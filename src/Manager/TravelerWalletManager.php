<?php

namespace App\Manager;

use App\Contract\AgencyFlexPayClientInterface;
use App\Entity\Agency;
use App\Entity\TravelerWallet;
use App\Entity\User;
use App\Entity\WalletLedger;
use App\Entity\WalletTopup;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\TravelerWalletRepository;
use App\Repository\WalletLedgerRepository;
use App\Repository\WalletTopupRepository;
use App\Service\Traveler\TravelerNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class TravelerWalletManager
{
    public const int LOW_BALANCE_THRESHOLD = 5000;

    public function __construct(
        private EntityManagerInterface $em,
        private TravelerWalletRepository $wallets,
        private WalletLedgerRepository $ledgerEntries,
        private WalletTopupRepository $topups,
        private AgencyFlexPayClientInterface $flexPay,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
        private TravelerNotificationService $travelerNotifications,
    ) {
    }

    public function getOrCreateWallet(User $user, string $currency = Agency::DEFAULT_CURRENCY): TravelerWallet
    {
        $existing = $this->wallets->findOneByUserAndCurrency($user, $currency);
        if ($existing instanceof TravelerWallet) {
            return $existing;
        }

        $wallet = new TravelerWallet();
        $wallet->setUser($user);
        $wallet->setCurrency($currency);
        $wallet->setBalance(0);

        $this->em->persist($wallet);
        $this->em->flush();

        return $wallet;
    }

    public function getWalletForUser(User $user): TravelerWallet
    {
        return $this->getOrCreateWallet($user);
    }

    /**
     * @return list<WalletLedger>
     */
    public function listLedger(TravelerWallet $wallet, int $limit = 50): array
    {
        return $this->ledgerEntries->findRecentForWallet($wallet, max(1, min($limit, 200)));
    }

    public function createTopup(
        User $user,
        int $amount,
        string $phone,
        string $method = WalletTopup::METHOD_MOBILE_MONEY,
    ): WalletTopup {
        if ($amount <= 0) {
            throw new UnprocessableEntityException('Topup amount must be positive.');
        }

        $userId = $user->getId();
        if (null !== $userId) {
            $managed = $this->em->find(User::class, $userId);
            if ($managed instanceof User) {
                $user = $managed;
            }
        }

        $wallet = $this->getOrCreateWallet($user);

        $topup = new WalletTopup();
        $topup->setWallet($wallet);
        $topup->setUser($user);
        $topup->setAmount($amount);
        $topup->setCurrency($wallet->getCurrency());
        $topup->setStatus(WalletTopup::STATUS_PENDING);
        $topup->setMethod($method);
        $topup->setPhone($phone);
        $topup->setProvider(WalletTopup::PROVIDER_FLEXPAY);

        $this->em->persist($topup);
        $this->em->flush();

        if (WalletTopup::METHOD_MOBILE_MONEY !== $method) {
            throw new UnprocessableEntityException('Only mobile money topups are supported in v1.');
        }

        $response = $this->flexPay->createMobileMoneyPaymentByReference(
            reference: (string) $topup->getId(),
            amount: $amount,
            currency: $wallet->getCurrency(),
            phone: $phone,
        );

        $topup->setProviderResponse($response->raw);

        if ($response->isSuccess()) {
            $topup->setProviderTx($response->transactionId);
        } else {
            $topup->setStatus(WalletTopup::STATUS_FAILED);
        }

        $this->em->flush();

        return $topup;
    }

    public function fulfillTopup(WalletTopup $topup): void
    {
        if (WalletTopup::STATUS_PAID === $topup->getStatus()) {
            return;
        }

        $wallet = $topup->getWallet();
        if (!$wallet instanceof TravelerWallet) {
            throw new UnprocessableEntityException('Topup has no wallet.');
        }

        $now = new \DateTimeImmutable();
        $topup->setStatus(WalletTopup::STATUS_PAID);
        $topup->setPaidAt($now);

        $newBalance = $wallet->getBalance() + $topup->getAmount();
        $wallet->setBalance($newBalance);

        $ledger = new WalletLedger();
        $ledger->setWallet($wallet);
        $ledger->setType(WalletLedger::TYPE_TOPUP);
        $ledger->setAmount($topup->getAmount());
        $ledger->setBalanceAfter($newBalance);
        $ledger->setCurrency($wallet->getCurrency());
        $ledger->setReference((string) $topup->getId());
        $ledger->setLabel('Wallet topup');

        $this->em->persist($ledger);
        $this->em->flush();
    }

    public function debit(
        User $user,
        int $amount,
        string $reference,
        string $label,
        ?array $meta = null,
    ): WalletLedger {
        if ($amount <= 0) {
            throw new UnprocessableEntityException('Debit amount must be positive.');
        }

        $userId = $user->getId();
        if (null !== $userId) {
            $managed = $this->em->find(User::class, $userId);
            if ($managed instanceof User) {
                $user = $managed;
            }
        }

        $wallet = $this->getOrCreateWallet($user);
        if ($wallet->getBalance() < $amount) {
            throw new UnprocessableEntityException('Insufficient wallet balance.');
        }

        $newBalance = $wallet->getBalance() - $amount;
        $wallet->setBalance($newBalance);

        $ledger = new WalletLedger();
        $ledger->setWallet($wallet);
        $ledger->setType(WalletLedger::TYPE_DEBIT);
        $ledger->setAmount($amount);
        $ledger->setBalanceAfter($newBalance);
        $ledger->setCurrency($wallet->getCurrency());
        $ledger->setReference($reference);
        $ledger->setLabel($label);
        $ledger->setMeta($meta);

        $this->em->persist($ledger);
        $this->em->flush();

        if ($newBalance < self::LOW_BALANCE_THRESHOLD) {
            $phone = $user->getPhone();
            if (null !== $phone && '' !== trim($phone)) {
                try {
                    $this->travelerNotifications->notifyLowWallet($phone, $newBalance);
                } catch (\Throwable $e) {
                    $this->logger->warning('wallet.low_balance.notify_failed', [
                        'userId' => $user->getId(),
                        'balance' => $newBalance,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $ledger;
    }

    public function getTopupForUser(User $user, string $id): WalletTopup
    {
        $topup = $this->topups->find($id);
        if (!$topup instanceof WalletTopup || $topup->getUser()?->getId() !== $user->getId()) {
            throw new UnavailableDataException('Topup not found.');
        }

        return $topup;
    }

    public function handleFlexpayWebhook(): ?WalletTopup
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return null;
        }

        $payload = $this->parseWebhookPayload($request);
        if (null === $payload) {
            return null;
        }

        $transactionId = $this->extractTransactionId($payload);
        $reference = $this->extractReference($payload);

        $topup = $this->resolveTopup($transactionId, $reference);
        if (!$topup instanceof WalletTopup) {
            return null;
        }

        if (null !== $transactionId && '' !== \trim($transactionId) && null === $topup->getProviderTx()) {
            $topup->setProviderTx($transactionId);
        }

        $providerResponse = $topup->getProviderResponse() ?? [];
        $providerResponse['webhook'] = $payload;
        $topup->setProviderResponse($providerResponse);

        if (WalletTopup::STATUS_PAID === $topup->getStatus()) {
            $this->em->flush();

            return $topup;
        }

        $incomingStatus = $this->normalizeIncomingStatus($payload);
        if (\in_array($incomingStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR'], true)) {
            $topup->setStatus(WalletTopup::STATUS_FAILED);
            $this->em->flush();

            return $topup;
        }

        if (\in_array($incomingStatus, ['SUCCESS', 'PAID', '0', 0], true)) {
            $this->fulfillTopup($topup);
            $this->em->flush();

            return $topup;
        }

        if (null !== $transactionId && '' !== \trim($transactionId)) {
            try {
                $check = $this->flexPay->checkStatus((string) $transactionId);
                $providerResponse['poll'] = $check->raw;
                $topup->setProviderResponse($providerResponse);

                $normalizedStatus = \is_string($check->status)
                    ? \strtoupper(\trim($check->status))
                    : $check->status;

                if ($check->isSuccess() && \in_array($normalizedStatus, ['SUCCESS', 'PAID', '0', 0], true)) {
                    $this->fulfillTopup($topup);
                } elseif (\in_array($normalizedStatus, ['FAILED', 'CANCELLED', 'DECLINED', 'ERROR', '4', 4], true)) {
                    $topup->setStatus(WalletTopup::STATUS_FAILED);
                }
            } catch (\Throwable $e) {
                $this->logger->error('wallet.flexpay.webhook.check_status.exception', [
                    'topupId' => $topup->getId(),
                    'transactionId' => $transactionId,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->em->flush();

        return $topup;
    }

    /** @return array<string, mixed>|null */
    private function parseWebhookPayload(Request $request): ?array
    {
        $rawBody = $request->getContent();
        $payload = \json_decode($rawBody, true);

        if (!\is_array($payload)) {
            $payload = $request->request->all();
        }

        return \is_array($payload) && [] !== $payload ? $payload : null;
    }

    /** @param array<string, mixed> $payload */
    private function extractTransactionId(array $payload): ?string
    {
        $transactionId = $payload['transactionId']
            ?? $payload['orderNumber']
            ?? $payload['order_number']
            ?? ($payload['transaction']['orderNumber'] ?? null)
            ?? ($payload['transaction']['order_number'] ?? null);

        return null !== $transactionId ? (string) $transactionId : null;
    }

    /** @param array<string, mixed> $payload */
    private function extractReference(array $payload): ?string
    {
        $reference = $payload['reference']
            ?? ($payload['transaction']['reference'] ?? null);

        return null !== $reference ? (string) $reference : null;
    }

    private function resolveTopup(?string $transactionId, ?string $reference): ?WalletTopup
    {
        if (null !== $transactionId && '' !== \trim($transactionId)) {
            $topup = $this->topups->findOneByProviderTx($transactionId);
            if ($topup instanceof WalletTopup) {
                return $topup;
            }
        }

        if (null !== $reference && '' !== \trim($reference)) {
            $refString = \trim($reference);
            if (\str_starts_with($refString, WalletTopup::ID_PREFIX)) {
                $topup = $this->topups->find($refString);
                if ($topup instanceof WalletTopup) {
                    return $topup;
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function normalizeIncomingStatus(array $payload): ?string
    {
        $incomingStatus = $payload['status']
            ?? ($payload['transaction']['status'] ?? null)
            ?? ($payload['code'] ?? null)
            ?? ($payload['message'] ?? null);

        if (\is_string($incomingStatus)) {
            return \strtoupper(\trim($incomingStatus));
        }

        if (\is_int($incomingStatus)) {
            return (string) $incomingStatus;
        }

        return null;
    }
}

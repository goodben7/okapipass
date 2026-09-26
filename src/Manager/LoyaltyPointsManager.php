<?php

namespace App\Manager;

use App\Domain\Agency\AgencyPermission;
use App\Dto\Agency\CreateSurprisePoolDto;
use App\Dto\Agency\DrawSurprisePoolDto;
use App\Dto\Agency\UpdateSurprisePoolDto;
use App\Entity\Agency;
use App\Entity\AgencyTicket;
use App\Entity\LoyaltyAccount;
use App\Entity\LoyaltyPointLedger;
use App\Entity\LoyaltyRule;
use App\Entity\SurprisePool;
use App\Entity\SurprisePoolItem;
use App\Entity\User;
use App\Exception\UnavailableDataException;
use App\Exception\UnprocessableEntityException;
use App\Repository\LoyaltyAccountRepository;
use App\Repository\LoyaltyPointLedgerRepository;
use App\Repository\LoyaltyRuleRepository;
use App\Repository\SurprisePoolItemRepository;
use App\Repository\SurprisePoolRepository;
use App\Repository\UserRepository;
use App\Service\Agency\AgencyContext;
use App\Service\Auth\TravelerOtpService;
use Doctrine\ORM\EntityManagerInterface;

final class LoyaltyPointsManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AgencyContext $agencyContext,
        private LoyaltyAccountRepository $accounts,
        private LoyaltyPointLedgerRepository $ledger,
        private LoyaltyRuleRepository $rules,
        private SurprisePoolRepository $pools,
        private SurprisePoolItemRepository $poolItems,
        private UserRepository $users,
        private TravelerOtpService $otpService,
    ) {
    }

    public function getOrCreateAccount(User $user, Agency $agency): LoyaltyAccount
    {
        $phone = $user->getPhone();
        if (null === $phone || '' === trim($phone)) {
            throw new UnprocessableEntityException('User phone is required for loyalty account.');
        }

        $normalized = $this->otpService->normalizePhone($phone);
        $existing = $this->accounts->findOneByAgencyAndPhone($agency, $normalized);
        if ($existing instanceof LoyaltyAccount) {
            return $existing;
        }

        $account = new LoyaltyAccount();
        $account->setAgency($agency);
        $account->setPhone($normalized);
        $account->setPoints(0);

        $this->em->persist($account);
        $this->em->flush();

        return $account;
    }

    public function earnPoints(User $user, Agency $agency, int $points, string $reference, string $label): void
    {
        if ($points <= 0) {
            return;
        }

        $account = $this->getOrCreateAccount($user, $agency);
        if ($this->ledger->existsByAccountAndLabel($account, $reference)) {
            return;
        }

        $newBalance = $account->getPoints() + $points;
        $account->setPoints($newBalance);

        $entry = new LoyaltyPointLedger();
        $entry->setAccount($account);
        $entry->setDelta($points);
        $entry->setBalanceAfter($newBalance);
        $entry->setReason(LoyaltyPointLedger::REASON_EARN_TRIP);
        $entry->setLabel($reference);

        $this->em->persist($entry);
        $this->em->flush();
    }

    public function spendPoints(User $user, Agency $agency, int $points, string $reference, string $label): void
    {
        if ($points <= 0) {
            throw new UnprocessableEntityException('Points to spend must be positive.');
        }

        $account = $this->getOrCreateAccount($user, $agency);
        if ($this->ledger->existsByAccountAndLabel($account, $reference)) {
            return;
        }

        if ($account->getPoints() < $points) {
            throw new UnprocessableEntityException('Insufficient loyalty points.');
        }

        $newBalance = $account->getPoints() - $points;
        $account->setPoints($newBalance);

        $entry = new LoyaltyPointLedger();
        $entry->setAccount($account);
        $entry->setDelta(-$points);
        $entry->setBalanceAfter($newBalance);
        $entry->setReason(LoyaltyPointLedger::REASON_REDEEM);
        $entry->setLabel($reference);

        $this->em->persist($entry);
        $this->em->flush();
    }

    public function onAgencyTicketPaid(AgencyTicket $ticket): void
    {
        $agency = $ticket->getAgency();
        if (!$agency instanceof Agency) {
            return;
        }

        $phone = $ticket->getPassengerPhone();
        if (null === $phone || '' === trim($phone)) {
            return;
        }

        $normalized = $this->otpService->normalizePhone($phone);
        $user = $this->users->findOneBy(['phone' => $normalized]);
        if (!$user instanceof User) {
            $account = $this->accounts->findOneByAgencyAndPhone($agency, $normalized);
            if (!$account instanceof LoyaltyAccount) {
                return;
            }
        }

        foreach ($this->rules->findActiveForAgency($agency) as $rule) {
            if (LoyaltyRule::REWARD_POINTS !== $rule->getRewardType()) {
                continue;
            }

            $points = $rule->getPointsEarn();
            if ($points <= 0) {
                continue;
            }

            if ($user instanceof User) {
                $this->earnPoints(
                    $user,
                    $agency,
                    $points,
                    'ticket:' . $ticket->getId() . ':rule:' . $rule->getId(),
                    'Points for ticket ' . $ticket->getReference(),
                );
            } else {
                $account = $this->accounts->findOneByAgencyAndPhone($agency, $normalized)
                    ?? $this->createAccountForPhone($agency, $normalized);
                $this->earnPointsForAccount($account, $points, 'ticket:' . $ticket->getId() . ':rule:' . $rule->getId(), $ticket);
            }
        }
    }

    public function createPool(CreateSurprisePoolDto $dto): SurprisePool
    {
        $this->agencyContext->requirePermission(AgencyPermission::LOYALTY_WRITE);
        $agency = $this->agencyContext->requireAgency();

        $pool = new SurprisePool();
        $pool->setAgency($agency);
        $pool->setLabel((string) $dto->label);
        $pool->setActive(false !== $dto->active);

        $this->em->persist($pool);
        $this->em->flush();

        return $pool;
    }

    public function updatePool(SurprisePool $pool, UpdateSurprisePoolDto $dto): SurprisePool
    {
        $this->agencyContext->requirePermission(AgencyPermission::LOYALTY_WRITE);
        $this->agencyContext->assertOwns($pool->getAgency());

        if (null !== $dto->label) {
            $pool->setLabel($dto->label);
        }
        if (null !== $dto->active) {
            $pool->setActive($dto->active);
        }

        $this->em->flush();

        return $pool;
    }

    /**
     * @return array{item: SurprisePoolItem, pointsCredited?: int, accountId?: string}
     */
    public function drawFromPool(SurprisePool $pool, ?string $userId = null): array
    {
        $this->agencyContext->assertOwns($pool->getAgency());

        if (!$pool->isActive()) {
            throw new UnprocessableEntityException('Surprise pool is not active.');
        }

        $items = $this->poolItems->findActiveForPool($pool);
        if ([] === $items) {
            throw new UnprocessableEntityException('No active items in surprise pool.');
        }

        $item = $this->pickWeightedItem($items);

        $result = [
            'item' => $item,
            'rewardType' => $item->getRewardType(),
            'rewardValue' => $item->getRewardValue(),
            'label' => $item->getLabel(),
        ];

        if (SurprisePoolItem::REWARD_POINTS === $item->getRewardType() && null !== $userId && '' !== trim($userId)) {
            $user = $this->users->find(trim($userId));
            if ($user instanceof User) {
                $this->earnPoints(
                    $user,
                    $pool->getAgency(),
                    $item->getRewardValue(),
                    'surprise:' . $pool->getId() . ':' . uniqid('', true),
                    'Surprise draw: ' . $item->getLabel(),
                );
                $account = $this->getOrCreateAccount($user, $pool->getAgency());
                $result['pointsCredited'] = $item->getRewardValue();
                $result['accountId'] = (string) $account->getId();
            }
        }

        return $result;
    }

    public function getAccountForTraveler(User $user, Agency $agency): LoyaltyAccount
    {
        return $this->getOrCreateAccount($user, $agency);
    }

    public function addPoolItem(
        SurprisePool $pool,
        string $label,
        string $rewardType,
        int $rewardValue,
        int $weight = 1,
        bool $active = true,
    ): SurprisePoolItem {
        $this->agencyContext->requirePermission(AgencyPermission::LOYALTY_WRITE);
        $this->agencyContext->assertOwns($pool->getAgency());

        if (!\in_array($rewardType, SurprisePoolItem::getRewardTypesAsList(), true)) {
            throw new UnprocessableEntityException('Invalid surprise reward type.');
        }

        $item = new SurprisePoolItem();
        $item->setPool($pool);
        $item->setLabel($label);
        $item->setRewardType($rewardType);
        $item->setRewardValue($rewardValue);
        $item->setWeight(\max(1, $weight));
        $item->setActive($active);
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    private function createAccountForPhone(Agency $agency, string $phone): LoyaltyAccount
    {
        $account = new LoyaltyAccount();
        $account->setAgency($agency);
        $account->setPhone($phone);
        $account->setPoints(0);
        $this->em->persist($account);
        $this->em->flush();

        return $account;
    }

    private function earnPointsForAccount(LoyaltyAccount $account, int $points, string $reference, AgencyTicket $ticket): void
    {
        if ($this->ledger->existsByAccountAndLabel($account, $reference)) {
            return;
        }

        $newBalance = $account->getPoints() + $points;
        $account->setPoints($newBalance);

        $entry = new LoyaltyPointLedger();
        $entry->setAccount($account);
        $entry->setDelta($points);
        $entry->setBalanceAfter($newBalance);
        $entry->setReason(LoyaltyPointLedger::REASON_EARN_TRIP);
        $entry->setLabel($reference);
        $entry->setTicket($ticket);

        $this->em->persist($entry);
        $this->em->flush();
    }

    /**
     * @param list<SurprisePoolItem> $items
     */
    private function pickWeightedItem(array $items): SurprisePoolItem
    {
        $totalWeight = 0;
        foreach ($items as $item) {
            $totalWeight += max(1, $item->getWeight());
        }

        $pick = random_int(1, $totalWeight);
        $cursor = 0;
        foreach ($items as $item) {
            $cursor += max(1, $item->getWeight());
            if ($pick <= $cursor) {
                return $item;
            }
        }

        return $items[0];
    }
}

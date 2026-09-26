<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TravelerWalletTopupResource;
use App\Entity\WalletTopup;
use App\Manager\TravelerWalletManager;

/** @implements ProviderInterface<TravelerWalletTopupResource> */
final class TravelerWalletTopupItemProvider implements ProviderInterface
{
    public function __construct(
        private TravelerMeProvider $travelerMe,
        private TravelerWalletManager $wallets,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TravelerWalletTopupResource
    {
        $user = $this->travelerMe->requireTraveler();
        $topup = $this->wallets->getTopupForUser($user, (string) ($uriVariables['id'] ?? ''));

        return self::fromEntity($topup);
    }

    public static function fromEntity(WalletTopup $topup): TravelerWalletTopupResource
    {
        return new TravelerWalletTopupResource(
            id: (string) $topup->getId(),
            amount: $topup->getAmount(),
            currency: $topup->getCurrency(),
            status: $topup->getStatus(),
            method: $topup->getMethod(),
            phone: $topup->getPhone(),
            providerTx: $topup->getProviderTx(),
            paidAt: $topup->getPaidAt()?->format(\DateTimeInterface::ATOM),
            createdAt: $topup->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}

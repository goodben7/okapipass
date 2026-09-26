<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TravelerWalletResource;
use App\Manager\TravelerWalletManager;

/** @implements ProviderInterface<TravelerWalletResource> */
final class TravelerWalletProvider implements ProviderInterface
{
    public function __construct(
        private TravelerMeProvider $travelerMe,
        private TravelerWalletManager $wallets,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TravelerWalletResource
    {
        $user = $this->travelerMe->requireTraveler();
        $wallet = $this->wallets->getWalletForUser($user);

        return new TravelerWalletResource(
            id: (string) $wallet->getId(),
            balance: $wallet->getBalance(),
            currency: $wallet->getCurrency(),
            updatedAt: $wallet->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}

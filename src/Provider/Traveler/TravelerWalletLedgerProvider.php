<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TravelerWalletLedgerResource;
use App\Manager\TravelerWalletManager;

/** @implements ProviderInterface<list<TravelerWalletLedgerResource>> */
final class TravelerWalletLedgerProvider implements ProviderInterface
{
    public function __construct(
        private TravelerMeProvider $travelerMe,
        private TravelerWalletManager $wallets,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->travelerMe->requireTraveler();
        $wallet = $this->wallets->getWalletForUser($user);
        $rows = [];
        foreach ($this->wallets->listLedger($wallet) as $entry) {
            $rows[] = new TravelerWalletLedgerResource(
                id: (string) $entry->getId(),
                type: $entry->getType(),
                amount: $entry->getAmount(),
                balanceAfter: $entry->getBalanceAfter(),
                currency: $entry->getCurrency(),
                reference: $entry->getReference(),
                label: $entry->getLabel(),
                createdAt: $entry->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            );
        }

        return $rows;
    }
}

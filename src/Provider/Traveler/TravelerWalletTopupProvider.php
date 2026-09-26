<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TravelerWalletTopupResource;
use App\Entity\WalletTopup;
use App\Exception\UnauthorizedActionException;
use App\Exception\UnavailableDataException;
use App\Repository\WalletTopupRepository;

/** @implements ProviderInterface<TravelerWalletTopupResource> */
final class TravelerWalletTopupProvider implements ProviderInterface
{
    public function __construct(
        private TravelerMeProvider $travelerMe,
        private WalletTopupRepository $topups,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TravelerWalletTopupResource
    {
        $user = $this->travelerMe->requireTraveler();
        $id = (string) ($uriVariables['id'] ?? '');
        $topup = $this->topups->find($id);
        if (!$topup instanceof WalletTopup) {
            throw new UnavailableDataException('Topup not found.');
        }

        if ($topup->getUser()?->getId() !== $user->getId()) {
            throw new UnauthorizedActionException('Topup not accessible.');
        }

        return $this->toResource($topup);
    }

    public function toResource(WalletTopup $topup): TravelerWalletTopupResource
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

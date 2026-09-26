<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TravelerLoyaltyResource;
use App\Entity\Agency;
use App\Exception\UnprocessableEntityException;
use App\Manager\LoyaltyPointsManager;
use App\Repository\AgencyRepository;

/** @implements ProviderInterface<TravelerLoyaltyResource> */
final class TravelerLoyaltyProvider implements ProviderInterface
{
    public function __construct(
        private TravelerMeProvider $travelerMe,
        private LoyaltyPointsManager $loyaltyPoints,
        private AgencyRepository $agencies,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TravelerLoyaltyResource
    {
        $user = $this->travelerMe->requireTraveler();
        $agencyId = $context['filters']['agencyId'] ?? null;
        if (!\is_string($agencyId) || '' === trim($agencyId)) {
            throw new UnprocessableEntityException('Query parameter agencyId is required.');
        }

        $agency = $this->agencies->find(trim($agencyId));
        if (!$agency instanceof Agency) {
            throw new UnprocessableEntityException('Agency not found.');
        }

        $account = $this->loyaltyPoints->getAccountForTraveler($user, $agency);

        return new TravelerLoyaltyResource(
            id: (string) $account->getId(),
            agencyId: (string) $agency->getId(),
            phone: $account->getPhone(),
            points: $account->getPoints(),
        );
    }
}

<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\TravelerBeneficiary;
use App\Manager\TravelerBeneficiaryManager;

/** @implements ProviderInterface<list<TravelerBeneficiary>> */
final class TravelerBeneficiaryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private TravelerBeneficiaryManager $manager,
        private TravelerMeProvider $me,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->manager->listFor($this->me->requireTraveler());
    }
}

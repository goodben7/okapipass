<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\TravelerBeneficiary;
use App\Manager\TravelerBeneficiaryManager;

/** @implements ProviderInterface<TravelerBeneficiary> */
final class TravelerBeneficiaryItemProvider implements ProviderInterface
{
    public function __construct(
        private TravelerBeneficiaryManager $manager,
        private TravelerMeProvider $me,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TravelerBeneficiary
    {
        return $this->manager->getOwned($this->me->requireTraveler(), (string) ($uriVariables['id'] ?? ''));
    }
}

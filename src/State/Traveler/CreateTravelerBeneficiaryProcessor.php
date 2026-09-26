<?php

namespace App\State\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Traveler\CreateTravelerBeneficiaryDto;
use App\Entity\TravelerBeneficiary;
use App\Manager\TravelerBeneficiaryManager;
use App\Provider\Traveler\TravelerMeProvider;

/** @implements ProcessorInterface<CreateTravelerBeneficiaryDto, TravelerBeneficiary> */
final class CreateTravelerBeneficiaryProcessor implements ProcessorInterface
{
    public function __construct(
        private TravelerBeneficiaryManager $manager,
        private TravelerMeProvider $me,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerBeneficiary
    {
        \assert($data instanceof CreateTravelerBeneficiaryDto);

        return $this->manager->create($this->me->requireTraveler(), $data);
    }
}

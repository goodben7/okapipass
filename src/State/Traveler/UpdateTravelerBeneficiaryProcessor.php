<?php

namespace App\State\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Traveler\UpdateTravelerBeneficiaryDto;
use App\Entity\TravelerBeneficiary;
use App\Exception\UnavailableDataException;
use App\Manager\TravelerBeneficiaryManager;
use App\Provider\Traveler\TravelerMeProvider;

/** @implements ProcessorInterface<UpdateTravelerBeneficiaryDto, TravelerBeneficiary> */
final class UpdateTravelerBeneficiaryProcessor implements ProcessorInterface
{
    public function __construct(
        private TravelerBeneficiaryManager $manager,
        private TravelerMeProvider $me,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerBeneficiary
    {
        $entity = $context['previous_data'] ?? null;
        if (!$entity instanceof TravelerBeneficiary) {
            throw new UnavailableDataException('Beneficiary not found.');
        }
        \assert($data instanceof UpdateTravelerBeneficiaryDto);

        return $this->manager->update($this->me->requireTraveler(), $entity, $data);
    }
}

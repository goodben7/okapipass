<?php

namespace App\State\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\TravelerBeneficiary;
use App\Exception\UnavailableDataException;
use App\Manager\TravelerBeneficiaryManager;
use App\Provider\Traveler\TravelerMeProvider;

/** @implements ProcessorInterface<TravelerBeneficiary|null, void> */
final class DeleteTravelerBeneficiaryProcessor implements ProcessorInterface
{
    public function __construct(
        private TravelerBeneficiaryManager $manager,
        private TravelerMeProvider $me,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof TravelerBeneficiary) {
            throw new UnavailableDataException('Beneficiary not found.');
        }

        $this->manager->delete($this->me->requireTraveler(), $data);
    }
}

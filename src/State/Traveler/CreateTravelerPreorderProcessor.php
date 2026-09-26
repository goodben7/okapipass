<?php

namespace App\State\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Traveler\CreateTravelerPreorderDto;
use App\Entity\TravelerPreorder;
use App\Manager\TravelerPassManager;
use App\Provider\Traveler\TravelerMeProvider;

/** @implements ProcessorInterface<CreateTravelerPreorderDto, TravelerPreorder> */
final class CreateTravelerPreorderProcessor implements ProcessorInterface
{
    public function __construct(
        private TravelerMeProvider $travelerMe,
        private TravelerPassManager $passManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerPreorder
    {
        if (!$data instanceof CreateTravelerPreorderDto) {
            throw new \InvalidArgumentException('Expected CreateTravelerPreorderDto.');
        }

        return $this->passManager->createPreorder($this->travelerMe->requireTraveler(), $data);
    }
}

<?php

namespace App\State\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Traveler\CreateTravelerPassPurchaseDto;
use App\Entity\TravelerPass;
use App\Manager\TravelerPassManager;
use App\Provider\Traveler\TravelerMeProvider;

/** @implements ProcessorInterface<CreateTravelerPassPurchaseDto, TravelerPass> */
final class CreateTravelerPassPurchaseProcessor implements ProcessorInterface
{
    public function __construct(
        private TravelerMeProvider $travelerMe,
        private TravelerPassManager $passManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerPass
    {
        if (!$data instanceof CreateTravelerPassPurchaseDto) {
            throw new \InvalidArgumentException('Expected CreateTravelerPassPurchaseDto.');
        }

        return $this->passManager->purchasePass($this->travelerMe->requireTraveler(), $data);
    }
}

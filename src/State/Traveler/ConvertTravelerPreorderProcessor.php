<?php

namespace App\State\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Traveler\ConvertTravelerPreorderDto;
use App\Entity\TravelerPreorder;
use App\Manager\TravelerPassManager;
use App\Provider\Traveler\TravelerMeProvider;

/** @implements ProcessorInterface<ConvertTravelerPreorderDto, TravelerPreorder> */
final class ConvertTravelerPreorderProcessor implements ProcessorInterface
{
    public function __construct(
        private TravelerMeProvider $travelerMe,
        private TravelerPassManager $passManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerPreorder
    {
        $preorder = $context['previous_data'] ?? $data;
        if (!$preorder instanceof TravelerPreorder) {
            throw new \InvalidArgumentException('Expected TravelerPreorder.');
        }

        $dto = $data instanceof ConvertTravelerPreorderDto ? $data : new ConvertTravelerPreorderDto();

        return $this->passManager->convertPreorder($this->travelerMe->requireTraveler(), $preorder, $dto);
    }
}

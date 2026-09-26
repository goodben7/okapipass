<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\TravelerPreorder;
use App\Manager\TravelerPassManager;

/** @implements ProcessorInterface<mixed, TravelerPreorder> */
final class CancelTravelerPreorderProcessor implements ProcessorInterface
{
    public function __construct(private TravelerPassManager $passManager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerPreorder
    {
        $preorder = $context['previous_data'] ?? null;
        if (!$preorder instanceof TravelerPreorder) {
            throw new \InvalidArgumentException('Expected TravelerPreorder as previous_data.');
        }

        return $this->passManager->cancelPreorder($preorder);
    }
}

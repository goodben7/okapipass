<?php

namespace App\Provider\Traveler;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\TravelerPassProduct;
use App\Manager\TravelerPassManager;

/** @implements ProviderInterface<TravelerPassProduct> */
final class TravelerPassProductCollectionProvider implements ProviderInterface
{
    public function __construct(private TravelerPassManager $passManager)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->passManager->listActiveProducts();
    }
}

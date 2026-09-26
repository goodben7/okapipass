<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateAgencyPassProductDto;
use App\Entity\TravelerPassProduct;
use App\Manager\TravelerPassManager;

/** @implements ProcessorInterface<UpdateAgencyPassProductDto, TravelerPassProduct> */
final class UpdateAgencyPassProductProcessor implements ProcessorInterface
{
    public function __construct(private TravelerPassManager $passManager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerPassProduct
    {
        if (!$data instanceof UpdateAgencyPassProductDto) {
            throw new \InvalidArgumentException('Expected UpdateAgencyPassProductDto.');
        }

        $product = $context['previous_data'] ?? null;
        if (!$product instanceof TravelerPassProduct) {
            throw new \InvalidArgumentException('Expected TravelerPassProduct as previous_data.');
        }

        return $this->passManager->updateProduct($product, $data);
    }
}

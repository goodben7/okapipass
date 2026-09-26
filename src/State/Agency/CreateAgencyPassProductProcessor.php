<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateAgencyPassProductDto;
use App\Entity\TravelerPassProduct;
use App\Manager\TravelerPassManager;

/** @implements ProcessorInterface<CreateAgencyPassProductDto, TravelerPassProduct> */
final class CreateAgencyPassProductProcessor implements ProcessorInterface
{
    public function __construct(private TravelerPassManager $passManager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TravelerPassProduct
    {
        if (!$data instanceof CreateAgencyPassProductDto) {
            throw new \InvalidArgumentException('Expected CreateAgencyPassProductDto.');
        }

        return $this->passManager->createProduct($data);
    }
}

<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateSellerCommissionRuleDto;
use App\Entity\SellerCommissionRule;
use App\Exception\UnavailableDataException;
use App\Manager\SellerCommissionManager;

/** @implements ProcessorInterface<UpdateSellerCommissionRuleDto, SellerCommissionRule> */
final class UpdateSellerCommissionRuleProcessor implements ProcessorInterface
{
    public function __construct(private SellerCommissionManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SellerCommissionRule
    {
        $entity = $context['previous_data'] ?? $data;
        if (!$entity instanceof SellerCommissionRule) {
            throw new UnavailableDataException('SellerCommissionRule not found.');
        }
        \assert($data instanceof UpdateSellerCommissionRuleDto);

        return $this->manager->updateRule($entity, $data);
    }
}

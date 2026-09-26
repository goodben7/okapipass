<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateSellerCommissionRuleDto;
use App\Entity\SellerCommissionRule;
use App\Manager\SellerCommissionManager;

/** @implements ProcessorInterface<CreateSellerCommissionRuleDto, SellerCommissionRule> */
final class CreateSellerCommissionRuleProcessor implements ProcessorInterface
{
    public function __construct(private SellerCommissionManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SellerCommissionRule
    {
        \assert($data instanceof CreateSellerCommissionRuleDto);

        return $this->manager->createRule($data);
    }
}

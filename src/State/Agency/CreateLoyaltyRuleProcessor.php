<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\CreateLoyaltyRuleDto;
use App\Entity\LoyaltyRule;
use App\Manager\LoyaltyManager;

/** @implements ProcessorInterface<CreateLoyaltyRuleDto, LoyaltyRule> */
final class CreateLoyaltyRuleProcessor implements ProcessorInterface
{
    public function __construct(private LoyaltyManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoyaltyRule
    {
        \assert($data instanceof CreateLoyaltyRuleDto);

        return $this->manager->createRule($data);
    }
}

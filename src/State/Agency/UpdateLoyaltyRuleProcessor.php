<?php

namespace App\State\Agency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Agency\UpdateLoyaltyRuleDto;
use App\Entity\LoyaltyRule;
use App\Manager\LoyaltyManager;

/** @implements ProcessorInterface<UpdateLoyaltyRuleDto, LoyaltyRule> */
final class UpdateLoyaltyRuleProcessor implements ProcessorInterface
{
    public function __construct(private LoyaltyManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoyaltyRule
    {
        \assert($data instanceof UpdateLoyaltyRuleDto);
        $rule = $context['previous_data'] ?? null;
        \assert($rule instanceof LoyaltyRule);

        return $this->manager->updateRule($rule, $data);
    }
}

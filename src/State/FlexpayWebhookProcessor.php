<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Manager\PaymentManager;

class FlexpayWebhookProcessor implements ProcessorInterface
{
    public function __construct(private PaymentManager $manager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        return $this->manager->handleWebhook();
    }
}

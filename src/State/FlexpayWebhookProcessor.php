<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Manager\PaymentManager;
use App\Manager\PublicAgencyPaymentManager;

class FlexpayWebhookProcessor implements ProcessorInterface
{
    public function __construct(
        private PaymentManager $paymentManager,
        private PublicAgencyPaymentManager $agencyPaymentManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $payment = $this->paymentManager->handleWebhook();
        if (null !== $payment) {
            return $payment;
        }

        return $this->agencyPaymentManager->handleFlexpayWebhook();
    }
}

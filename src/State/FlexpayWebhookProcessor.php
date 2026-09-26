<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Manager\PaymentManager;
use App\Manager\PublicAgencyPaymentManager;
use App\Manager\TravelerWalletManager;

class FlexpayWebhookProcessor implements ProcessorInterface
{
    public function __construct(
        private PaymentManager $paymentManager,
        private TravelerWalletManager $walletManager,
        private PublicAgencyPaymentManager $agencyPaymentManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $payment = $this->paymentManager->handleWebhook();
        if (null !== $payment) {
            return $payment;
        }

        $topup = $this->walletManager->handleFlexpayWebhook();
        if (null !== $topup) {
            return $topup;
        }

        return $this->agencyPaymentManager->handleFlexpayWebhook();
    }
}

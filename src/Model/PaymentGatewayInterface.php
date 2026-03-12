<?php

namespace App\Model;

use App\Entity\Payment;
use App\Model\GatewayResponse;

interface PaymentGatewayInterface
{
    public function createPayment(Payment $payment): GatewayResponse; 

    public function checkStatus(string $transactionId): GatewayResponse;
}

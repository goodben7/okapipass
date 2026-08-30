<?php

namespace App\Contract;

use App\Entity\AgencyPayment;
use App\Model\GatewayResponse;

interface AgencyFlexPayClientInterface
{
    public function initiate(AgencyPayment $payment, string $phone): GatewayResponse;

    public function checkStatus(string $transactionId): GatewayResponse;

    /**
     * @return array{action: string, fields: array<string, string>}
     */
    public function buildAgencyCardPaymentForm(AgencyPayment $payment, string $ticketRef): array;
}

<?php

namespace App\Tests\Stub;

use App\Contract\AgencyFlexPayClientInterface;
use App\Entity\AgencyPayment;
use App\Model\GatewayResponse;

final class StubAgencyFlexPayClient implements AgencyFlexPayClientInterface
{
    public function initiate(AgencyPayment $payment, string $phone): GatewayResponse
    {
        return new GatewayResponse(
            success: true,
            transactionId: 'TEST-TX-' . (string) $payment->getId(),
            status: 'PENDING',
            message: 'Stub payment initiated',
            raw: ['stub' => true, 'phone' => $phone],
        );
    }

    public function checkStatus(string $transactionId): GatewayResponse
    {
        return new GatewayResponse(
            success: true,
            transactionId: $transactionId,
            status: 'SUCCESS',
            message: 'Stub payment paid',
            raw: ['stub' => true, 'status' => 'SUCCESS'],
        );
    }

    public function buildAgencyCardPaymentForm(AgencyPayment $payment, string $ticketRef): array
    {
        return [
            'action' => 'https://flexpay.test/card',
            'fields' => [
                'reference' => 'ABP-' . (string) $payment->getId(),
                'amount' => (string) $payment->getAmount(),
                'currency' => $payment->getCurrency(),
            ],
        ];
    }
}

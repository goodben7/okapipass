<?php

namespace App\Service;

use App\Entity\Payment;
use App\Model\GatewayResponse;
use App\Model\PaymentGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FlexPayGateway implements PaymentGatewayInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private string $merchantId,
        private string $token,
        private string $callbackUrl,
        private LoggerInterface $logger,
    ) {}
    
    public function createPayment(Payment $payment): GatewayResponse
    {
        $authorization = \str_starts_with($this->token, 'Bearer ')
            ? $this->token
            : 'Bearer ' . $this->token;

        $url = 'https://backend.flexpay.cd/api/rest/v1/paymentService';

        $phone = $payment->getTicket()->getPhone();
        $phone = null === $phone ? null : \preg_replace('/\D+/', '', $phone);

        $payload = [
            'merchant' => $this->merchantId,
            'type' => '1',
            'reference' => $payment->getReference(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'description' => 'Payment ' . $payment->getReference(),
            'callbackUrl' => $this->callbackUrl,
            'phone' => $phone,
        ];

        $this->logger->info('flexpay.create_payment.request', [
            'paymentId' => $payment->getId(),
            'reference' => $payment->getReference(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'callbackUrl' => $this->callbackUrl,
        ]);

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Authorization' => $authorization,
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->error('flexpay.create_payment.exception', [
                'paymentId' => $payment->getId(),
                'reference' => $payment->getReference(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return new GatewayResponse(
                success: false,
                transactionId: null,
                status: null,
                message: $e->getMessage(),
                raw: null,
            );
        }

        $this->logger->info('flexpay.create_payment.response', [
            'paymentId' => $payment->getId(),
            'reference' => $payment->getReference(),
            'httpStatus' => $statusCode,
            'code' => $data['code'] ?? null,
            'orderNumber' => $data['orderNumber'] ?? null,
            'message' => $data['message'] ?? null,
        ]);

        $code = $data['code'] ?? null;
        $success = $code === '0' || $code === 0;
        $message = $data['message'] ?? (\is_string($code) ? $code : null);

        return new GatewayResponse(
            success: $success,
            transactionId: $data['orderNumber'] ?? null,
            status: $data['status'] ?? $data['message'] ?? null,
            message: $message,
            raw: $data
        );
    }

    public function checkStatus(string $transactionId): GatewayResponse
    {
        $url = \sprintf(
            'https://apicheck.flexpaie.com/api/rest/v1/check/%s',
            $transactionId
        );

        $this->logger->info('flexpay.check_status.request', [
            'transactionId' => $transactionId,
        ]);

        try {
            $response = $this->client->request('GET', $url);
            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->error('flexpay.check_status.exception', [
                'transactionId' => $transactionId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return new GatewayResponse(
                success: false,
                transactionId: $transactionId,
                status: null,
                message: $e->getMessage(),
                raw: null,
            );
        }

        $providerStatus = $data['status'] ?? ($data['transaction']['status'] ?? null);
        $normalizedStatus = \is_string($providerStatus) ? \strtoupper(\trim($providerStatus)) : $providerStatus;

        $success = $normalizedStatus === 'SUCCESS' || $normalizedStatus === '0' || $normalizedStatus === 0;

        $this->logger->info('flexpay.check_status.response', [
            'transactionId' => $transactionId,
            'httpStatus' => $statusCode,
            'status' => $providerStatus,
        ]);

        return new GatewayResponse(
            success: $success,
            transactionId: $transactionId,
            status: $providerStatus,
            message: $data['message'] ?? null,
            raw: $data
        );
    }
}

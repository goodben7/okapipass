<?php

namespace App\Service;

use App\Entity\Payment;
use App\Model\GatewayResponse;
use App\Model\PaymentGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FlexPayGateway implements PaymentGatewayInterface
{
    public function __construct(
        private HttpClientInterface $client,
        private string $merchantId,
        private string $token,
        private string $callbackUrl,
        private string $paymentUrl,
        private string $cardPayUrl,
        private string $checkStatusUrl,
        private string $cardApproveUrl,
        private string $cardCancelUrl,
        private string $cardDeclineUrl,
        private LoggerInterface $logger,
    ) {}
    
    public function createPayment(Payment $payment): GatewayResponse
    {
        if (Payment::METHOD_CARD === $payment->getMethod()) {
            return $this->createCardPayment($payment);
        }

        return $this->createMobileMoneyPayment($payment);
    }

    private function createMobileMoneyPayment(Payment $payment): GatewayResponse
    {
        $authorization = \str_starts_with($this->token, 'Bearer ')
            ? $this->token
            : 'Bearer ' . $this->token;

        $paymentUrl = $this->normalizeUrl($this->paymentUrl);

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
            $response = $this->client->request('POST', $paymentUrl, [
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

    private function createCardPayment(Payment $payment): GatewayResponse
    {
        $token = \trim($this->token);
        $token = \trim($token, "\"'`");
        $tokenNoBearer = \preg_replace('/^\s*Bearer\s+/i', '', $token) ?? $token;
        $authorization = 'Bearer ' . $tokenNoBearer;

        $cardPayUrl = $this->normalizeUrl($this->cardPayUrl);
        $callbackUrl = $this->normalizeUrl($this->callbackUrl);
        $approveUrl = $this->normalizeUrl($this->cardApproveUrl);
        $cancelUrl = $this->normalizeUrl($this->cardCancelUrl);
        $declineUrl = $this->normalizeUrl($this->cardDeclineUrl);

        $cardReference = $this->buildCardReference($payment);

        $payload = [
            'authorization' => $authorization,
            'merchant' => $this->merchantId,
            'reference' => $cardReference,
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'description' => 'Payment ' . $payment->getReference(),
            'callback_url' => $callbackUrl,
            'approve_url' => $approveUrl,
            'cancel_url' => $cancelUrl,
            'decline_url' => $declineUrl,
        ];

        $this->logger->info('flexpay.card.create_payment.request', [
            'paymentId' => $payment->getId(),
            'reference' => $payment->getReference(),
            'cardReference' => $cardReference,
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'callbackUrl' => $callbackUrl,
            'approveUrl' => $approveUrl,
            'cancelUrl' => $cancelUrl,
            'declineUrl' => $declineUrl,
            'authLen' => \strlen($authorization),
            'authStartsWithBearer' => \str_starts_with($authorization, 'Bearer '),
            'tokenNoBearerLen' => \strlen($tokenNoBearer),
            'tokenHash' => \hash('sha256', $tokenNoBearer),
        ]);

        try {
            $response = $this->client->request('POST', $cardPayUrl, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => \http_build_query($payload, '', '&', \PHP_QUERY_RFC3986),
            ]);
            $statusCode = $response->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->error('flexpay.card.create_payment.exception', [
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

        $parsed = $this->decodeJsonResponse($response);
        $data = $parsed['data'];

        if (null === $data && 200 === $statusCode && \is_string($parsed['contentType']) && \str_starts_with(\strtolower($parsed['contentType']), 'text/html')) {
            $htmlError = \is_string($parsed['rawBody']) ? $this->extractHtmlError($parsed['rawBody']) : null;

            if (\is_string($htmlError) && \preg_match('/token/i', $htmlError) === 1) {
                $this->logger->warning('flexpay.card.create_payment.retry_token_format', [
                    'paymentId' => $payment->getId(),
                    'reference' => $payment->getReference(),
                    'httpStatus' => $statusCode,
                    'contentType' => $parsed['contentType'],
                    'htmlError' => $htmlError,
                ]);

                $payloadRetry = $payload;
                $payloadRetry['authorization'] = $tokenNoBearer;

                try {
                    $response = $this->client->request('POST', $cardPayUrl, [
                        'headers' => [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                            'Authorization' => 'Bearer ' . $tokenNoBearer,
                        ],
                        'body' => \http_build_query($payloadRetry, '', '&', \PHP_QUERY_RFC3986),
                    ]);
                    $statusCode = $response->getStatusCode();
                } catch (\Throwable $e) {
                    $this->logger->error('flexpay.card.create_payment.exception', [
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

                $parsed = $this->decodeJsonResponse($response);
                $data = $parsed['data'];
            }
        }

        if (null === $data) {
            $htmlTitle = null;
            $htmlError = null;
            $htmlRedirectUrl = null;

            if (\is_string($parsed['rawBody']) && \is_string($parsed['contentType']) && \str_starts_with(\strtolower($parsed['contentType']), 'text/html')) {
                $htmlTitle = $this->extractHtmlTitle($parsed['rawBody']);
                $htmlError = $this->extractHtmlError($parsed['rawBody']);
                $htmlRedirectUrl = $this->extractHtmlRedirectUrl($parsed['rawBody']);
            }

            if (null === $htmlError && null !== $htmlTitle) {
                $normalizedTitle = \strtolower($htmlTitle);
                if (\str_contains($normalizedTitle, 'card payment') && \str_contains($normalizedTitle, 'flexpay')) {
                    $this->logger->info('flexpay.card.create_payment.html_page', [
                        'paymentId' => $payment->getId(),
                        'reference' => $payment->getReference(),
                        'httpStatus' => $statusCode,
                        'contentType' => $parsed['contentType'],
                        'htmlTitle' => $htmlTitle,
                        'htmlRedirectUrl' => $htmlRedirectUrl,
                    ]);

                    return new GatewayResponse(
                        success: true,
                        transactionId: null,
                        status: 'HTML',
                        message: 'HTML payment page returned by FlexPay.',
                        raw: [
                            'httpStatus' => $statusCode,
                            'contentType' => $parsed['contentType'],
                            'htmlTitle' => $htmlTitle,
                            'htmlRedirectUrl' => $htmlRedirectUrl,
                        ],
                    );
                }
            }

            $this->logger->error('flexpay.card.create_payment.invalid_json', [
                'paymentId' => $payment->getId(),
                'reference' => $payment->getReference(),
                'httpStatus' => $statusCode,
                'contentType' => $parsed['contentType'],
                'bodyPreview' => $parsed['bodyPreview'],
                'htmlTitle' => $htmlTitle,
                'htmlError' => $htmlError,
                'htmlRedirectUrl' => $htmlRedirectUrl,
            ]);

            return new GatewayResponse(
                success: false,
                transactionId: null,
                status: null,
                message: $htmlError ?: 'Invalid JSON response from FlexPay card endpoint.',
                raw: [
                    'httpStatus' => $statusCode,
                    'contentType' => $parsed['contentType'],
                    'bodyPreview' => $parsed['bodyPreview'],
                    'htmlTitle' => $htmlTitle,
                    'htmlError' => $htmlError,
                    'htmlRedirectUrl' => $htmlRedirectUrl,
                ],
            );
        }

        $this->logger->info('flexpay.card.create_payment.response', [
            'paymentId' => $payment->getId(),
            'reference' => $payment->getReference(),
            'httpStatus' => $statusCode,
            'code' => $data['code'] ?? null,
            'orderNumber' => $data['orderNumber'] ?? null,
            'url' => $data['url'] ?? null,
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

    public function buildCardPaymentForm(string $paymentId, string $paymentReference, string $ticketRef, string $amount, string $currency): array
    {
        $token = \trim($this->token);
        $token = \trim($token, "\"'`");
        $tokenNoBearer = \preg_replace('/^\s*Bearer\s+/i', '', $token) ?? $token;
        $authorization = 'Bearer ' . $tokenNoBearer;

        $cardPayUrl = $this->normalizeUrl($this->cardPayUrl);
        $callbackUrl = $this->normalizeUrl($this->callbackUrl);

        $approveUrl = $this->expandUrlTemplate(
            $this->normalizeUrl($this->cardApproveUrl),
            [
                'ref' => $ticketRef,
                'reason' => '',
            ]
        );
        $cancelUrl = $this->expandUrlTemplate(
            $this->normalizeUrl($this->cardCancelUrl),
            [
                'ref' => $ticketRef,
                'reason' => '',
            ]
        );
        $declineUrl = $this->expandUrlTemplate(
            $this->normalizeUrl($this->cardDeclineUrl),
            [
                'ref' => $ticketRef,
                'reason' => '',
            ]
        );

        $cardReference = \substr('OKP-' . $paymentId, 0, 25);

        return [
            'action' => $cardPayUrl,
            'fields' => [
                'authorization' => $authorization,
                'merchant' => $this->merchantId,
                'reference' => $cardReference,
                'amount' => $amount,
                'currency' => $currency,
                'description' => 'Payment ' . $paymentReference,
                'paymentWay' => 'card',
                'type' => 'card',
                'callback_url' => $callbackUrl,
                'approve_url' => $approveUrl,
                'cancel_url' => $cancelUrl,
                'decline_url' => $declineUrl,
            ],
        ];
    }

    private function decodeJsonResponse(ResponseInterface $response): array
    {
        $headers = $response->getHeaders(false);
        $contentType = $headers['content-type'][0] ?? null;

        $rawBody = $response->getContent(false);
        $preview = \substr($rawBody, 0, 2000);

        $data = \json_decode($rawBody, true);
        if (!\is_array($data) || \JSON_ERROR_NONE !== \json_last_error()) {
            return [
                'data' => null,
                'contentType' => \is_string($contentType) ? $contentType : null,
                'bodyPreview' => $preview,
                'rawBody' => $rawBody,
            ];
        }

        return [
            'data' => $data,
            'contentType' => \is_string($contentType) ? $contentType : null,
            'bodyPreview' => null,
            'rawBody' => null,
        ];
    }

    private function extractHtmlTitle(string $html): ?string
    {
        if (\preg_match('/<title>\s*(.*?)\s*<\/title>/is', $html, $m) !== 1) {
            return null;
        }

        $title = \strip_tags((string) $m[1]);
        $title = \trim(\preg_replace('/\s+/', ' ', $title) ?? '');

        return '' === $title ? null : $title;
    }

    private function extractHtmlError(string $html): ?string
    {
        if (\preg_match('/<div[^>]*class="[^"]*form-error[^"]*"[^>]*>.*?<li>\s*(.*?)\s*<\/li>/is', $html, $m) === 1) {
            $msg = \strip_tags((string) $m[1]);
            $msg = \trim(\preg_replace('/\s+/', ' ', $msg) ?? '');

            return '' === $msg ? null : $msg;
        }

        if (\preg_match('/<div[^>]*class="[^"]*alert[^"]*alert-warning[^"]*"[^>]*>\s*(.*?)\s*<\/div>/is', $html, $m) === 1) {
            $msg = \strip_tags((string) $m[1]);
            $msg = \trim(\preg_replace('/\s+/', ' ', $msg) ?? '');

            return '' === $msg ? null : $msg;
        }

        if (\preg_match('/Votre token est vide/is', $html) === 1) {
            return 'Votre token est vide';
        }

        if (\preg_match('/token.*vide/is', $html) === 1) {
            return 'Token invalide ou vide';
        }

        return null;
    }

    private function extractHtmlRedirectUrl(string $html): ?string
    {
        if (\preg_match('/<meta[^>]+http-equiv=["\']refresh["\'][^>]+content=["\'][^"\']*url=([^"\']+)["\']/i', $html, $m) === 1) {
            $url = \trim((string) $m[1]);
            return '' === $url ? null : $url;
        }

        if (\preg_match('/window\.location\s*=\s*[\'"]([^\'"]+)[\'"]/i', $html, $m) === 1) {
            $url = \trim((string) $m[1]);
            return '' === $url ? null : $url;
        }

        if (\preg_match('/<form[^>]+action=["\']([^"\']+)["\']/i', $html, $m) === 1) {
            $url = \trim((string) $m[1]);
            return '' === $url ? null : $url;
        }

        return null;
    }

    private function buildCardReference(Payment $payment): string
    {
        $reference = $payment->getId();

        if (null !== $reference && '' !== \trim($reference)) {
            $reference = 'OKP-' . $reference;
        } else {
            $fallback = (string) $payment->getReference();
            $fallback = \preg_replace('/[^A-Za-z0-9_-]+/', '', $fallback) ?: 'OKP';
            $reference = 'OKP-' . $fallback;
        }

        $reference = \preg_replace('/[^A-Za-z0-9_-]+/', '', $reference) ?: 'OKP';

        return \substr($reference, 0, 25);
    }

    private function normalizeUrl(string $url): string
    {
        $url = \trim($url);
        $url = \trim($url, "\"'`");
        $url = (string) \preg_replace('/\s+/', '', $url);
        $url = (string) \preg_replace('/[\x00-\x1F\x7F]+/', '', $url);
        $url = (string) \preg_replace('/[\p{Cf}]/u', '', $url);
        $url = \rtrim($url, "\\");

        if ('' === $url) {
            return $url;
        }

        if (\str_contains($url, '{') || \str_contains($url, '}')) {
            return $url;
        }

        if (false === \filter_var($url, \FILTER_VALIDATE_URL)) {
            $this->logger->error('flexpay.url.invalid', [
                'url' => $url,
                'hex' => \bin2hex($url),
            ]);
        }

        return $url;
    }

    private function expandUrlTemplate(string $url, array $vars): string
    {
        if ('' === $url) {
            return $url;
        }

        $expanded = $url;
        foreach ($vars as $k => $v) {
            $expanded = \str_replace('{' . (string) $k . '}', \rawurlencode((string) $v), $expanded);
        }

        return $expanded;
    }

    public function checkStatus(string $transactionId): GatewayResponse
    {
        $authorization = \str_starts_with($this->token, 'Bearer ')
            ? $this->token
            : 'Bearer ' . $this->token;

        $checkStatusUrl = $this->normalizeUrl($this->checkStatusUrl);

        $url = \str_contains($checkStatusUrl, '%s')
            ? \sprintf($checkStatusUrl, $transactionId)
            : \rtrim($checkStatusUrl, '/') . '/' . \rawurlencode($transactionId);

        $this->logger->info('flexpay.check_status.request', [
            'transactionId' => $transactionId,
        ]);

        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'Authorization' => $authorization,
                ],
            ]);
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

        $success = \in_array($normalizedStatus, ['SUCCESS', 'PAID', '0', 0], true);

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

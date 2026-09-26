<?php

namespace App\Service\Agency;

use App\Contract\AgencySmsSenderInterface;
use App\Exception\UnprocessableEntityException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Dream Digital / ASMSC SendSMS adapter (transactional).
 *
 * @see API SendSMS — POST {baseUrl}/api/SendSMS
 */
final class DreamDigitalSmsSender implements AgencySmsSenderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $baseUrl,
        private string $apiId,
        private string $apiPassword,
        private string $senderId,
        private string $smsType = 'T',
        private string $encoding = 'T',
    ) {
    }

    public function send(string $toPhone, string $message): string
    {
        $phone = $this->normalizePhone($toPhone);
        $base = rtrim($this->baseUrl, '/');
        if ('' === $base || '' === $this->apiId || '' === $this->apiPassword) {
            throw new UnprocessableEntityException('Dream Digital SMS is not configured (BASE_URL / API_ID / API_PASSWORD).');
        }
        $uid = sprintf('okapi-%s', bin2hex(random_bytes(6)));
        $url = $base.'/api/SendSMS';

        $payload = [
            'api_id' => $this->apiId,
            'api_password' => $this->apiPassword,
            'sms_type' => $this->smsType,
            'encoding' => $this->encoding,
            'sender_id' => $this->senderId,
            'phonenumber' => $phone,
            'textmessage' => $message,
            'uid' => $uid,
        ];

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => $payload,
                'timeout' => 20,
            ]);
            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->error('Dream Digital SMS HTTP error', [
                'to' => $phone,
                'error' => $e->getMessage(),
            ]);
            throw new UnprocessableEntityException('SMS gateway unavailable: '.$e->getMessage());
        }

        $status = (string) ($data['status'] ?? '');
        $messageId = isset($data['message_id']) ? (string) $data['message_id'] : '';
        $remarks = (string) ($data['remarks'] ?? '');

        if ($statusCode >= 400 || 'S' !== strtoupper($status)) {
            $this->logger->error('Dream Digital SMS rejected', [
                'to' => $phone,
                'httpStatus' => $statusCode,
                'status' => $status,
                'remarks' => $remarks,
                'body' => $data,
            ]);
            throw new UnprocessableEntityException(
                '' !== $remarks ? $remarks : 'SMS submission failed.'
            );
        }

        $smsMessageId = '' !== $messageId ? 'DD-'.$messageId : 'DD-'.$uid;
        $this->logger->info('Dream Digital SMS submitted', [
            'smsMessageId' => $smsMessageId,
            'to' => $phone,
            'uid' => $uid,
            'remarks' => $remarks,
        ]);

        return $smsMessageId;
    }

    /**
     * Dream Digital expects digits only: country code + number, no "+".
     */
    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (preg_match('/^0\d{8,12}$/', $digits)) {
            $digits = '243'.substr($digits, 1);
        }
        if (!preg_match('/^\d{8,15}$/', $digits)) {
            throw new UnprocessableEntityException('Invalid phone number for SMS gateway.');
        }

        return $digits;
    }
}

<?php

namespace App\Service\Agency;

use App\Contract\AgencySmsSenderInterface;
use Psr\Log\LoggerInterface;

/**
 * V1 SMS adapter — logs and returns a synthetic message id (Africa's Talking later).
 */
final class LoggingAgencySmsSender implements AgencySmsSenderInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function send(string $toPhone, string $message): string
    {
        $id = sprintf('SMS-%s', strtoupper(bin2hex(random_bytes(4))));
        $this->logger->info('Agency SMS queued (stub)', [
            'smsMessageId' => $id,
            'to' => $toPhone,
            'message' => $message,
        ]);

        return $id;
    }
}

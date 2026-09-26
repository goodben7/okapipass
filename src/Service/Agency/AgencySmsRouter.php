<?php

namespace App\Service\Agency;

use App\Contract\AgencySmsSenderInterface;

/**
 * Routes SMS to Dream Digital when configured, otherwise the logging stub.
 */
final class AgencySmsRouter implements AgencySmsSenderInterface
{
    public function __construct(
        private DreamDigitalSmsSender $dreamDigital,
        private LoggingAgencySmsSender $logging,
        private bool $enabled,
    ) {
    }

    public function send(string $toPhone, string $message): string
    {
        if ($this->enabled) {
            return $this->dreamDigital->send($toPhone, $message);
        }

        return $this->logging->send($toPhone, $message);
    }
}

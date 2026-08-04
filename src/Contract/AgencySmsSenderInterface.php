<?php

namespace App\Contract;

interface AgencySmsSenderInterface
{
    /**
     * @return string smsMessageId
     */
    public function send(string $toPhone, string $message): string;
}

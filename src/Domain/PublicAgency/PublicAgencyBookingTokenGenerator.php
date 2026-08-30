<?php

namespace App\Domain\PublicAgency;

final class PublicAgencyBookingTokenGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}

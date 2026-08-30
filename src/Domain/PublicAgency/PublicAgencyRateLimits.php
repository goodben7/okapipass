<?php

namespace App\Domain\PublicAgency;

final class PublicAgencyRateLimits
{
    public const int WINDOW_SECONDS = 60;

    public const int BOOKING_CREATE_LIMIT = 10;

    public const int PAYMENT_INIT_LIMIT = 5;
}

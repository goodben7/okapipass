<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;

final class TravelerTicketShareResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public ?string $ticketId = null,
        public ?string $toPhone = null,
        public ?string $smsMessageId = null,
        public ?string $shareUrl = null,
        public ?string $whatsappUrl = null,
        public ?string $shareToken = null,
    ) {
    }
}

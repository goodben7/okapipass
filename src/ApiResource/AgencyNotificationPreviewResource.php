<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\Agency\NotificationPreviewDto;
use App\State\Agency\NotificationPreviewProcessor;

#[ApiResource(
    shortName: 'AgencyNotificationPreview',
    operations: [
        new Post(
            uriTemplate: '/agency/notifications/preview',
            security: 'is_granted("ROLE_PARTNER")',
            input: NotificationPreviewDto::class,
            output: AgencyNotificationPreviewResource::class,
            processor: NotificationPreviewProcessor::class,
            read: false,
        ),
    ]
)]
class AgencyNotificationPreviewResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $smsText,
        public string $whatsappUrl,
        public string $whatsappText,
    ) {
    }
}

<?php

namespace App\Dto\Agency;

use App\Entity\AgencyWebhookSubscription;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateAgencyWebhookDto
{
    public function __construct(
        #[Assert\Url]
        public ?string $url = null,

        #[Assert\Length(max: 120)]
        public ?string $secret = null,

        /** @var list<string>|null */
        #[Assert\All([
            new Assert\Choice(callback: [AgencyWebhookSubscription::class, 'getEventsAsList']),
        ])]
        public ?array $events = null,

        public ?bool $active = null,
    ) {
    }
}

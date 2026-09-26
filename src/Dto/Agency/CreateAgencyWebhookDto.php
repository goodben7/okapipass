<?php

namespace App\Dto\Agency;

use App\Entity\AgencyWebhookSubscription;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateAgencyWebhookDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Url]
        public ?string $url = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $secret = null,

        /** @var list<string>|null */
        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        #[Assert\All([
            new Assert\Choice(callback: [AgencyWebhookSubscription::class, 'getEventsAsList']),
        ])]
        public ?array $events = null,

        public ?bool $active = true,
    ) {
    }
}

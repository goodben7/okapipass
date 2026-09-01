<?php

namespace App\Dto\Public;

use Symfony\Component\Validator\Constraints as Assert;

final class CreatePublicAgencyBookingGroupDto
{
    /**
     * @param list<PublicAgencyPassengerLineDto> $passengers
     */
    public function __construct(
        #[Assert\NotBlank]
        public ?string $offerId = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $travelDate = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $groupName = null,

        /** Optional contact phone for the group (payer or organizer). */
        #[Assert\Length(max: 20)]
        public ?string $contactPhone = null,

        #[Assert\NotBlank]
        #[Assert\Count(min: 2, max: 20)]
        #[Assert\Valid]
        public array $passengers = [],
    ) {
    }
}

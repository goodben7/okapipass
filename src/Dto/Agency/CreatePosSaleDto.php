<?php

namespace App\Dto\Agency;

use App\Entity\AgencyPayment;
use Symfony\Component\Validator\Constraints as Assert;

final class CreatePosSaleDto
{
    public function __construct(
        #[Assert\NotBlank]
        public ?string $session = null,

        #[Assert\NotBlank]
        public ?string $offer = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public ?string $passengerName = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 60)]
        public ?string $passengerId = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 20)]
        public ?string $passengerPhone = null,

        #[Assert\Length(max: 10)]
        public ?string $seatNumber = null,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $travelDate = null,

        #[Assert\Length(max: 40)]
        public ?string $okapiPassRef = null,

        #[Assert\Choice(callback: [AgencyPayment::class, 'getMethodsAsList'])]
        public ?string $method = AgencyPayment::METHOD_CASH,

        #[Assert\Length(max: 40)]
        public ?string $promoCode = null,

        #[Assert\PositiveOrZero]
        public ?int $discountAmount = null,

        public ?bool $sendSms = false,

        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/')]
        public ?string $passengerDateOfBirth = null,

        #[Assert\Length(max: 16)]
        public ?string $escortTicketId = null,

        #[Assert\Length(max: 120)]
        public ?string $escortName = null,

        public ?bool $insuranceOpted = false,
    ) {
    }
}

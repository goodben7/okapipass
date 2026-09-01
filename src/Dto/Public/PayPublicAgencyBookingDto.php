<?php

namespace App\Dto\Public;

use App\Entity\AgencyPayment;
use Symfony\Component\Validator\Constraints as Assert;

final class PayPublicAgencyBookingDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: [AgencyPayment::METHOD_MOBILE_MONEY, AgencyPayment::METHOD_CARD])]
        public ?string $method = null,

        /** Mobile Money phone of the person paying. Defaults to passengerPhone when omitted. */
        #[Assert\Length(max: 20)]
        public ?string $payerPhone = null,
    ) {
    }
}

<?php

namespace App\Dto\Agency;

use App\Entity\AgencyBaggageExcess;
use Symfony\Component\Serializer\Attribute\Groups;

final class RecordBaggageResult
{
    public function __construct(
        #[Groups(['agency_baggage_excess:get', 'agency_ticket:get'])]
        public AgencyBaggageExcess $excess,
        #[Groups(['agency_baggage_excess:get', 'agency_ticket:get'])]
        public int $amount,
        #[Groups(['agency_baggage_excess:get', 'agency_ticket:get'])]
        public int $excessKg,
    ) {
    }
}

<?php

namespace App\Dto\Agency;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Response for assign / reassign / unassign trip transport.
 *
 * @phpstan-type TransportSnapshot array{id: string, label: string, plateNumber: string, capacity: int}|null
 * @phpstan-type DriverSnapshot array{id: string, name: string}|null
 * @phpstan-type RemappedSeat array{ticketId: string, from: string, to: string}
 */
final class TripAssignResultDto
{
    /**
     * @param list<string>         $warnings
     * @param TransportSnapshot    $transport
     * @param DriverSnapshot       $driver
     * @param list<RemappedSeat>   $remappedSeats
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        #[Groups(['trip_assign:get'])]
        public string $tripId,
        #[Groups(['trip_assign:get'])]
        public string $embarkationId,
        #[Groups(['trip_assign:get'])]
        public string $status,
        #[Groups(['trip_assign:get'])]
        public ?array $transport,
        #[Groups(['trip_assign:get'])]
        public ?array $driver,
        #[Groups(['trip_assign:get'])]
        public array $warnings = [],
        #[Groups(['trip_assign:get'])]
        public ?string $assignmentId = null,
        #[Groups(['trip_assign:get'])]
        public array $remappedSeats = [],
    ) {
    }
}

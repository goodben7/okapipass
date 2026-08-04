<?php

namespace App\Domain\Agency;

use App\Entity\AgencyTransport;
use App\Exception\UnprocessableEntityException;

/**
 * Builds seat layouts from transport kind + capacity (spec §5.1).
 */
final class SeatLayoutBuilder
{
    /**
     * @return array{
     *     kind: string,
     *     rows: int,
     *     columns: list<string>,
     *     aisleAfter: int,
     *     seatIds: list<string>,
     *     capacity: int
     * }
     */
    public function build(string $kind, int $capacity): array
    {
        if ($capacity < 1) {
            throw new UnprocessableEntityException('Capacity must be at least 1.');
        }

        $columns = match ($kind) {
            AgencyTransport::KIND_BUS, AgencyTransport::KIND_COASTER => ['A', 'B', 'C', 'D'],
            AgencyTransport::KIND_MINIBUS, AgencyTransport::KIND_VAN => ['A', 'B', 'C'],
            default => throw new UnprocessableEntityException(sprintf('Invalid transport kind "%s".', $kind)),
        };

        $colsPerRow = \count($columns);
        $aisleAfter = 1; // A B | C D  or  A B | C
        $rows = (int) ceil($capacity / $colsPerRow);
        $seatIds = [];

        for ($row = 1; $row <= $rows; ++$row) {
            foreach ($columns as $col) {
                if (\count($seatIds) >= $capacity) {
                    break 2;
                }
                $seatIds[] = sprintf('%02d%s', $row, $col);
            }
        }

        return [
            'kind' => $kind,
            'rows' => $rows,
            'columns' => $columns,
            'aisleAfter' => $aisleAfter,
            'seatIds' => $seatIds,
            'capacity' => $capacity,
        ];
    }

    public function isValidSeat(string $kind, int $capacity, string $seatNumber): bool
    {
        $normalized = strtoupper(trim($seatNumber));
        $layout = $this->build($kind, $capacity);

        return \in_array($normalized, $layout['seatIds'], true);
    }
}

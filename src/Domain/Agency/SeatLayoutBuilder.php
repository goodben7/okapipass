<?php

namespace App\Domain\Agency;

use App\Entity\AgencyTransport;
use App\Exception\UnprocessableEntityException;

/**
 * Builds seat layouts from transport kind + capacity (spec §5.1).
 */
final class SeatLayoutBuilder
{
    public const string CLASS_PREMIUM = 'PREMIUM';
    public const string CLASS_STANDARD = 'STANDARD';

    /**
     * @return array{
     *     kind: string,
     *     rows: int,
     *     columns: list<string>,
     *     aisleAfter: int,
     *     seatIds: list<string>,
     *     seatClasses: array<string, string>,
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
        $aisleAfter = 1;
        $rows = (int) ceil($capacity / $colsPerRow);
        $seatIds = [];
        $seatClasses = [];

        for ($row = 1; $row <= $rows; ++$row) {
            foreach ($columns as $col) {
                if (\count($seatIds) >= $capacity) {
                    break 2;
                }
                $seatId = sprintf('%02d%s', $row, $col);
                $seatIds[] = $seatId;
                $seatClasses[$seatId] = 1 === $row ? self::CLASS_PREMIUM : self::CLASS_STANDARD;
            }
        }

        return [
            'kind' => $kind,
            'rows' => $rows,
            'columns' => $columns,
            'aisleAfter' => $aisleAfter,
            'seatIds' => $seatIds,
            'seatClasses' => $seatClasses,
            'capacity' => $capacity,
        ];
    }

    public function seatClassFor(string $seatNumber): string
    {
        $normalized = strtoupper(trim($seatNumber));
        if (preg_match('/^0*1[A-Z]$/', $normalized)) {
            return self::CLASS_PREMIUM;
        }

        return self::CLASS_STANDARD;
    }

    public function isValidSeat(string $kind, int $capacity, string $seatNumber): bool
    {
        $normalized = strtoupper(trim($seatNumber));
        $layout = $this->build($kind, $capacity);

        return \in_array($normalized, $layout['seatIds'], true);
    }
}

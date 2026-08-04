<?php

namespace App\Domain\Agency;

use App\Exception\UnprocessableEntityException;

/**
 * Parses FPT declaration CSV (spec §6.8).
 */
final class DeclarationCsvParser
{
    private const array ALIASES = [
        'referenceBillet' => ['referencebillet', 'reference', 'ref', 'billet'],
        'date' => ['date', 'traveldate', 'date_voyage'],
        'passengerName' => ['passengername', 'passager', 'name'],
        'passengerId' => ['passengerid', 'piece', 'id_document'],
        'origin' => ['origin'],
        'destination' => ['destination'],
        'ticketPrice' => ['ticketprice', 'prix', 'price'],
        'currency' => ['currency', 'devise'],
        'okapiPassRef' => ['okapipassref', 'passref', 'pass'],
        'hasExistingPass' => ['hasexistingpass'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function parse(string $content): array
    {
        $content = trim($content);
        if ('' === $content) {
            throw new UnprocessableEntityException('CSV content is empty.');
        }

        if (\strlen($content) > DeclarationCsvLimits::MAX_CONTENT_BYTES) {
            throw new UnprocessableEntityException(sprintf(
                'CSV content exceeds maximum size of %d bytes.',
                DeclarationCsvLimits::MAX_CONTENT_BYTES
            ));
        }

        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $l): bool => '' !== trim($l)));
        if (\count($lines) < 2) {
            throw new UnprocessableEntityException('CSV must contain a header and at least one data row.');
        }

        $dataRowCount = \count($lines) - 1;
        if ($dataRowCount > DeclarationCsvLimits::MAX_ROWS) {
            throw new UnprocessableEntityException(sprintf(
                'CSV exceeds maximum of %d data rows (%d found).',
                DeclarationCsvLimits::MAX_ROWS,
                $dataRowCount
            ));
        }

        $delimiter = str_contains($lines[0], ';') ? ';' : ',';
        $header = str_getcsv(array_shift($lines), $delimiter, '"', '\\');
        $map = $this->mapHeader($header);

        $required = ['referenceBillet', 'date', 'passengerName', 'passengerId', 'origin', 'destination', 'ticketPrice'];
        foreach ($required as $key) {
            if (!isset($map[$key])) {
                throw new UnprocessableEntityException(sprintf('CSV missing required column for "%s".', $key));
            }
        }

        $rows = [];
        foreach ($lines as $line) {
            $cols = str_getcsv($line, $delimiter, '"', '\\');
            $row = [];
            foreach ($map as $canon => $idx) {
                $row[$canon] = $cols[$idx] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<string|null> $header
     *
     * @return array<string, int>
     */
    private function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $i => $col) {
            $norm = strtolower(trim((string) $col));
            $norm = str_replace([' ', '-'], ['_', '_'], $norm);
            $norm = str_replace('_', '', $norm);
            foreach (self::ALIASES as $canon => $aliases) {
                if (\in_array($norm, $aliases, true)) {
                    $map[$canon] = $i;
                    break;
                }
            }
        }

        return $map;
    }
}

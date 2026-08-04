<?php

namespace App\Tests\Unit\Domain\Agency;

use App\Domain\Agency\DeclarationCsvLimits;
use App\Domain\Agency\DeclarationCsvParser;
use App\Exception\UnprocessableEntityException;
use PHPUnit\Framework\TestCase;

final class DeclarationCsvParserTest extends TestCase
{
    public function testRejectsTooManyRows(): void
    {
        $header = 'referenceBillet;date;passengerName;passengerId;origin;destination;ticketPrice;currency';
        $row = 'VP-1;2026-08-05;Jean;CD-1;Kinshasa;Matadi;10000;CDF';
        $lines = array_merge([$header], array_fill(0, DeclarationCsvLimits::MAX_ROWS + 1, $row));

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('CSV exceeds maximum of');

        (new DeclarationCsvParser())->parse(implode("\n", $lines));
    }

    public function testRejectsOversizedContent(): void
    {
        $payload = str_repeat('a', DeclarationCsvLimits::MAX_CONTENT_BYTES + 1);

        $this->expectException(UnprocessableEntityException::class);
        $this->expectExceptionMessage('CSV content exceeds maximum size');

        (new DeclarationCsvParser())->parse($payload);
    }

    public function testParsesValidCsv(): void
    {
        $csv = implode("\n", [
            'referenceBillet;date;passengerName;passengerId;origin;destination;ticketPrice;currency',
            'VP-1;2026-08-05;Jean;CD-1;Kinshasa;Matadi;10000;CDF',
        ]);

        $rows = (new DeclarationCsvParser())->parse($csv);

        self::assertCount(1, $rows);
        self::assertSame('VP-1', $rows[0]['referenceBillet']);
    }
}

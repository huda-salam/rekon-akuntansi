<?php

namespace Tests\Unit;

use App\Services\LedgerParser;
use PHPUnit\Framework\TestCase;

class LedgerParserTest extends TestCase
{
    public function test_it_converts_excel_serial_date_to_iso_date(): void
    {
        $parser = new LedgerParser();
        $method = new \ReflectionMethod($parser, 'date');
        $method->setAccessible(true);

        $this->assertSame('2026-01-01', $method->invoke($parser, 46023));
        $this->assertSame('2026-01-01', $method->invoke($parser, '01/01/2026'));
    }

    public function test_it_extracts_account_code_from_uraian(): void
    {
        $parser = new LedgerParser();
        $method = new \ReflectionMethod($parser, 'accountCode');
        $method->setAccessible(true);

        $row = [
            'Tanggal' => '01/01/2026',
            'Uraian' => '5.1.01.01.001.00001 - Belanja Barang dan Jasa',
            'Debit' => 1000,
        ];
        $headers = array_keys($row);
        $values = array_values($row);

        $this->assertSame(
            '5.1.01.01.001.00001',
            $method->invoke($parser, $values, $headers)
        );
    }
    public function test_it_rejects_populated_but_invalid_dates(): void
    {
        $parser = new LedgerParser();
        $method = new \ReflectionMethod($parser, 'date');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($parser, 'not-a-date'));
    }

    public function test_it_identifies_ledger_summary_row_as_footer(): void
    {
        $parser = new LedgerParser();
        $method = new \ReflectionMethod($parser, 'isFooterRow');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($parser, ['JUMLAH', null, null, null, null, 100, 0, 100]));
        $this->assertTrue($method->invoke($parser, ['Grand Total', null]));
        $this->assertFalse($method->invoke($parser, ['07/09/2026', '5.1.01.01.001.00001 - Belanja']));
    }

}

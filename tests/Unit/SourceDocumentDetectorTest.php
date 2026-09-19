<?php

namespace Tests\Unit;

use App\Services\SourceDocumentDetector;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SourceDocumentDetectorTest extends TestCase
{
    public function test_it_detects_expenditure_reconciliation_from_cell_content_not_sheet_name(): void
    {
        $sheets = new Collection([
            'Worksheet' => new Collection([
                ['REKAPITULASI REALISASI (SP2D, SPJ, STS)'],
                ['No', 'Kode', 'SKPD', 'SP2D LS', 'SP2D UP/GU', 'SP2D TU', 'SP2D KKPD', 'TOTAL SP2D', 'SPJ LS', 'SPJ UP/GU', 'SPJ TU', 'SPJ KKPD', 'TOTAL SPJ', 'STS UP/GU', 'STS TU', 'CP LS', 'CP UP/GU', 'CP TU', 'TOTAL STS', 'KAS SIPD', 'KAS BANK', 'KAS TUNAI', 'SELISIH', 'STATUS'],
            ]),
        ]);

        $result = app(SourceDocumentDetector::class)->detect($sheets);

        $this->assertSame('expenditure_reconciliation', $result['type']);
        $this->assertSame('RKUD', $result['category']);
        $this->assertSame(1.0, $result['confidence']);
        $this->assertNotEmpty($result['evidence']);
    }

    public function test_it_detects_financial_statement_from_content(): void
    {
        $sheets = new Collection([
            'Worksheet' => new Collection([
                ['PEMERINTAHAN KABUPATEN KEDIRI'],
                ['KECAMATAN PARE'],
                ['LAPORAN REALISASI ANGGARAN PENDAPATAN DAN BELANJA DAERAH'],
            ]),
        ]);

        $result = app(SourceDocumentDetector::class)->detect($sheets);

        $this->assertSame('financial_statement', $result['type']);
        $this->assertSame('ACCOUNTING', $result['category']);
    }

    public function test_unknown_workbook_is_not_claimed_as_supported(): void
    {
        $sheets = new Collection([
            'Worksheet' => new Collection([
                ['random content'],
            ]),
        ]);

        $result = app(SourceDocumentDetector::class)->detect($sheets);

        $this->assertSame('unknown', $result['type']);
        $this->assertSame(0.0, $result['confidence']);
    }
}

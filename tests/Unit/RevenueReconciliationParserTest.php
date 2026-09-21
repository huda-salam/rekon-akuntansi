<?php

namespace Tests\Unit;

use App\Models\AccountingYear;
use App\Models\FinancialFact;
use App\Models\Skpd;
use App\Models\SourceDocument;
use App\Models\SourceRecord;
use App\Services\RevenueReconciliationParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RevenueReconciliationParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_preserves_month_and_distinguishes_two_sisa_rows(): void
    {
        $year = AccountingYear::create([
            'year' => 2026,
            'status' => 'active',
        ]);

        $skpd = Skpd::create([
            'code' => 'DINKES',
            'name' => 'Dinas Kesehatan',
            'is_active' => true,
        ]);

        $document = SourceDocument::create([
            'accounting_year_id' => $year->id,
            'uploaded_by' => 1,
            'original_filename' => 'rekonsiliasi pendapatan.xlsx',
            'document_type' => 'revenue_reconciliation',
            'source_category' => 'reconciliation',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'checksum_sha256' => hash('sha256', uniqid('', true)),
            'file_path' => 'source-documents/test.xlsx',
            'file_size' => 1,
            'status' => 'IMPORTED',
        ]);

        $rows = collect([
            ['SKPD', '', 'Dinas Kesehatan'],
            ['JANUARI', 'FEBRUARI'],
            ['BKU PENERIMAAN MANUAL (SKPD)', 100, 200],
            ['Pendapatan NON RKUD', 30, 40],
            ['BLUD', 10, 20],
            ['JKN', 5, 6],
            ['BOS', 3, 4],
            ['BOK', 2, 3],
            ['STS (yg punya no. STS)', 90, 180],
            ['STBP (total semua, termasuk yg di STS kan)', 100, 200],
            ['Sisa', 10, 20],
            ['LPJ Penerimaan', 95, 190],
            ['LPJ Penyetoran', 90, 180],
            ['Sisa', 5, 10],
        ]);

        app(RevenueReconciliationParser::class)->parse(
            new Collection(['DINKES' => $rows]),
            $document,
            2026,
            1,
        );

        $facts = FinancialFact::query()->where('source_document_id', $document->id)->get();

        $this->assertSame(1, $facts->where('metric', 'sisa_stbp')->count());
        $this->assertSame(1, $facts->where('metric', 'sisa_lpj')->count());
        $this->assertSame(10.0, (float) $facts->firstWhere('metric', 'sisa_stbp')->value);
        $this->assertSame(5.0, (float) $facts->firstWhere('metric', 'sisa_lpj')->value);

        $derived = $facts->firstWhere('metric', 'pendapatan_non_rkud_derived');
        $this->assertNotNull($derived);
        $this->assertSame(20.0, (float) $derived->value);
        $this->assertSame(1, (int) $derived->month);
        $this->assertSame($skpd->id, $derived->skpd_id);
    }
}

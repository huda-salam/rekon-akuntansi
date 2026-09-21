<?php

namespace Tests\Feature;

use App\Models\AccountingYear;
use App\Models\Skpd;
use App\Models\SourceDocument;
use App\Services\ExpenditureReconciliationParser;
use App\Services\NormalizedWorkbookParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SourceIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_expenditure_parser_translates_rows_into_reported_and_derived_facts(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);
        $skpd = Skpd::create([
            'code' => '7.01.0.00.0.00.46.0000',
            'name' => 'Kecamatan Pare',
            'is_active' => true,
        ]);

        $document = SourceDocument::create([
            'accounting_year_id' => $year->id,
            'original_filename' => 'rekonsiliasi pengeluaran.xlsx',
            'document_type' => 'expenditure_reconciliation',
            'source_category' => 'RKUD',
            'checksum_sha256' => hash('sha256', 'fixture'),
            'status' => 'IMPORTED',
        ]);

        $rows = new Collection([
            ['REKAPITULASI REALISASI (SP2D, SPJ, STS)'],
            ['No','Kode','SKPD','SP2D LS','SP2D UP/GU','SP2D TU','SP2D KKPD','TOTAL SP2D','SPJ LS','SPJ UP/GU','SPJ TU','SPJ KKPD','TOTAL SPJ','STS UP/GU','STS TU','CP LS','CP UP/GU','CP TU','TOTAL STS','KAS SIPD','KAS BANK','KAS TUNAI','SELISIH','STATUS'],
            [46,$skpd->code,$skpd->name,100,20,0,0,120,100,15,0,0,115,0,0,0,0,0,0,5,5,0,0,'SUDAH'],
        ]);

        $count = app(ExpenditureReconciliationParser::class)->parse($rows, $document, 2026, 9);

        $this->assertSame(1, $count);

        $this->assertDatabaseHas('financial_facts', [
            'source_document_id' => $document->id,
            'month' => 9,
            'period' => '2026-09',
        ]);

        $this->assertDatabaseHas('source_records', [
            'source_document_id' => $document->id,
            'record_type' => 'expenditure_reconciliation_row',
        ]);

        $this->assertDatabaseHas('financial_facts', [
            'source_document_id' => $document->id,
            'skpd_id' => $skpd->id,
            'metric' => 'sp2d_ls',
            'value' => 100,
        ]);

        $this->assertDatabaseHas('financial_facts', [
            'source_document_id' => $document->id,
            'skpd_id' => $skpd->id,
            'metric' => 'total_sp2d_derived',
            'value' => 120,
        ]);

        $this->assertDatabaseHas('financial_facts', [
            'source_document_id' => $document->id,
            'skpd_id' => $skpd->id,
            'metric' => 'total_spj_derived',
            'value' => 115,
        ]);

        $this->assertDatabaseHas('financial_facts', [
            'source_document_id' => $document->id,
            'skpd_id' => $skpd->id,
            'metric' => 'selisih_kas_derived',
            'value' => 0,
        ]);
    }

    public function test_generic_parser_normalizes_supported_non_expenditure_workbook_rows_and_numeric_facts(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);

        $document = SourceDocument::create([
            'accounting_year_id' => $year->id,
            'original_filename' => 'neraca.xlsx',
            'document_type' => 'financial_statement',
            'source_category' => 'ACCOUNTING',
            'checksum_sha256' => hash('sha256', 'neraca-fixture'),
            'status' => 'IMPORTED',
        ]);

        $sheets = new Collection([
            'Neraca' => new Collection([
                ['NERACA'],
                ['KODE REKENING', 'URAIAN', 'SALDO'],
                ['1.01', 'Kas', 125000],
            ]),
        ]);

        $count = app(\App\Services\GenericWorkbookParser::class)->parse($sheets, $document, 2026, 9);

        $this->assertSame(1, $count);

        $this->assertDatabaseHas('source_records', [
            'source_document_id' => $document->id,
            'sheet_name' => 'Neraca',
            'source_row' => 3,
        ]);

        $this->assertDatabaseHas('financial_facts', [
            'source_document_id' => $document->id,
            'metric' => 'saldo',
            'value' => 125000,
            'month' => 9,
            'account_code' => '1.01',
        ]);
    }


    public function test_normalized_parser_preserves_lineage_for_ledger_and_statement_sources(): void
    {
        $year = AccountingYear::create(['year' => 2026, 'is_active' => true]);

        $document = SourceDocument::create([
            'accounting_year_id' => $year->id,
            'original_filename' => 'buku-besar-kecamatan-pare.xlsx',
            'document_type' => 'ledger',
            'source_category' => 'ACCOUNTING',
            'checksum_sha256' => hash('sha256', 'ledger-fixture'),
            'status' => 'IMPORTED',
        ]);

        $sheets = new Collection([
            'Buku Besar' => new Collection([
                ['PEMERINTAH KAB. KEDIRI'],
                ['Tanggal', 'Kode Rekening', 'Nama Rekening', 'Debit', 'Kredit'],
                ['2026-09-01', '5.1.01', 'Belanja', 100000, 25000],
            ]),
        ]);

        $count = app(NormalizedWorkbookParser::class)->parse($sheets, $document, 2026, 9);

        $this->assertSame(1, $count);

        $this->assertDatabaseHas('financial_facts', [
            'source_document_id' => $document->id,
            'metric' => 'debit',
            'value' => 100000,
            'month' => 9,
            'account_code' => '5.1.01',
        ]);

        $this->assertDatabaseHas('financial_facts', [
            'source_document_id' => $document->id,
            'metric' => 'kredit',
            'value' => 25000,
            'month' => 9,
            'account_code' => '5.1.01',
        ]);
    }

}

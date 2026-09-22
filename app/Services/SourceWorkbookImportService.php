<?php

namespace App\Services;

use App\Models\AccountingYear;
use App\Models\ImportBatch;
use App\Models\SourceDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class SourceWorkbookImportService
{
    public function __construct(
        private readonly SourceDocumentDetector $detector,
        private readonly ExpenditureReconciliationParser $expenditureParser,
        private readonly GenericWorkbookParser $genericParser,
        private readonly NormalizedWorkbookParser $normalizedParser,
        private readonly RevenueReconciliationParser $revenueParser,
        private readonly FinancialStatementParser $financialStatementParser,
        private readonly LedgerParser $ledgerParser,
        private readonly SourceWorkbookInspectionService $inspectionService,
        private readonly SourceFactQualityGate $qualityGate,
    ) {}

    public function execute(
        UploadedFile $file,
        int $year,
        int $userId,
        ?int $month = null,
        ?ImportBatch $batch = null,
    ): SourceDocument {
        $accountingYear = AccountingYear::query()->where('year', $year)->first();

        if (! $accountingYear) {
            throw ValidationException::withMessages([
                'year' => "Tahun anggaran {$year} belum tersedia.",
            ]);
        }

        if ($month !== null && ($month < 1 || $month > 12)) {
            throw ValidationException::withMessages([
                'month' => 'Bulan harus berada pada rentang 1 sampai 12.',
            ]);
        }

        $checksum = hash_file('sha256', $file->getRealPath());

        if (SourceDocument::query()->where('checksum_sha256', $checksum)->exists()) {
            throw ValidationException::withMessages([
                'file' => 'File yang sama sudah pernah diimpor.',
            ]);
        }

        $reader = IOFactory::createReaderForFile($file->getRealPath());
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());
        $sheets = $this->readSheets($spreadsheet);
        $detection = $this->detector->detect($sheets, $file->getClientOriginalName());

        $validation = $this->inspectionService->validation($detection['type'], (float) $detection['confidence'], $sheets);

        if ($validation['readiness'] !== 'READY') {
            $message = $validation['warnings'][0]
                ?? 'Workbook belum lolos source parser readiness gate.';

            throw ValidationException::withMessages([
                'file' => $message,
            ]);
        }

        $path = $file->storeAs(
            'source-documents',
            $checksum . '.' . strtolower($file->getClientOriginalExtension()),
            'local'
        );

        try {
            return DB::transaction(function () use (
                $file, $checksum, $accountingYear, $userId, $month,
                $sheets, $detection, $path, $batch
            ) {
                $document = SourceDocument::create([
                    'accounting_year_id' => $accountingYear->id,
                    'import_batch_id' => $batch?->id,
                    'uploaded_by' => $userId,
                    'original_filename' => $file->getClientOriginalName(),
                    'document_type' => $detection['type'],
                    'source_category' => $detection['category'],
                    'mime_type' => $file->getMimeType(),
                    'checksum_sha256' => $checksum,
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'status' => 'IMPORTED',
                    'metadata' => [
                        'sheets' => $sheets->keys()->values()->all(),
                        'detection' => $detection,
                        'requested_month' => $month,
                        'report_scope' => $this->reportScope($file->getClientOriginalName(), $detection['type']),
                    ],
                    'imported_at' => now(),
                ]);

                $count = $this->parse($detection['type'], $sheets, $document, $accountingYear->year, $month);

                $quality = $this->qualityGate->evaluate($document);

                if (! $quality['valid']) {
                    throw ValidationException::withMessages([
                        'file' => $quality['errors'],
                    ]);
                }

                $document->update([
                    'row_count' => $count,
                    'metadata' => array_merge($document->metadata ?? [], [
                        'quality_gate' => $quality,
                    ]),
                ]);

                return $document->fresh();
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    /**
     * @param array<int, UploadedFile> $files
     * @return array{batch: ImportBatch, imported: array<int, SourceDocument>, failed: array<int, array<string,mixed>>}
     */
    /**
     * Execute the full parser pipeline inside a transaction and roll it back.
     * This validates real source parsing without persisting documents, records,
     * or financial facts.
     *
     * @return array<string,mixed>
     */
    public function dryRun(string $path, int $year, int $userId, ?int $month = null): array
    {
        $accountingYear = AccountingYear::query()->where('year', $year)->first();

        if (! $accountingYear) {
            throw ValidationException::withMessages([
                'year' => "Tahun anggaran {$year} belum tersedia.",
            ]);
        }

        if ($month !== null && ($month < 1 || $month > 12)) {
            throw ValidationException::withMessages([
                'month' => 'Bulan harus berada pada rentang 1 sampai 12.',
            ]);
        }

        if (! is_file($path) || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)) {
            throw new \InvalidArgumentException("File workbook tidak valid: {$path}");
        }

        $checksum = hash_file('sha256', $path);
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheets = $this->readSheets($spreadsheet);
        $detection = $this->detector->detect($sheets, basename($path));
        $validation = $this->inspectionService->validation($detection['type'], (float) $detection['confidence'], $sheets);

        if ($validation['readiness'] !== 'READY') {
            throw ValidationException::withMessages([
                'file' => $validation['warnings'][0] ?? 'Workbook belum lolos source readiness gate.',
            ]);
        }

        DB::beginTransaction();

        try {
            $document = SourceDocument::create([
                'accounting_year_id' => $accountingYear->id,
                'import_batch_id' => null,
                'uploaded_by' => $userId,
                'original_filename' => basename($path),
                'document_type' => $detection['type'],
                'source_category' => $detection['category'],
                'mime_type' => mime_content_type($path) ?: 'application/octet-stream',
                'checksum_sha256' => $checksum,
                'file_path' => 'dry-run/' . $checksum,
                'file_size' => filesize($path),
                'status' => 'DRY_RUN',
                'metadata' => [
                    'sheets' => $sheets->keys()->values()->all(),
                    'detection' => $detection,
                    'requested_month' => $month,
                    'report_scope' => $this->reportScope(basename($path), $detection['type']),
                ],
                'imported_at' => now(),
            ]);

            $parsedRows = $this->parse($detection['type'], $sheets, $document, $accountingYear->year, $month);
            $quality = $this->qualityGate->evaluate($document);
            $recordCount = SourceRecord::query()->where('source_document_id', $document->id)->count();
            $factCount = \App\Models\FinancialFact::query()->where('source_document_id', $document->id)->count();

            $sampleFacts = \App\Models\FinancialFact::query()
                ->where('source_document_id', $document->id)
                ->orderBy('id')
                ->limit(10)
                ->get([
                    'source_record_id', 'fact_date', 'period', 'month', 'source_type',
                    'transaction_type', 'document_number', 'account_code', 'metric',
                    'value', 'unit',
                ])
                ->toArray();

            DB::rollBack();

            return [
                'filename' => basename($path),
                'document_type' => $detection['type'],
                'source_category' => $detection['category'],
                'confidence' => $detection['confidence'],
                'readiness' => $validation['readiness'],
                'parsed_rows' => $parsedRows,
                'source_records' => $recordCount,
                'financial_facts' => $factCount,
                'quality' => $quality,
                'sample_facts' => $sampleFacts,
                'rolled_back' => true,
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function executeBatch(
        array $files,
        int $year,
        int $userId,
        ?int $month = null,
    ): array {
        $accountingYear = AccountingYear::query()->where('year', $year)->first();

        if (! $accountingYear) {
            throw ValidationException::withMessages([
                'year' => "Tahun anggaran {$year} belum tersedia.",
            ]);
        }

        if ($month !== null && ($month < 1 || $month > 12)) {
            throw ValidationException::withMessages([
                'month' => 'Bulan harus berada pada rentang 1 sampai 12.',
            ]);
        }

        $batch = ImportBatch::create([
            'accounting_year_id' => $accountingYear->id,
            'initiated_by' => $userId,
            'month' => $month,
            'status' => 'RUNNING',
            'file_count' => count($files),
            'started_at' => now(),
        ]);

        $imported = [];
        $failed = [];

        foreach ($files as $file) {
            try {
                $imported[] = $this->execute($file, $year, $userId, $month, $batch);
            } catch (Throwable $e) {
                $failed[] = [
                    'filename' => $file->getClientOriginalName(),
                    'message' => $e->getMessage(),
                ];
            }
        }

        $documentIds = collect($imported)->pluck('id');

        $sourceRecordCount = $documentIds->isEmpty()
            ? 0
            : DB::table('source_records')->whereIn('source_document_id', $documentIds)->count();

        $financialFactCount = $documentIds->isEmpty()
            ? 0
            : DB::table('financial_facts')->whereIn('source_document_id', $documentIds)->count();

        $status = count($failed) === 0
            ? 'COMPLETED'
            : (count($imported) === 0 ? 'FAILED' : 'PARTIAL');

        $batch->update([
            'status' => $status,
            'imported_count' => count($imported),
            'failed_count' => count($failed),
            'source_record_count' => $sourceRecordCount,
            'financial_fact_count' => $financialFactCount,
            'summary' => [
                'failed' => $failed,
                'document_types' => collect($imported)->countBy('document_type')->all(),
                'source_categories' => collect($imported)->countBy('source_category')->all(),
            ],
            'completed_at' => now(),
        ]);

        return [
            'batch' => $batch->fresh(),
            'imported' => $imported,
            'failed' => $failed,
        ];
    }

    private function reportScope(string $filename, string $type): string
    {
        $name = mb_strtolower(pathinfo($filename, PATHINFO_BASENAME));

        if ($type !== 'financial_statement') {
            return 'source';
        }

        if (str_starts_with($name, 'kertas-kerja-') || str_contains($name, 'kertas kerja')) {
            return 'working_paper';
        }

        if (str_starts_with($name, 'lra-') || str_starts_with($name, 'neraca-') || str_starts_with($name, 'lpe_') || str_starts_with($name, 'laporan-operasional-')) {
            return 'official_report';
        }

        if (str_starts_with($name, 'lra-program-')) {
            return 'supporting_schedule';
        }

        return 'financial_statement_unknown';
    }

    private function parse(
        string $type,
        Collection $sheets,
        SourceDocument $document,
        int $year,
        ?int $month,
    ): int {
        if ($type === 'revenue_reconciliation') {
            return $this->revenueParser->parse($sheets, $document, $year, $month);
        }

        if ($type === 'expenditure_reconciliation') {
            foreach ($sheets as $sheetName => $rows) {
                if ($this->expenditureParser->supports($rows)) {
                    return $this->expenditureParser->parse($rows, $document, $year, $month, $sheetName);
                }
            }

            throw ValidationException::withMessages([
                'file' => 'Workbook rekonsiliasi pengeluaran terdeteksi, tetapi tabel sumber tidak ditemukan.',
            ]);
        }

        if ($type === 'ledger') {
            return $this->ledgerParser->parse($sheets, $document, $year, $month);
        }

        if ($type === 'financial_statement') {
            return $this->financialStatementParser->parse($sheets, $document, $year, $month);
        }

        if ($this->normalizedParser->supports($type)) {
            return $this->normalizedParser->parse($sheets, $document, $year, $month);
        }

        return $this->genericParser->parse($sheets, $document, $year, $month);
    }

    private function readSheets($spreadsheet): Collection
    {
        $sheets = new Collection();

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $rows = collect();

            foreach ($worksheet->toArray(null, true, true, false) as $row) {
                $rows->push(array_values($row));
            }

            $sheets->put($worksheet->getTitle(), $rows);
        }

        return $sheets;
    }
}

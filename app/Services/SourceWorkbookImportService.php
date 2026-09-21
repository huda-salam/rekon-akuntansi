<?php

namespace App\Services;

use App\Models\AccountingYear;
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
    ) {}

    public function execute(
        UploadedFile $file,
        int $year,
        int $userId,
        ?int $month = null,
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

        if ($detection['type'] === 'unknown') {
            throw ValidationException::withMessages([
                'file' => 'Jenis workbook belum didukung atau strukturnya tidak dikenali.',
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
                $sheets, $detection, $path
            ) {
                $document = SourceDocument::create([
                    'accounting_year_id' => $accountingYear->id,
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
                    ],
                    'imported_at' => now(),
                ]);

                $count = $this->parse($detection['type'], $sheets, $document, $accountingYear->year, $month);
                $document->update(['row_count' => $count]);

                return $document->fresh();
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    /**
     * @param array<int, UploadedFile> $files
     * @return array{imported: array<int, SourceDocument>, failed: array<int, array<string,mixed>>}
     */
    public function executeBatch(
        array $files,
        int $year,
        int $userId,
        ?int $month = null,
    ): array {
        $imported = [];
        $failed = [];

        foreach ($files as $file) {
            try {
                $imported[] = $this->execute($file, $year, $userId, $month);
            } catch (Throwable $e) {
                $failed[] = [
                    'filename' => $file->getClientOriginalName(),
                    'message' => $e->getMessage(),
                ];
            }
        }

        return compact('imported', 'failed');
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

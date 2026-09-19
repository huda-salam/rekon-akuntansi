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
    ) {}

    public function execute(UploadedFile $file, int $year, int $userId): SourceDocument
    {
        $accountingYear = AccountingYear::query()->where('year', $year)->first();

        if (! $accountingYear) {
            throw ValidationException::withMessages([
                'year' => "Tahun anggaran {$year} belum tersedia.",
            ]);
        }

        $checksum = hash_file('sha256', $file->getRealPath());

        if (SourceDocument::query()->where('checksum_sha256', $checksum)->exists()) {
            throw ValidationException::withMessages([
                'file' => 'File yang sama sudah pernah diimpor.',
            ]);
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheets = new Collection();

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $rows = collect();

            foreach ($worksheet->toArray(null, true, true, false) as $row) {
                $rows->push(array_values($row));
            }

            $sheets->put($worksheet->getTitle(), $rows);
        }

        $detection = $this->detector->detect($sheets);

        if ($detection['type'] === 'unknown') {
            throw ValidationException::withMessages([
                'file' => 'Jenis workbook belum didukung atau strukturnya tidak dikenali.',
            ]);
        }

        if ($detection['type'] !== 'expenditure_reconciliation') {
            throw ValidationException::withMessages([
                'file' => "Workbook terdeteksi sebagai {$detection['type']}, tetapi parser untuk jenis tersebut belum tersedia.",
            ]);
        }

        $firstSheetRows = $sheets->first();

        if (! $firstSheetRows instanceof Collection || ! $this->expenditureParser->supports($firstSheetRows)) {
            throw ValidationException::withMessages([
                'file' => 'Workbook terdeteksi sebagai rekonsiliasi pengeluaran, tetapi struktur tabelnya tidak valid.',
            ]);
        }

        $path = $file->storeAs(
            'source-documents',
            $checksum . '.' . strtolower($file->getClientOriginalExtension()),
            'local'
        );

        try {
            return DB::transaction(function () use (
                $file,
                $checksum,
                $accountingYear,
                $userId,
                $firstSheetRows,
                $sheets,
                $detection,
                $path
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
                    ],
                    'imported_at' => now(),
                ]);

                $count = $this->expenditureParser->parse(
                    $firstSheetRows,
                    $document,
                    $accountingYear->year
                );

                $document->update(['row_count' => $count]);

                return $document->fresh();
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }
}

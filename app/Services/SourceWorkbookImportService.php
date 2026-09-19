<?php

namespace App\Services;

use App\Models\AccountingYear;
use App\Models\SourceDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SourceWorkbookImportService
{
    public function __construct(
        private readonly ExpenditureReconciliationParser $expenditureParser,
    ) {}

    public function execute(UploadedFile $file, int $year, int $userId): SourceDocument
    {
        $accountingYear = AccountingYear::query()->where('year', $year)->first();
        if (! $accountingYear) {
            throw ValidationException::withMessages(['year' => "Tahun anggaran {$year} belum tersedia."]);
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        if (SourceDocument::query()->where('checksum_sha256', $checksum)->exists()) {
            throw ValidationException::withMessages(['file' => 'File yang sama sudah pernah diimpor.']);
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheets = [];
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $rows = [];
            foreach ($worksheet->toArray(null, true, true, false) as $row) {
                $rows[] = array_values($row);
            }
            $sheets[$worksheet->getTitle()] = $rows;
        }

        $firstSheetRows = collect(array_values($sheets)[0] ?? []);
        if ($this->expenditureParser->supports($firstSheetRows)) {
            $type = 'expenditure_reconciliation';
            $category = 'RKUD';
        } else {
            throw ValidationException::withMessages([
                'file' => 'Jenis workbook belum didukung atau strukturnya tidak dikenali.',
            ]);
        }

        return DB::transaction(function () use ($file, $checksum, $accountingYear, $userId, $type, $category, $firstSheetRows) {
            $path = $file->storeAs(
                'source-documents',
                $checksum . '.' . strtolower($file->getClientOriginalExtension()),
                'local'
            );

            $document = SourceDocument::create([
                'accounting_year_id' => $accountingYear->id,
                'uploaded_by' => $userId,
                'original_filename' => $file->getClientOriginalName(),
                'document_type' => $type,
                'source_category' => $category,
                'mime_type' => $file->getMimeType(),
                'checksum_sha256' => $checksum,
                'file_path' => $path,
                'status' => 'IMPORTED',
                'metadata' => ['sheets' => array_keys($GLOBALS['__source_import_sheets'] ?? [])],
                'imported_at' => now(),
            ]);

            $count = $this->expenditureParser->parse($firstSheetRows, $document, $accountingYear->year);
            $document->update(['row_count' => $count]);

            return $document->fresh();
        });
    }
}
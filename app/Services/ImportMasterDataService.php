<?php

namespace App\Services;

use App\Imports\MasterDataImport;
use App\Models\MasterReference;
use App\Models\Skpd;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ImportMasterDataService
{
    private const TYPES = [
        'urusan',
        'bidang',
        'program',
        'sub_kegiatan',
        'skpd',
        'rekening_belanja',
        'rekening_pendapatan',
        'rekening_pembiayaan',
    ];

    public function execute(UploadedFile $file, ?int $sourceYear = null): array
    {
        $import = new MasterDataImport();
        Excel::import($import, $file);

        $rows = $import->rows ?? collect();
        $this->validateRows($rows);

        return DB::transaction(function () use ($rows, $sourceYear) {
            $masterCount = 0;
            $skpdCount = 0;

            foreach ($rows as $row) {
                $type = trim((string) $row['jenis']);
                $code = trim((string) $row['kode']);
                $description = trim((string) $row['uraian']);
                $parentCode = $this->nullableString($row['parent'] ?? null);
                $level = $this->nullableNumber($row['level'] ?? null);

                MasterReference::updateOrCreate(
                    ['type' => $type, 'code' => $code],
                    [
                        'description' => $description,
                        'level' => $level,
                        'parent_code' => $parentCode,
                        'source_year' => $sourceYear,
                        'is_active' => true,
                    ],
                );
                $masterCount++;

                if ($type === 'skpd') {
                    Skpd::updateOrCreate(
                        ['code' => $code],
                        ['name' => $description, 'is_active' => true],
                    );
                    $skpdCount++;
                }
            }

            return [
                'rows' => $masterCount,
                'skpds' => $skpdCount,
                'source_year' => $sourceYear,
            ];
        });
    }

    private function validateRows($rows): void
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak berisi data.']);
        }

        $errors = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $type = trim((string) ($row['jenis'] ?? ''));
            $code = trim((string) ($row['kode'] ?? ''));
            $description = trim((string) ($row['uraian'] ?? ''));

            if ($code === '') {
                $errors[] = "Baris {$line}: kode wajib diisi.";
            }
            if ($description === '') {
                $errors[] = "Baris {$line}: uraian wajib diisi.";
            }
            if (!in_array($type, self::TYPES, true)) {
                $errors[] = "Baris {$line}: jenis '{$type}' tidak dikenali.";
            }

            $key = $type . '|' . $code;
            if ($type !== '' && $code !== '' && isset($seen[$key])) {
                $errors[] = "Baris {$line}: kombinasi jenis dan kode duplikat dengan baris {$seen[$key]}.";
            }
            if ($type !== '' && $code !== '') {
                $seen[$key] = $line;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages([
                'file' => array_slice($errors, 0, 50),
            ]);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return (float) $value;
    }
}

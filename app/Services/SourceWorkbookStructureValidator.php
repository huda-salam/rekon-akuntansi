<?php

namespace App\Services;

use Illuminate\Support\Collection;

class SourceWorkbookStructureValidator
{
    /**
     * @param Collection<string, Collection<int, array<int, mixed>>> $sheets
     * @return array{valid:bool,missing:array<int,string>,evidence:array<int,string>}
     */
    public function validate(string $type, Collection $sheets): array
    {
        $checks = match ($type) {
            'ledger' => [
                'ledger header' => fn (Collection $rows) => $this->containsAll($rows, ['tanggal', 'kode rekening'])
                    && $this->containsAny($rows, ['debit', 'kredit']),
            ],
            'financial_statement' => [
                'description' => fn (Collection $rows) => $this->containsAny($rows, ['uraian', 'nama rekening', 'rekening']),
                'financial value columns' => fn (Collection $rows) => $this->containsAny(
                    $rows,
                    ['kode rekening', 'kode akun', 'kode', '2026', '2025', 'anggaran', 'realisasi', 'saldo', 'jumlah', 'konsolidasi']
                ),
            ],
            'revenue_reconciliation' => [
                'month columns' => fn (Collection $rows) => $this->containsAnyMonth($rows),
            ],
            'expenditure_reconciliation' => [
                'reconciliation header' => fn (Collection $rows) => $this->containsAll($rows, ['no', 'kode', 'skpd', 'sp2d ls', 'spj ls', 'selisih']),
            ],
            'blud' => [
                'SP3BP/SP2BP markers' => fn (Collection $rows) => $this->containsAll($rows, ['nomor sp3bp', 'nomor sp2bp']),
            ],
            'non_rkud_transfer' => [
                'transfer document markers' => fn (Collection $rows) => $this->containsAnyGroup($rows, [
                    ['nomor sp2b', 'nomor spb'],
                    ['nomor sp2d bun', 'nomor sp2bdd'],
                    ['nomor sp3bp', 'nomor sp2bp'],
                ]),
            ],
            default => [],
        };

        if ($checks === []) {
            return ['valid' => false, 'missing' => ['validator untuk document type'], 'evidence' => []];
        }

        $missing = [];
        $evidence = [];

        foreach ($checks as $name => $check) {
            $matchedSheet = null;

            foreach ($sheets as $sheetName => $rows) {
                if ($check($rows)) {
                    $matchedSheet = (string) $sheetName;
                    break;
                }
            }

            if ($matchedSheet === null) {
                $missing[] = $name;
            } else {
                $evidence[] = $name . ': ' . $matchedSheet;
            }
        }

        return [
            'valid' => $missing === [],
            'missing' => $missing,
            'evidence' => $evidence,
        ];
    }

    private function containsAll(Collection $rows, array $targets): bool
    {
        $labels = $this->labels($rows);

        foreach ($targets as $target) {
            if (! $labels->contains($target)) {
                return false;
            }
        }

        return true;
    }

    private function containsAny(Collection $rows, array $targets): bool
    {
        $labels = $this->labels($rows);

        foreach ($labels as $label) {
            foreach ($targets as $target) {
                if ($label === $target || str_contains($label, $target)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function containsAnyGroup(Collection $rows, array $groups): bool
    {
        foreach ($groups as $targets) {
            if ($this->containsAll($rows, $targets)) {
                return true;
            }
        }

        return false;
    }

    private function containsAnyMonth(Collection $rows): bool
    {
        $months = ['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
        $labels = $this->labels($rows);

        foreach ($months as $month) {
            if ($labels->contains($month)) {
                return true;
            }
        }

        return false;
    }

    private function labels(Collection $rows): Collection
    {
        return $rows->take(20)
            ->flatten()
            ->map(fn ($value) => mb_strtolower(trim((string) ($value ?? ''))))
            ->filter()
            ->unique()
            ->values();
    }
}

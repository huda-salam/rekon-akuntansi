<?php

namespace App\Services;

use Illuminate\Support\Collection;

class SourceDocumentDetector
{
    /**
     * @param Collection<string, Collection<int, array<int, mixed>>> $sheets
     * @return array{type:string,category:?string,confidence:float,evidence:array<int,string>}
     */
    public function detect(Collection $sheets, ?string $filename = null): array
    {
        $evidence = [];

        foreach ($sheets as $sheetName => $rows) {
            $sheet = $this->normalizeSheet($rows);

            if ($this->hasExpenditureHeader($sheet)) {
                return [
                    'type' => 'expenditure_reconciliation',
                    'category' => 'RKUD',
                    'confidence' => 1.0,
                    'evidence' => ["{$sheetName}: SP2D/SPJ/STS reconciliation header"],
                ];
            }

            if ($this->hasRevenueReconciliationMarkers($sheet)) {
                $evidence[] = "{$sheetName}: revenue reconciliation markers";
            }

            if ($this->hasLedgerMarkers($sheet)) {
                $evidence[] = "{$sheetName}: BUKU BESAR / journal markers";
            }

            if ($this->hasFinancialStatementMarkers($sheet)) {
                $evidence[] = "{$sheetName}: financial statement markers";
            }
        }

        $names = $sheets->keys()
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->values();

        // Strong structural markers must take precedence over filename guesses.
        // Check Dana Desa first because these workbooks also carry generic
        // BLUD/BOK/BOSP template sheets.
        if ($names->contains('data dd') && $this->hasDanaDesaMarkers($sheets)) {
            return [
                'type' => 'non_rkud_transfer',
                'category' => 'NON_RKUD',
                'confidence' => 0.98,
                'evidence' => array_merge($evidence, ['Dana Desa: Data DD + SP2D BUN/SP2BDD markers']),
            ];
        }

        if ($names->contains('tabel sp2bp')) {
            return [
                'type' => 'blud',
                'category' => 'NON_RKUD',
                'confidence' => 0.95,
                'evidence' => array_merge($evidence, ['sheet: Tabel SP2BP']),
            ];
        }

        if ($this->hasBludMarkers($sheets)) {
            return [
                'type' => 'blud',
                'category' => 'NON_RKUD',
                'confidence' => 0.98,
                'evidence' => array_merge($evidence, ['BLUD structural markers']),
            ];
        }

        if ($names->contains('tabelsp2t') && $names->contains('tabelspb')) {
            return [
                'type' => 'non_rkud_transfer',
                'category' => 'NON_RKUD',
                'confidence' => 0.9,
                'evidence' => array_merge($evidence, ['sheets: TabelSP2T + TabelSPB']),
            ];
        }

        if ($this->containsEvidence($evidence, 'BUKU BESAR')) {
            return [
                'type' => 'ledger',
                'category' => 'ACCOUNTING',
                'confidence' => 0.85,
                'evidence' => $evidence,
            ];
        }

        if ($this->containsEvidence($evidence, 'financial statement')) {
            return [
                'type' => 'financial_statement',
                'category' => 'ACCOUNTING',
                'confidence' => 0.85,
                'evidence' => $evidence,
            ];
        }

        if ($this->containsEvidence($evidence, 'revenue reconciliation')) {
            return [
                'type' => 'revenue_reconciliation',
                'category' => 'RKUD',
                'confidence' => 0.85,
                'evidence' => $evidence,
            ];
        }

        $filenameText = mb_strtolower((string) $filename);
        $filenameNormalized = str_replace(['_', '-'], ' ', $filenameText);

        if (str_contains($filenameNormalized, 'buku besar') || str_contains($filenameNormalized, 'buku jurnal')) {
            return [
                'type' => 'ledger',
                'category' => 'ACCOUNTING',
                'confidence' => 0.75,
                'evidence' => array_merge($evidence, ['filename: ledger pattern']),
            ];
        }

        if (str_contains($filenameNormalized, 'rekonsiliasi pendapatan')) {
            return [
                'type' => 'revenue_reconciliation',
                'category' => 'RKUD',
                'confidence' => 0.75,
                'evidence' => array_merge($evidence, ['filename: revenue reconciliation pattern']),
            ];
        }

        if (str_contains($filenameNormalized, 'rekonsiliasi pengeluaran')) {
            return [
                'type' => 'expenditure_reconciliation',
                'category' => 'RKUD',
                'confidence' => 0.75,
                'evidence' => array_merge($evidence, ['filename: expenditure reconciliation pattern']),
            ];
        }

        if (
            (str_contains($filenameNormalized, 'kertas kerja') &&
                (str_contains($filenameNormalized, ' lra')
                    || str_contains($filenameNormalized, ' lo')
                    || str_contains($filenameNormalized, ' lpe')
                    || str_contains($filenameNormalized, ' neraca')))
            || str_contains($filenameNormalized, 'lra ')
            || str_contains($filenameNormalized, 'neraca ')
            || str_contains($filenameNormalized, 'laporan operasional')
            || str_contains($filenameNormalized, 'lpe ')
        ) {
            return [
                'type' => 'financial_statement',
                'category' => 'ACCOUNTING',
                'confidence' => 0.95,
                'evidence' => array_merge($evidence, ['filename: financial statement pattern']),
            ];
        }

        if (str_contains($filenameNormalized, 'lra program')) {
            return [
                'type' => 'financial_statement',
                'category' => 'ACCOUNTING',
                'confidence' => 0.85,
                'evidence' => array_merge($evidence, ['filename: LRA program supporting schedule']),
            ];
        }

        if (str_contains($filenameText, 'pengesahan')
            || str_contains($filenameText, 'bosp')
            || str_contains($filenameText, 'bok')
            || str_contains($filenameText, 'blud')
            || str_contains($filenameText, 'tunjangan')
            || str_contains($filenameText, 'tamsil')
        ) {
            return [
                'type' => 'non_rkud_transfer',
                'category' => 'NON_RKUD',
                'confidence' => 0.65,
                'evidence' => array_merge($evidence, ['filename: non-RKUD transfer pattern']),
            ];
        }

        return [
            'type' => 'unknown',
            'category' => null,
            'confidence' => 0.0,
            'evidence' => $evidence,
        ];
    }

    private function hasBludMarkers(Collection $sheets): bool
    {
        foreach ($sheets as $sheetName => $rows) {
            $name = mb_strtolower(trim((string) $sheetName));
            $text = $this->flattenText($this->normalizeSheet($rows)->take(20));

            if (
                (str_contains($name, 'blud') || $name === 'tabel sp2bp')
                && str_contains($text, 'nomor sp3bp')
                && str_contains($text, 'nomor sp2bp')
                && str_contains($text, 'saldo awal')
                && (
                    str_contains($text, 'belanja pegawai blud')
                    || $name === 'tabel sp2bp'
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function hasDanaDesaMarkers(Collection $sheets): bool
    {
        foreach ($sheets as $rows) {
            $text = $this->flattenText($this->normalizeSheet($rows)->take(20));

            if (
                str_contains($text, 'nomor sp2d bun')
                && str_contains($text, 'nomor sp2bdd')
                && str_contains($text, 'saldo akhir')
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalizeSheet(Collection $rows): Collection
    {
        return $rows->map(function ($row) {
            return collect($row)->map(
                fn ($value) => mb_strtolower(trim((string) ($value ?? '')))
            )->values();
        });
    }

    private function hasExpenditureHeader(Collection $rows): bool
    {
        foreach ($rows->take(20) as $row) {
            $labels = $row->filter(fn ($value) => $value !== '')->values();

            if (
                $labels->contains('no') &&
                $labels->contains('kode') &&
                $labels->contains('skpd') &&
                $labels->contains('sp2d ls') &&
                $labels->contains('spj ls') &&
                $labels->contains('selisih')
            ) {
                return true;
            }
        }

        return false;
    }

    private function hasRevenueReconciliationMarkers(Collection $rows): bool
    {
        $text = $this->flattenText($rows->take(20));

        return str_contains($text, 'berita acara rekonsiliasi')
            && str_contains($text, 'pendapatan skpd');
    }

    private function hasLedgerMarkers(Collection $rows): bool
    {
        $text = $this->flattenText($rows->take(12));

        return str_contains($text, 'buku besar')
            || (
                str_contains($text, 'tanggal')
                && str_contains($text, 'debit')
                && str_contains($text, 'kredit')
                && str_contains($text, 'kode rekening')
            );
    }

    private function hasFinancialStatementMarkers(Collection $rows): bool
    {
        $text = $this->flattenText($rows->take(10));

        return str_contains($text, 'laporan realisasi anggaran')
            || str_contains($text, 'laporan operasional')
            || str_contains($text, 'neraca')
            || str_contains($text, 'laporan perubahan ekuitas');
    }

    private function flattenText(Collection $rows): string
    {
        return $rows
            ->flatten()
            ->filter(fn ($value) => $value !== '')
            ->implode(' ');
    }

    private function containsEvidence(array $evidence, string $needle): bool
    {
        foreach ($evidence as $item) {
            if (str_contains(mb_strtolower($item), mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}

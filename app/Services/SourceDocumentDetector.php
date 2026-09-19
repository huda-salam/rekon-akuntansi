<?php

namespace App\Services;

use Illuminate\Support\Collection;

class SourceDocumentDetector
{
    public function detect(Collection $sheets): array
    {
        $names = $sheets->keys()->map(fn ($v) => mb_strtolower(trim((string) $v)))->values();

        if ($names->contains('rekapitulasi realisasi (sp2d, spj, sts)')) {
            return ['type'=>'expenditure_reconciliation','category'=>'RKUD','confidence'=>1.0];
        }
        if ($names->contains('tabel sp2bp')) {
            return ['type'=>'blud','category'=>'NON_RKUD','confidence'=>1.0];
        }
        if ($names->contains('tabelsp2t') && $names->contains('tabelspb')) {
            return ['type'=>'non_rkud_transfer','category'=>'NON_RKUD','confidence'=>0.9];
        }
        if ($names->contains('buku besar') || $names->contains('semester 1') || $names->contains('semester 2')) {
            return ['type'=>'ledger','category'=>'ACCOUNTING','confidence'=>0.8];
        }

        return ['type'=>'unknown','category'=>null,'confidence'=>0.0];
    }
}
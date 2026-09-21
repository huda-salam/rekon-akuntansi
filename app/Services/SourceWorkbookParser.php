<?php

namespace App\Services;

use App\Models\SourceDocument;
use Illuminate\Support\Collection;

interface SourceWorkbookParser
{
    public function supports(string $type): bool;

    public function parse(
        Collection $sheets,
        SourceDocument $document,
        int $year,
        ?int $month = null,
    ): int;
}

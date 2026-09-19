<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SourceWorkbookReader implements ToCollection, WithMultipleSheets
{
    public array $sheets = [];

    public function collection(Collection $rows): void
    {
        $this->sheets['Worksheet'] = $rows;
    }

    public function sheets(): array
    {
        return [];
    }
}
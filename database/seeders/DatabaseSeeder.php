<?php

namespace Database\Seeders;

use App\Models\AccountingYear;
use App\Models\Official;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $year = AccountingYear::firstOrCreate(['year' => (int) date('Y')], ['is_active' => true]);
        AccountingYear::whereKeyNot($year->id)->update(['is_active' => false]);
        $year->update(['is_active' => true]);

        $skpd = Skpd::firstOrCreate(
            ['code' => 'DEMO'],
            ['name' => 'SKPD Demo', 'is_active' => true]
        );

        Official::firstOrCreate(
            ['skpd_id' => $skpd->id, 'name' => 'Pejabat Demo'],
            ['position' => 'Kepala SKPD', 'is_active' => true]
        );

        User::firstOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Administrator', 'password' => 'password', 'role' => 'admin']
        );

        User::firstOrCreate(
            ['email' => 'skpd@example.test'],
            ['name' => 'User SKPD Demo', 'password' => 'password', 'role' => 'skpd', 'skpd_id' => $skpd->id]
        );
    }
}

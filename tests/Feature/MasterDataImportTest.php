<?php

namespace Tests\Feature;

use App\Models\MasterReference;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class MasterDataImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_master_excel_and_sync_skpd(): void
    {
        Excel::shouldReceive('import')->once()->andReturnUsing(function ($import) {
            $import->rows = new Collection([
                ['kode' => '1', 'uraian' => 'Urusan', 'jenis' => 'urusan', 'level' => null, 'parent' => null],
                ['kode' => '1.01', 'uraian' => 'Bidang Pendidikan', 'jenis' => 'bidang', 'level' => null, 'parent' => '1'],
                ['kode' => '1.01.2.19.0.00.01.0000', 'uraian' => 'Dinas Pendidikan', 'jenis' => 'skpd', 'level' => null, 'parent' => null],
            ]);
        });

        $admin = User::factory()->create(['role' => 'admin', 'skpd_id' => null]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/master-data/import', [
                'file' => UploadedFile::fake()->create('master.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'source_year' => 2026,
            ])
            ->assertOk()
            ->assertJsonPath('rows', 3)
            ->assertJsonPath('skpds', 1)
            ->assertJsonPath('source_year', 2026);

        $this->assertDatabaseHas('master_references', [
            'type' => 'bidang',
            'code' => '1.01',
            'parent_code' => '1',
            'source_year' => 2026,
        ]);
        $this->assertDatabaseHas('skpds', [
            'code' => '1.01.2.19.0.00.01.0000',
            'name' => 'Dinas Pendidikan',
        ]);
    }

    public function test_master_import_is_upsert_not_duplicate(): void
    {
        MasterReference::create([
            'code' => '1.01',
            'description' => 'Old name',
            'type' => 'bidang',
            'parent_code' => '1',
            'source_year' => 2025,
            'is_active' => true,
        ]);

        Excel::shouldReceive('import')->once()->andReturnUsing(function ($import) {
            $import->rows = new Collection([
                ['kode' => '1.01', 'uraian' => 'New name', 'jenis' => 'bidang', 'level' => null, 'parent' => '1'],
            ]);
        });

        $admin = User::factory()->create(['role' => 'admin', 'skpd_id' => null]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/master-data/import', [
                'file' => UploadedFile::fake()->create('master.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'source_year' => 2026,
            ])
            ->assertOk();

        $this->assertSame(1, MasterReference::where('type', 'bidang')->where('code', '1.01')->count());
        $this->assertDatabaseHas('master_references', [
            'type' => 'bidang',
            'code' => '1.01',
            'description' => 'New name',
            'source_year' => 2026,
        ]);
    }

    public function test_skpkd_cannot_import_master_data(): void
    {
        $skpkd = User::factory()->create(['role' => 'skpkd', 'skpd_id' => null]);

        $this->actingAs($skpkd, 'sanctum')
            ->postJson('/api/master-data/import', [
                'file' => UploadedFile::fake()->create('master.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ])
            ->assertForbidden();
    }

    public function test_import_rejects_unknown_master_type_before_writing(): void
    {
        Excel::shouldReceive('import')->once()->andReturnUsing(function ($import) {
            $import->rows = new Collection([
                ['kode' => 'X', 'uraian' => 'Invalid', 'jenis' => 'unknown', 'level' => null, 'parent' => null],
            ]);
        });

        $admin = User::factory()->create(['role' => 'admin', 'skpd_id' => null]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/master-data/import', [
                'file' => UploadedFile::fake()->create('master.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'source_year' => 2026,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('master_references', 0);
    }
}

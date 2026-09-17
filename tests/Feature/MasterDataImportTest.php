<?php

namespace Tests\Feature;

use App\Models\MasterReference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class MasterDataImportTest extends TestCase
{
    use RefreshDatabase;

    private function mockImport(Collection $rows): void
    {
        $excel = Excel::getFacadeRoot();
        Excel::shouldReceive('import')
            ->once()
            ->andReturnUsing(function ($import) use ($rows, $excel) {
                $import->rows = $rows;
                return $excel;
            });
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => 'password', 'role' => 'admin', 'skpd_id' => null]);
    }

    private function upload(int $year = 2026): array
    {
        return [
            'file' => UploadedFile::fake()->create('master.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'source_year' => $year,
        ];
    }

    public function test_admin_can_import_master_excel_and_sync_skpd(): void
    {
        $this->mockImport(new Collection([
            ['kode' => '1', 'uraian' => 'Urusan', 'jenis' => 'urusan', 'level' => null, 'parent' => null],
            ['kode' => '1.01', 'uraian' => 'Bidang Pendidikan', 'jenis' => 'bidang', 'level' => null, 'parent' => '1'],
            ['kode' => '1.01.2.19.0.00.01.0000', 'uraian' => 'Dinas Pendidikan', 'jenis' => 'skpd', 'level' => null, 'parent' => null],
        ]));

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/master-data/import', $this->upload())
            ->assertOk()
            ->assertJsonPath('rows', 3)
            ->assertJsonPath('skpds', 1)
            ->assertJsonPath('year', 2026);

        $this->assertDatabaseHas('master_references', ['year' => 2026, 'type' => 'bidang', 'code' => '1.01', 'parent_code' => '1']);
        $this->assertDatabaseHas('skpds', ['code' => '1.01.2.19.0.00.01.0000', 'name' => 'Dinas Pendidikan']);
    }

    public function test_same_master_code_can_exist_for_different_years(): void
    {
        MasterReference::create(['year' => 2025, 'code' => '1.01', 'description' => 'Old name', 'type' => 'bidang', 'parent_code' => '1', 'is_active' => true]);
        $this->mockImport(new Collection([
            ['kode' => '1.01', 'uraian' => 'New name', 'jenis' => 'bidang', 'level' => null, 'parent' => '1'],
        ]));

        $this->actingAs($this->admin(), 'sanctum')->postJson('/api/master-data/import', $this->upload())->assertOk();

        $this->assertSame(2, MasterReference::where('type', 'bidang')->where('code', '1.01')->count());
        $this->assertDatabaseHas('master_references', ['year' => 2025, 'type' => 'bidang', 'code' => '1.01', 'description' => 'Old name']);
        $this->assertDatabaseHas('master_references', ['year' => 2026, 'type' => 'bidang', 'code' => '1.01', 'description' => 'New name']);
    }

    public function test_same_master_code_is_upserted_within_same_year(): void
    {
        MasterReference::create(['year' => 2026, 'code' => '1.01', 'description' => 'Old name', 'type' => 'bidang', 'parent_code' => '1', 'is_active' => true]);
        $this->mockImport(new Collection([
            ['kode' => '1.01', 'uraian' => 'New name', 'jenis' => 'bidang', 'level' => null, 'parent' => '1'],
        ]));

        $this->actingAs($this->admin(), 'sanctum')->postJson('/api/master-data/import', $this->upload())->assertOk();

        $this->assertSame(1, MasterReference::where('year', 2026)->where('type', 'bidang')->where('code', '1.01')->count());
        $this->assertDatabaseHas('master_references', ['year' => 2026, 'type' => 'bidang', 'code' => '1.01', 'description' => 'New name']);
    }

    public function test_import_requires_year(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/master-data/import', ['file' => UploadedFile::fake()->create('master.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_year');
    }

    public function test_skpkd_cannot_import_master_data(): void
    {
        $skpkd = User::create(['name' => 'SKPKD', 'email' => 'skpkd@test.local', 'password' => 'password', 'role' => 'skpkd', 'skpd_id' => null]);

        $this->actingAs($skpkd, 'sanctum')
            ->postJson('/api/master-data/import', ['file' => UploadedFile::fake()->create('master.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])
            ->assertForbidden();
    }

    public function test_import_rejects_unknown_master_type_before_writing(): void
    {
        $this->mockImport(new Collection([
            ['kode' => 'X', 'uraian' => 'Invalid', 'jenis' => 'unknown', 'level' => null, 'parent' => null],
        ]));

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/master-data/import', $this->upload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('master_references', 0);
    }
}

<?php

namespace Database\Seeders;

use App\Models\CalculationDefinition;
use App\Models\ReconciliationRule;
use Illuminate\Database\Seeder;

class ReconciliationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $calculations = [
            [
                'code' => 'EXP-TOTAL-SP2D-DERIVED',
                'name' => 'TOTAL SP2D from components',
                'expression' => 'SUM',
                'input_metrics' => ['sp2d_ls', 'sp2d_up_gu', 'sp2d_tu', 'sp2d_kkpd'],
            ],
            [
                'code' => 'EXP-TOTAL-SPJ-DERIVED',
                'name' => 'TOTAL SPJ from components',
                'expression' => 'SUM',
                'input_metrics' => ['spj_ls', 'spj_up_gu', 'spj_tu', 'spj_kkpd'],
            ],
            [
                'code' => 'EXP-TOTAL-STS-DERIVED',
                'name' => 'TOTAL STS from STS/CP components',
                'expression' => 'SUM',
                'input_metrics' => ['sts_up_gu', 'sts_tu', 'cp_ls', 'cp_up_gu', 'cp_tu'],
            ],
            [
                'code' => 'EXP-SELISIH-KAS-DERIVED',
                'name' => 'SELISIH KAS SIPD dan KAS BANK',
                'expression' => 'kas_sipd - kas_bank',
                'input_metrics' => ['kas_sipd', 'kas_bank'],
            ],
        ];

        foreach ($calculations as $definition) {
            CalculationDefinition::updateOrCreate(
                ['accounting_year_id' => null, 'code' => $definition['code'], 'version' => '1'],
                [
                    'name' => $definition['name'],
                    'status' => 'active',
                    'expression' => $definition['expression'],
                    'input_metrics' => $definition['input_metrics'],
                    'metadata' => [
                        'source' => 'docs/input/rekonsiliasi pengeluaran.xlsx',
                        'type' => 'derived_calculation',
                    ],
                ]
            );
        }

        $rules = [
            [
                'code' => 'EXP-001',
                'name' => 'TOTAL SP2D consistency',
                'expression' => 'total_sp2d_reported - total_sp2d_derived',
                'inputs' => ['total_sp2d_reported', 'total_sp2d_derived'],
                'calculation' => 'EXP-TOTAL-SP2D-DERIVED',
            ],
            [
                'code' => 'EXP-002',
                'name' => 'TOTAL SPJ consistency',
                'expression' => 'total_spj_reported - total_spj_derived',
                'inputs' => ['total_spj_reported', 'total_spj_derived'],
                'calculation' => 'EXP-TOTAL-SPJ-DERIVED',
            ],
            [
                'code' => 'EXP-003',
                'name' => 'TOTAL STS consistency',
                'expression' => 'total_sts_reported - total_sts_derived',
                'inputs' => ['total_sts_reported', 'total_sts_derived'],
                'calculation' => 'EXP-TOTAL-STS-DERIVED',
            ],
            [
                'code' => 'EXP-004',
                'name' => 'Reported SELISIH agrees with KAS SIPD minus KAS BANK',
                'expression' => 'selisih_reported - selisih_kas_derived',
                'inputs' => ['selisih_reported', 'selisih_kas_derived'],
                'calculation' => 'EXP-SELISIH-KAS-DERIVED',
            ],
        ];

        $revenueRules = [
            ['code'=>'REV-001','name'=>'BKU penerimaan manual vs BPKAD','expression'=>'bku_penerimaan_manual_skpd - bku_pengeluaran_manual_bpkad','inputs'=>['bku_penerimaan_manual_skpd','bku_pengeluaran_manual_bpkad']],
            ['code'=>'REV-002','name'=>'Selisih akumulasi vs pendapatan non-RKUD','expression'=>'selisih_akumulasi - pendapatan_non_rkud','inputs'=>['selisih_akumulasi','pendapatan_non_rkud']],
            ['code'=>'REV-003','name'=>'Pendapatan non-RKUD vs komponennya','expression'=>'pendapatan_non_rkud - pendapatan_non_rkud_derived','inputs'=>['pendapatan_non_rkud','pendapatan_non_rkud_derived']],
            ['code'=>'REV-004','name'=>'STBP dikurangi STS menghasilkan sisa','expression'=>'stbp - sts - sisa_stbp','inputs'=>['stbp','sts','sisa_stbp']],
            ['code'=>'REV-005','name'=>'Akumulasi BKU penerimaan SIPD','expression'=>'akumulasi_bku_sipd_penerimaan - saldo_sebelumnya_sipd_penerimaan - bku_penerimaan_sipd_bulanan','inputs'=>['akumulasi_bku_sipd_penerimaan','saldo_sebelumnya_sipd_penerimaan','bku_penerimaan_sipd_bulanan']],
            ['code'=>'REV-006','name'=>'Akumulasi BKU pengeluaran SIPD','expression'=>'akumulasi_bku_sipd_pengeluaran - saldo_sebelumnya_sipd_pengeluaran - bku_pengeluaran_sipd_bulanan','inputs'=>['akumulasi_bku_sipd_pengeluaran','saldo_sebelumnya_sipd_pengeluaran','bku_pengeluaran_sipd_bulanan']],
            ['code'=>'REV-007','name'=>'Saldo SIPD per bulan','expression'=>'bku_penerimaan_sipd_bulanan - bku_pengeluaran_sipd_bulanan - saldo_sipd_bulanan','inputs'=>['bku_penerimaan_sipd_bulanan','bku_pengeluaran_sipd_bulanan','saldo_sipd_bulanan']],
            ['code'=>'REV-008','name'=>'LPJ penerimaan vs BKU SIPD penerimaan','expression'=>'lpj_penerimaan - bku_penerimaan_sipd_bulanan','inputs'=>['lpj_penerimaan','bku_penerimaan_sipd_bulanan']],
            ['code'=>'REV-009','name'=>'LPJ penerimaan dikurangi penyetoran menghasilkan sisa','expression'=>'lpj_penerimaan - lpj_penyetoran - sisa_lpj','inputs'=>['lpj_penerimaan','lpj_penyetoran','sisa_lpj']],
            ['code'=>'REV-010','name'=>'Register SIPD: STS + sisa = LPJ penyetoran','expression'=>'sts + sisa_stbp - lpj_penyetoran','inputs'=>['sts','sisa_stbp','lpj_penyetoran']],
        ];

        foreach ($revenueRules as $rule) {
            ReconciliationRule::updateOrCreate(
                ['accounting_year_id'=>null,'code'=>$rule['code'],'version'=>'1'],
                [
                    'calculation_definition_id'=>null,
                    'name'=>$rule['name'],
                    'category'=>'revenue',
                    'scope'=>'source_record',
                    'status'=>'active',
                    'expression'=>$rule['expression'],
                    'tolerance'=>0,
                    'input_metrics'=>$rule['inputs'],
                    'metadata'=>[
                        'source'=>'docs/input/rekonsiliasi pendapatan.xlsx',
                        'expected'=>0,
                    ],
                ]
            );
        }


        $crossSourceRules = [
            [
                'code' => 'ACC-001',
                'name' => 'LRA pendapatan vs Buku Besar pendapatan',
                'category' => 'accounting',
                'left' => ['source_type' => 'ledger', 'canonical_metrics' => ['lra_revenue']],
                'right' => ['source_type' => 'financial_statement', 'canonical_metrics' => ['lra_revenue'], 'period_mode' => 'annual_snapshot', 'skpd_mode' => 'exact'],
            ],
            [
                'code' => 'ACC-002',
                'name' => 'LRA belanja vs Buku Besar belanja',
                'category' => 'accounting',
                'left' => ['source_type' => 'ledger', 'canonical_metrics' => ['lra_expenditure']],
                'right' => ['source_type' => 'financial_statement', 'canonical_metrics' => ['lra_expenditure'], 'period_mode' => 'annual_snapshot', 'skpd_mode' => 'exact'],
            ],
            [
                'code' => 'ACC-003',
                'name' => 'Surplus/(Defisit) LO vs LPE',
                'category' => 'accounting',
                'left' => ['source_type' => 'financial_statement', 'canonical_metrics' => ['lo_surplus_deficit'], 'period_mode' => 'annual_snapshot', 'skpd_mode' => 'exact'],
                'right' => ['source_type' => 'financial_statement', 'canonical_metrics' => ['lpe_surplus_deficit'], 'period_mode' => 'annual_snapshot', 'skpd_mode' => 'exact'],
            ],
            [
                'code' => 'ACC-004',
                'name' => 'Ekuitas Neraca vs Ekuitas Akhir LPE',
                'category' => 'accounting',
                'left' => ['source_type' => 'financial_statement', 'canonical_metrics' => ['balance_equity'], 'period_mode' => 'annual_snapshot', 'skpd_mode' => 'exact'],
                'right' => ['source_type' => 'financial_statement', 'canonical_metrics' => ['lpe_ending_equity'], 'period_mode' => 'annual_snapshot', 'skpd_mode' => 'exact'],
            ],
        ];

        foreach ($crossSourceRules as $rule) {
            ReconciliationRule::updateOrCreate(
                ['accounting_year_id' => null, 'code' => $rule['code'], 'version' => '1'],
                [
                    'calculation_definition_id' => null,
                    'name' => $rule['name'],
                    'category' => $rule['category'],
                    'scope' => 'skpd_month',
                    'status' => 'active',
                    'expression' => 'left - right',
                    'tolerance' => 0,
                    'input_metrics' => [],
                    'metadata' => [
                        'source' => 'canonical accounting mapping',
                        'left' => $rule['left'],
                        'right' => $rule['right'],
                        'expected' => 0,
                    ],
                ]
            );
        }

        foreach ($rules as $rule) {
            $calculation = CalculationDefinition::query()
                ->where('code', $rule['calculation'])
                ->whereNull('accounting_year_id')
                ->where('version', '1')
                ->firstOrFail();

            ReconciliationRule::updateOrCreate(
                ['accounting_year_id' => null, 'code' => $rule['code'], 'version' => '1'],
                [
                    'calculation_definition_id' => $calculation->id,
                    'name' => $rule['name'],
                    'category' => 'expenditure',
                    'scope' => 'source_record',
                    'status' => 'active',
                    'expression' => $rule['expression'],
                    'tolerance' => 0,
                    'input_metrics' => $rule['inputs'],
                    'metadata' => [
                        'source' => 'docs/input/rekonsiliasi pengeluaran.xlsx',
                        'expected' => 0,
                    ],
                ]
            );
        }
    }
}

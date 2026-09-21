<?php

namespace App\Services;

use App\Models\AccountingYear;
use App\Models\FinancialFact;
use App\Models\Skpd;
use App\Models\SourceDocument;
use App\Models\SourceRecord;
use Illuminate\Support\Collection;

class RevenueReconciliationParser implements SourceWorkbookParser
{
    private const MONTHS = [
        'JANUARI'=>1,'FEBRUARI'=>2,'MARET'=>3,'APRIL'=>4,'MEI'=>5,'JUNI'=>6,
        'JULI'=>7,'AGUSTUS'=>8,'SEPTEMBER'=>9,'OKTOBER'=>10,'NOVEMBER'=>11,'DESEMBER'=>12,
    ];

    public function supports(string $type): bool { return $type === 'revenue_reconciliation'; }

    public function parse(Collection $sheets, SourceDocument $document, int $year, ?int $month = null): int
    {
        $yearId=AccountingYear::query()->where('year',$year)->value('id');
        if(!$yearId) throw new \InvalidArgumentException("Tahun {$year} tidak ditemukan.");

        $records=0;
        foreach($sheets as $sheetName=>$rows){
            $monthColumns=$this->monthColumns($rows);
            if(!$monthColumns) continue;
            $skpd=$this->resolveSkpd($rows);
            $selected=$month!==null ? array_filter($monthColumns,fn($m)=>$m===$month,true) : $monthColumns;

            foreach($selected as $column=>$monthNumber){
                $facts=[];
                $payload=['sheet_name'=>(string)$sheetName,'skpd'=>$skpd?->name,'month'=>$monthNumber];

                foreach($rows as $rowIndex=>$row){
                    $label=$this->label($row);
                    if($label==='') continue;
                    $value=$row[$column]??null;
                    if($value===null || $value==='') continue;

                    $metric=$this->metricForLabel($label);
                    if($metric===null) continue;

                    $numeric=$this->number($value);
                    if($numeric===null && !is_bool($value)) continue;

                    $payload[$metric]=$numeric ?? ($value ? 1 : 0);
                    $facts[]=['metric'=>$metric,'value'=>$numeric ?? ($value ? 1 : 0),'unit'=>$numeric===null?'BOOLEAN':'IDR','source_row'=>$rowIndex+1,'label'=>$label];
                }

                if(!$facts) continue;

                $record=SourceRecord::create([
                    'source_document_id'=>$document->id,
                    'source_row'=>1,
                    'sheet_name'=>(string)$sheetName,
                    'record_type'=>'revenue_reconciliation_month',
                    'payload'=>$payload,
                ]);

                foreach($facts as $fact){
                    FinancialFact::create([
                        'source_document_id'=>$document->id,'source_record_id'=>$record->id,
                        'accounting_year_id'=>$yearId,'skpd_id'=>$skpd?->id,
                        'period'=>sprintf('%04d-%02d',$year,$monthNumber),'month'=>$monthNumber,
                        'source_type'=>'revenue_reconciliation','transaction_type'=>'REVENUE_RECON',
                        'account_code'=>null,'metric'=>$fact['metric'],'value'=>$fact['value'],
                        'unit'=>$fact['unit'],
                        'dimensions'=>['sheet_name'=>$sheetName,'source_row'=>$fact['source_row'],'label'=>$fact['label'],'origin'=>'reported'],
                        'lineage'=>['origin'=>'reported','source_document_id'=>$document->id,'source_record_id'=>$record->id,'sheet_name'=>$sheetName,'source_row'=>$fact['source_row'],'label'=>$fact['label'],'month'=>$monthNumber],
                    ]);
                }
                $records++;
            }
        }
        return $records;
    }

    private function monthColumns(Collection $rows): array
    {
        foreach($rows->take(20) as $row){
            foreach($row as $index=>$value){
                $key=mb_strtoupper(trim((string)($value??'')));
                if(isset(self::MONTHS[$key])){
                    $result=[];
                    foreach($row as $i=>$v){
                        $m=self::MONTHS[mb_strtoupper(trim((string)($v??'')))]??null;
                        if($m!==null) $result[$i]=$m;
                    }
                    return $result;
                }
            }
        }
        return [];
    }

    private function resolveSkpd(Collection $rows): ?Skpd
    {
        foreach($rows->take(15) as $row){
            if(mb_strtolower(trim((string)($row[0]??'')))==='skpd'){
                $name=trim((string)($row[2]??''));
                return $name!=='' ? Skpd::query()->where('name',$name)->first() : null;
            }
        }
        return null;
    }

    private function label(array|Collection $row): string
    {
        return trim((string)($row[0]??''));
    }

    private function metricForLabel(string $label): ?string
    {
        $map=[
            'Saldo Bulan Lalu'=>'saldo_bulan_lalu',
            'BKU PENERIMAAN MANUAL (SKPD)'=>'bku_penerimaan_manual_skpd',
            'BKU PENGELUARAN MANUAL (BPKAD)'=>'bku_pengeluaran_manual_bpkad',
            'Selisih Akumulasi'=>'selisih_akumulasi',
            'Pendapatan NON RKUD'=>'pendapatan_non_rkud',
            'BLUD'=>'blud','JKN'=>'jkn','BOS'=>'bos','BOK'=>'bok',
            'STS (yg punya no. STS)'=>'sts',
            'STBP (total semua, termasuk yg di STS kan)'=>'stbp',
            'Sisa'=>'sisa',
            'Saldo Sebelumnya (SIPD) Penerimaan'=>'saldo_sebelumnya_sipd_penerimaan',
            'Akumulasi BKU B.Pen (SIPD) Penerimaan'=>'akumulasi_bku_sipd_penerimaan',
            'BKU B.Pen (SIPD) Penerimaan per Bln'=>'bku_penerimaan_sipd_bulanan',
            'Saldo Sebelumnya (SIPD) Pengeluaran'=>'saldo_sebelumnya_sipd_pengeluaran',
            'Akumulasi BKU B.Pen (SIPD) Pengeluaran'=>'akumulasi_bku_sipd_pengeluaran',
            'BKU B.Pen (SIPD) Pengeluaran per Bln'=>'bku_pengeluaran_sipd_bulanan',
            'Saldo per Bln'=>'saldo_sipd_bulanan',
            'LPJ Penerimaan'=>'lpj_penerimaan',
            'LPJ Penyetoran'=>'lpj_penyetoran',
            'Pengembalian Pendapatan Tahun Berjalan'=>'pengembalian_pendapatan_tahun_berjalan',
            'Pengembalian Pendapatan Tahun Lalu (BTT)'=>'pengembalian_pendapatan_tahun_lalu_btt',
            'Register SIPD'=>'register_sipd_check',
            'LPJ SIPD'=>'lpj_sipd_check',
        ];
        return $map[$label]??null;
    }

    private function number(mixed $value): ?float
    {
        if(is_int($value)||is_float($value)) return (float)$value;
        if($value===null||trim((string)$value)==='') return null;
        $text=trim((string)$value);
        if(!preg_match('/^-?[0-9][0-9.,]*$/',$text)) return null;
        if(str_contains($text,',')&&str_contains($text,'.')){
            $text=strrpos($text,',')>strrpos($text,'.') ? str_replace(',', '.', str_replace('.','',$text)) : str_replace(',','',$text);
        }elseif(str_contains($text,',')){
            $parts=explode(',',$text);
            $text=count($parts)===2&&strlen($parts[1])<=2 ? $parts[0].'.'.$parts[1] : str_replace(',','',$text);
        }elseif(substr_count($text,'.')>1) $text=str_replace('.','',$text);
        return is_numeric($text)?(float)$text:null;
    }
}

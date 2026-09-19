# Source Ingestion & Reconciliation Map

Status: reverse-engineering baseline from the supplied `docs(1).zip`.

## 1. Purpose

The application consumes the original workbooks under `input/`. Users do not manually key individual authorization records as the primary workflow.

The intended pipeline is:

```
Original workbook
    ↓
Document ingestion
    ↓
Document type identification
    ↓
Type-specific parser
    ↓
Raw extracted records
    ↓
Normalized financial facts
    ↓
Calculated / aggregated metrics
    ↓
Reconciliation rules
    ↓
Match / variance / data-quality result
    ↓
Review
    ↓
Berita Acara
    ↓
Immutable snapshot
```

The original file remains immutable. Parsed and normalized data are derived representations and must retain source lineage.

## 2. Source inventory

### 2.1 Rekonsiliasi workbooks

| File | Observed structure | Initial semantic role | Confidence |
|---|---|---|---|
| `input/rekonsiliasi pendapatan.xlsx` | 18 sheets; SKPD sheets plus `LIST`; each SKPD sheet contains BA-like header and financial rows | Revenue reconciliation template / expected comparison dimensions | High |
| `input/rekonsiliasi pengeluaran.xlsx` | 60 x 24 worksheet | Expenditure reconciliation template / expected comparison dimensions | High |

Important: these two workbooks are evidence of the reconciliation presentation and calculation model. They should not automatically be treated as raw transaction sources.

### 2.2 Buku besar / jurnal

| File | Observed structure | Initial semantic role |
|---|---|---|
| `input/buku besar jurnal/buku_jurnal_kab_kediri_kecamatan_pare.xlsx` | 3,191 x 8 | Journal transaction source |
| `input/buku besar jurnal/buku-besar-kecamatan-pare-pendapatan.xlsx` | 55 x 9 | SKPD revenue ledger source |
| `input/buku besar jurnal/buku-besar-kecamatan-pare-belanja.xlsx` | 555 x 9 | SKPD expenditure ledger source |
| `input/buku besar jurnal/buku-besar-konsolidasi-pendapatan.xls` | converted for inspection; semester 1/2 sheets | Consolidated revenue ledger source |
| `input/buku besar jurnal/buku-besar-konsolidasi-belanja.xls` | converted for inspection; semester 1/2 sheets | Consolidated expenditure ledger source |

The consolidated workbooks have headers identifying `BUKU BESAR`, `KONSOLIDASI`, and account groups such as `KODE REKENING : 4` / `KODE REKENING : 5`.

### 2.3 Financial statements

| File | Observed structure | Initial semantic role |
|---|---|---|
| `input/laporan keuangan/lra-kecamatan-pare-kab-kediri.xlsx` | 148 x 6 | LRA source |
| `input/laporan keuangan/neraca-kecamatan-pare-kab-kediri.xlsx` | 57 x 4 | Balance sheet / Neraca source |
| `input/laporan keuangan/laporan-operasional-kab-kediri-kecamatan-pare-2026.xlsx` | 132 x 6 | LO source |
| `input/laporan keuangan/lpe_Kecamatan Pare_kab-kediri_2026.xlsx` | 24 x 7 | LPE source |
| `input/laporan keuangan/lra-program-kecamatan-pare-kab-kediri.xlsx` | appears empty | No usable data identified yet |

### 2.4 Kertas kerja konsolidasi

| File | Observed structure | Initial semantic role |
|---|---|---|
| `input/kertas kerja/kertas-kerja-lra.xlsx` | 814 x 196 | LRA consolidation / cross-SKPD comparison |
| `input/kertas kerja/kertas-kerja-lo.xlsx` | 582 x 196 | LO consolidation / cross-SKPD comparison |
| `input/kertas kerja/kertas-kerja-neraca.xlsx` | 195 x 194 | Neraca consolidation / cross-SKPD comparison |
| `input/kertas kerja/kertas-kerja-lpe.xlsx` | 5 x 193 | LPE consolidation |

The LRA/LO/Neraca workbooks use paired columns per entity/SKPD. The LPE workbook contains year columns and values such as `SURPLUS / (DEFISIT) - LO`, `RK PPKD`, and `EKUITAS AKHIR`.

These workbooks are primarily downstream/aggregation evidence. They should not be used as the only source of truth when transaction-level sources are available.

### 2.5 Non-RKUD / pengesahan source workbooks

The word "pengesahan" describes a source category in the data flow; it is **not a manual input menu**.

| File | Observed sheets | Initial semantic role |
|---|---|---|
| `input/pengesahan/BOK Puskesmas.xlsx` | `TabelSP2T`, `Cetak SP2T BOK`, `TabelSPB`, `Cetak SPB BOK` | BOK source |
| `input/pengesahan/BLUD Puskesmas.xlsx` | `Tabel SP2BP`, shared reference sheets, print sheets, correction sheet | BLUD Puskesmas source |
| `input/pengesahan/BLUD RSUD SLG.xlsx` | `BLUD-SLG`, shared reference sheets, print sheets, month reference | BLUD RSUD source |
| `input/pengesahan/BLUD RSKK.xlsx` | `BLUD-RSKK`, shared reference sheets, print sheets, correction print sheet | BLUD RSKK source |
| `input/pengesahan/BOSP.xlsx` | `TabelSPB`, `Cetak SPB`, `TabelSP2T`, `Cetak SP2T BOS`, `Referensi` | BOSP source |
| `input/pengesahan/Dana Desa.xlsx` | `BLUD`, `BOK`, `BOSP`, `Data DD`, print sheets | Dana Desa source plus shared reference data |
| `input/pengesahan/Tambahan Penghasilan Guru.xlsx` | `TabelSPB`, `Cetak SPB`, `TabelSP2T`, `Cetak SP2T`, `Referensi` | Tamsil source |
| `input/pengesahan/Tunjangan Profesi Guru.xlsx` | same structural family as Tamsil | TPG source |

Observed TPG/Tamsil structure provides strong evidence that source data should be parsed from table sheets rather than from the print sheets. For example, `TabelSP2T` has fields including:

- No
- Nomor SP2D BUN
- Tanggal SP2D BUN
- Bulan
- Jenis Pendapatan
- Nomor SP2T
- Tanggal SP2T
- Penerimaan Brutto

The `Referensi` sheet contains source-fund and account mappings, including `KODE SUMBER DANA`, `NAMA SUMBER DANA`, `KODE REKENING`, `NAMA REKENING`, and `PAGU`.

This is evidence for a reusable parser family, but exact field mappings for every workbook must still be validated from the workbook contents before rules are finalized.

## 3. Source vs calculation vs presentation

A key distinction:

### Source data

Data imported from an external workbook that represents an underlying transaction, journal, ledger, statement, or non-RKUD transaction.

Examples:
- journal rows;
- SP2D/SPJ/STS rows;
- SPB/SP2T/SP2BP rows;
- ledger rows;
- financial statement rows.

### Derived facts

Normalized values extracted or calculated from source data.

Examples:
- total SP2D LS;
- total SPJ LS;
- revenue receipt by period;
- deposit by period;
- ledger debit/credit totals;
- statement balance;
- non-RKUD gross receipt.

### Reconciliation result

A comparison between derived facts from two or more sources.

Examples:
- Source A total;
- Source B total;
- difference;
- status;
- rule code;
- tolerance;
- evidence/lineage.

### Presentation

Existing BA/reconciliation/kertas-kerja workbooks are useful evidence for what humans expect to see, but the application should calculate from normalized data instead of reproducing spreadsheet formulas blindly.

## 4. Required data lineage

Every imported/derived value must be traceable:

```
reconciliation result
  ↓
calculation / rule
  ↓
normalized fact(s)
  ↓
source record(s)
  ↓
source dataset
  ↓
original uploaded file + checksum
```

A final BA must additionally snapshot the relevant source versions, rule versions, results, SKPD values, and official values.

## 5. Document type detection

Do not identify a workbook solely by filename.

The detector should consider:

1. expected sheet names;
2. sheet-name patterns;
3. recognizable header labels;
4. structural dimensions;
5. known document markers;
6. source-specific signatures.

Examples:

- workbook containing `TabelSP2T` + `Cetak SP2T` is a strong non-RKUD transfer/pengesahan signature;
- workbook containing `BUKU BESAR` + `KONSOLIDASI` is a ledger/consolidated-ledger signature;
- workbook containing `KODE REKENING` + `ANGGARAN` + `REALISASI` in paired SKPD columns is a kertas-kerja LRA signature;
- workbook containing BA title/header and SKPD-named sheets is a reconciliation presentation signature.

Detection should return a document type and confidence, with a validation error when the workbook is ambiguous.

## 6. Parser architecture

The application should have a small number of explicit parser families rather than one giant Excel parser.

Conceptually:

```
DocumentImporter
  ├── RevenueReconciliationParser
  ├── ExpenditureReconciliationParser
  ├── JournalParser
  ├── LedgerParser
  ├── ConsolidatedLedgerParser
  ├── FinancialStatementParser
  ├── WorkingPaperParser
  └── NonRkudParser
        ├── BludParser
        ├── BospParser
        ├── BokParser
        ├── DanaDesaParser
        └── TpgTamsilParser
```

Where structures are genuinely shared, use a configurable mapping rather than duplicating parsing code.

## 7. Initial normalized fact model

The normalized layer should not mirror every Excel column.

A financial fact should be able to carry at least:

- source dataset;
- source record;
- fiscal year;
- period/date;
- SKPD/entity;
- source type;
- transaction/document type;
- document/reference number;
- account code;
- metric;
- amount/value;
- unit where relevant;
- dimensions needed by reconciliation;
- source lineage.

The exact column list is intentionally not frozen yet.

## 8. Initial reconciliation families

The supplied artifacts support these families:

### Expenditure

Potential comparisons include:
- SP2D LS vs SPJ LS;
- SP2D UP/GU vs SPJ UP/GU;
- SP2D TU vs SPJ TU;
- SP2D KKPD vs SPJ KKPD;
- total SP2D vs total SPJ;
- STS-related values where present.

These are source-derived checks, not manual entry fields.

### Revenue

Potential dimensions include:
- prior-period balance;
- receipts;
- deposits;
- non-RKUD revenue;
- ending balance.

The workbook evidence supports a balance-flow style reconciliation, but exact formulas and row semantics must be extracted from the workbook before implementation.

### Non-RKUD

The supplied workbooks support source families including:
- BLUD;
- BOSP;
- BOK;
- Dana Desa;
- TPG;
- Tamsil.

The system consumes their transaction/authorization data and compares it to other accounting/penatausahaan sources.

### Accounting

The source set supports comparisons among:
- journal;
- SKPD ledger;
- consolidated ledger;
- LRA;
- LO;
- Neraca;
- LPE.

These should be implemented after the source-level expenditure/revenue ingestion is stable.

## 9. What is deliberately not decided yet

The following must not be invented before the relevant source is fully mapped:

1. exact BMD/asset BA fields;
2. exact row-to-row reconciliation formulas not evidenced by source workbooks;
3. exact account classification semantics where the source does not make them explicit;
4. exact tolerance values;
5. automatic resolution of ambiguous source records;
6. overwrite semantics for a newly uploaded version;
7. any claim that a print sheet is authoritative when a table sheet exists.

## 10. Implementation consequence

The existing manual "Sumber Pengesahan" CRUD workflow is not the target product flow.

It should be replaced by:

```
Import Batch
  ├── source files
  ├── detected document types
  ├── validation
  ├── extracted records
  ├── normalized facts
  ├── calculated metrics
  └── reconciliation readiness
```

The UI should therefore evolve toward:

- **Import Data**
- **Import History / Dataset**
- **Data Quality**
- **Rekonsiliasi**
- **Selisih / Exception**
- **BA Rekon**
- administration/master data separately

The old authorization CRUD can remain temporarily as legacy code during migration, but it should not remain the primary user experience.

## 11. Next implementation sequence

1. Add source-dataset/document ingestion tables.
2. Add immutable uploaded-file metadata and checksum.
3. Add document-type detector.
4. Add raw extracted-record storage with lineage.
5. Implement one parser end-to-end using the supplied expenditure sources.
6. Implement derived financial facts and calculations.
7. Implement the first expenditure reconciliation rules.
8. Replace manual authorization UI with batch import UI.
9. Add revenue ingestion/reconciliation.
10. Add non-RKUD parser families.
11. Add accounting/working-paper reconciliation.
12. Integrate actual BMD BA after its real format is supplied.
13. Finalize BA snapshot over the resulting reconciliation state.

This order is intentional: it validates the ingestion architecture against real source data before expanding the rule set.

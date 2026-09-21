# Source-to-Row Mapping

Status: Step 2 — source mapping baseline  
Basis: supplied `docs/input` and `docs/output/ba rekonsiliasi dengan pengesahan.xlsx`

## 1. Principle

The BA workbook is treated as the reconciliation presentation/control layer. Input workbooks are treated as source datasets. A BA row is not automatically a source fact: it may be a source value, derived value, subtotal, or control result.

Mapping status:

- **MAPPED** — source dataset and semantic field are sufficiently identified.
- **PARTIAL** — source is identified, but exact field/period/grain still requires verification.
- **BLOCKED** — source format is not yet available or semantics cannot be proven from supplied files.
- **DERIVED** — value is calculated from other mapped values.

## 2. Confirmed source families

| Source family | Supplied file(s) | Grain | Initial role |
|---|---|---|---|
| Rekonsiliasi pendapatan | `input/rekonsiliasi pendapatan.xlsx` | SKPD x month | revenue/treasurer reconciliation |
| Rekonsiliasi pengeluaran | `input/rekonsiliasi pengeluaran.xlsx` | SKPD | SP2D/SPJ/STS/KAS reconciliation |
| Buku besar pendapatan | `input/buku besar jurnal/buku-besar-kecamatan-pare-pendapatan.xlsx` | journal/account transaction | revenue ledger |
| Buku besar belanja | `input/buku besar jurnal/buku-besar-kecamatan-pare-belanja.xlsx` | journal/account transaction | expenditure ledger |
| Buku jurnal | `input/buku besar jurnal/buku_jurnal_kab_kediri_kecamatan_pare.xlsx` | journal/account/document | accounting journal |
| LRA | `input/laporan keuangan/lra-kecamatan-pare-kab-kediri.xlsx` | account | LRA reporting |
| LO | `input/laporan keuangan/laporan-operasional-kab-kediri-kecamatan-pare-2026.xlsx` | account | LO reporting |
| Neraca | `input/laporan keuangan/neraca-kecamatan-pare-kab-kediri.xlsx` | account | balance-sheet reporting |
| LPE | `input/laporan keuangan/lpe_Kecamatan Pare_kab-kediri_2026.xlsx` | report line | equity reporting |
| Pengesahan | files under `input/pengesahan/` | document/month/source | non-RKUD and transfer authorization |
| Kertas kerja | files under `input/kertas kerja/` | account x entity | consolidation/workpaper |
| BMD/asset BA | not supplied in actual structured format | TBD | asset reconciliation |

## 3. BA cash receiving controls

### BA rows 12–25

| BA row | Meaning | Formula/role | Source mapping | Status |
|---:|---|---|---|---|
| 12 | Saldo Awal | prior month row 15 | BA-derived chain | DERIVED |
| 13 | Jumlah Debet | source balance activity | likely revenue ledger / treasurer data | PARTIAL |
| 14 | Jumlah Kredit | source balance activity | likely revenue ledger / treasurer data | PARTIAL |
| 15 | Saldo akhir | 12 + 13 - 14 | derived | DERIVED |
| 17 | Saldo Awal Kas | prior month row 20 | BA-derived chain | DERIVED |
| 18 | Penerimaan | monthly receipt | `rekonsiliasi pendapatan.xlsx` candidate | PARTIAL |
| 19 | Setoran | monthly deposit | `rekonsiliasi pendapatan.xlsx` candidate | PARTIAL |
| 20 | Kas (BKU) | cash balance | treasurer source not yet isolated | PARTIAL |
| 21 | Selisih Debet dan Penerimaan | 13 - 18 | control | DERIVED |
| 22 | Selisih Kredit dan Setoran | 14 - 19 | control | DERIVED |
| 23 | Selisih Kas BKU dan Neraca | 15 - 20 | control | DERIVED |
| 24 | Pendapatan LRA | monthly LRA revenue proxy | 2025 BA formula references row 19 in Jan and row 18 in later months | PARTIAL |
| 25 | Selisih Penerimaan dan LRA | 18 - 24 | control | DERIVED |

Important: the source workbook `rekonsiliasi pendapatan.xlsx` contains the same monthly reconciliation concept, but the exact cell-to-cell mapping must be verified before production parser rules are created.

## 4. Revenue aggregation

| BA row | Meaning | Formula | Source |
|---:|---|---|---|
| 39 | Pendapatan transfer non BOS BOK | source value | authorization / transfer source; exact workbook mapping pending |
| 40 | Pendapatan BOS | source value | `input/pengesahan/BOSP.xlsx` / related sources |
| 41 | Pendapatan BOK | source value | `input/pengesahan/BOK Puskesmas.xlsx` / related sources |
| 43 | LRA Pendapatan per Bulan | 41 + 40 + 19 + 39 | DERIVED |
| 44 | LRA Pendapatan Akumulatif | cumulative row 43 | DERIVED |

## 5. LPJ reliability

| BA row | Meaning | Source |
|---:|---|---|
| 52 | SP2D LS | expenditure/ledger source |
| 53 | Total TBP UP+GU+TU | expenditure/treasurer source |
| 54 | Pengembalian UP | expenditure/STS/CP source |
| 55 | TOTAL LPJ | source or derived aggregate |
| 56 | Selisih | 55 - (52 + 53 - 54) |

This control is **PARTIAL** at source level because the supplied `rekonsiliasi pengeluaran.xlsx` is a consolidated SKPD-level table and does not expose the same monthly TBP decomposition used by the BA.

## 6. Expenditure cash controls

| BA row | Meaning | Formula | Source |
|---:|---|---|---|
| 60 | Jumlah Debet | source activity | ledger/BKU source, exact mapping pending |
| 61 | Jumlah Kredit | source activity | ledger/BKU source, exact mapping pending |
| 62 | Saldo akhir | 59 + 60 - 61 | DERIVED |
| 65 | SP2D UP+GU+TU+LS | source aggregate | `rekonsiliasi pengeluaran.xlsx` contains SP2D components |
| 66 | TBP UP+GU+TU+LS | equals row 55 | DERIVED / LPJ |
| 67 | RC Bendahara Pengeluaran dan BPP | source cash reconciliation | exact source pending |
| 68 | Selisih Debet dan Penerimaan | 60 - 65 + 52 | DERIVED |
| 69 | Selisih Kredit dan Setoran | 61 - 66 + 52 | DERIVED |
| 70 | Selisih RC BP dan Neraca | 62 - 67 | DERIVED |

The supplied expenditure reconciliation workbook is confirmed to contain the following normalized SKPD-level source metrics:

- SP2D LS
- SP2D UP/GU
- SP2D TU
- SP2D KKPD
- TOTAL SP2D
- SPJ LS
- SPJ UP/GU
- SPJ TU
- SPJ KKPD
- TOTAL SPJ
- STS UP/GU
- STS TU
- CP LS
- CP UP/GU
- CP TU
- TOTAL STS
- KAS SIPD
- KAS BANK
- KAS TUNAI
- SELISIH

This source is therefore a strong candidate for the first production parser.

## 7. LRA

The BA's LRA section is a monthly/accumulative reporting model.

Key calculations:

- row 78: `TOTAL LPJ - TOTAL PENGEMBALIAN`
- row 79: cumulative LRA plus Dana Desa
- rows 102, 110, 114, 119, 121, 126, 128: category/subtotal calculations
- row 136: total operating expenditure
- row 145: total capital expenditure
- row 149: unexpected expenditure
- row 155: transfer expenditure
- row 157: total expenditure
- row 159: surplus/deficit

The supplied LRA workbook is a **report-level source** with:

- account code
- description
- budget
- realization 2026
- percentage
- prior-year realization

It can support cross-checking against LRA report totals, but it is not sufficient by itself to reconstruct all monthly BA rows.

## 8. LO and balance sheet

The supplied LO workbook is a report-level source with:

- account code
- description
- 2026
- 2025
- change
- percentage

The supplied Neraca workbook is a report-level source with:

- account code
- description
- 2026
- 2025

The BA contains aggregate LO and balance-sheet controls. Exact line-to-line mapping must be established using account codes and labels rather than positional row assumptions.

## 9. Accounting ledger/journal

The supplied ledger and journal workbooks are structurally stronger source candidates than report-level spreadsheets because they contain:

- date
- journal/document references
- account code
- description
- debit
- credit
- running balance

The consolidated journal contains multiple accounting entries per document and should therefore be treated as transaction-level source data, not as a pre-aggregated fact table.

## 10. Authorization / pengesahan

The supplied workbooks demonstrate multiple source formats:

- BOSP
- BOK
- BLUD
- Dana Desa
- Tamsil
- TPG

Examples include structured tables such as:

- TabelSPB
- TabelSP2T
- Tabel SP2BP
- Referensi

These should be parsed by workbook family. They must not be collapsed into a generic manual authorization input.

## 11. BMD / asset controls

The BA has explicit asset reconciliation rows for:

- Tanah
- Peralatan dan Mesin
- Gedung dan Bangunan
- Jalan, Jaringan, dan Irigasi
- Aset Tetap Lainnya
- Aset Lainnya

However, the actual structured BA Rekon Aset/BMD source is not supplied in the input set. These mappings remain **BLOCKED** until the actual format is provided.

## 12. First implementation target

The first complete end-to-end source should be:

```
rekonsiliasi pengeluaran.xlsx
        ↓
content detector
        ↓
expenditure parser
        ↓
source records
        ↓
financial facts
        ↓
calculation definitions
        ↓
consistency rules
        ↓
reconciliation results
```

This provides a bounded vertical slice before implementing the more heterogeneous workbook families.

## 13. Do not infer

The following must not be inferred without evidence:

- exact meaning of a BA value merely from its position;
- BMD fields;
- tolerance values other than those explicitly documented;
- whether a non-zero variance is automatically invalid;
- source lineage of BA values that are manually pasted;
- semantics of free-text explanations.



## 14. Period semantics for cross-source controls

Cross-source reconciliation must not treat the requested BA month and the source file's stored period as interchangeable. Rules therefore declare an explicit `period_mode` per side:

| Mode | Meaning |
|---|---|
| `MONTHLY` | facts belonging to the requested month only |
| `YEAR_TO_DATE` | monthly facts from January through the requested month |
| `ANNUAL_SNAPSHOT` | annual/closing facts whose imported month is NULL |
| `ANY` | no period restriction |
| `OPENING_BALANCE` | opening snapshot facts whose imported month is NULL |
| `PRIOR_YEAR` | reserved for an explicitly supplied prior-year fact set; no implicit year inference |

The implementation intentionally does not guess a prior-year source or reinterpret a NULL month as a monthly value.

### Accounting category safeguard

The current supplied financial-statement workbooks are imported as annual/snapshot facts (month NULL). Therefore the `accounting` reconciliation category is currently **annual-only**. A monthly accounting run is rejected rather than comparing an annual statement with a single monthly ledger slice.

This is a correctness safeguard, not a business rule about how the final system must operate. If monthly LRA/LO/Neraca/LPE snapshots are later supplied, the rule metadata can be changed to use `MONTHLY` or `YEAR_TO_DATE` semantics after the source format is verified.

### Duplicate comparison safeguard

A cross-source rule produces one comparison per SKPD reconciliation grain, not one comparison for every source-document grain. This prevents the same SKPD/month control from being repeated merely because both sides contain multiple source documents.



## 15. Report scope

Financial-statement workbooks are classified at import time:

- `official_report): supplied LRA, LO, Neraca, and LPE report exports.
- `working_paper`: supplied `kertas-kerja-*.xlsx` workbooks.
- `financial_statement_unknown`: financial-statement workbook whose filename cannot yet be classified safely.

Accounting cross-source controls currently use only `official_report`. Working papers remain imported and available for later controls, but are not silently treated as another copy of the official report.

This distinction is important because the working papers contain entity-level/consolidation columns and can represent intermediate calculation layers. Including them in the same aggregate as the official report could duplicate amounts or compare different reporting grains.


### Fact-level lineage

The import pipeline now copies `report_scope` into each financial fact's dimensions. Cross-source controls prefer this fact-level lineage and fall back to the source document metadata when older facts do not contain it. This keeps source selection traceable without changing the raw financial value.

# Reconciliation Rule Map

Status: Step 1 — reverse-engineering baseline  
Source workbook: `docs/output/ba rekonsiliasi dengan pengesahan.xlsx`  
Primary sheet reviewed: `2025`

## 1. Purpose

This document records the reconciliation logic that is explicitly present in the supplied BA workbook. It is a source-derived specification, not a proposal to change the existing accounting process.

The implementation must distinguish:

- **Source value** — a value supplied by an input dataset.
- **Derived value** — a value calculated from source values.
- **Control result** — a comparison that produces a status such as BENAR/SALAH.
- **Explanation** — human-entered/contextual information explaining an exception.

A formula must not be implemented as a reconciliation rule until its referenced rows have been mapped to source datasets.

## 2. Workbook structure

The 2025 sheet contains these major sections:

1. Buku Besar Kas di Bendahara Penerimaan
2. Rekon Penatausahaan
3. Buku Besar Piutang
4. Surat Pengesahan Pendapatan
5. Pendapatan BOS/BLUD yang dicatat di BPKAD
6. Crosscheck Keandalan LPJ
7. Buku Besar Kas di Bendahara Pengeluaran
8. Rekon Penatausahaan
9. Belanja LRA
10. LRA
11. LO
12. Cross-check pendapatan RKUD / belanja SPJ
13. KKPD Akuntansi / reconciliation with BMD data

## 3. Explicit control formulas

### 3.1 Cash — receiving treasurer

| Rule candidate | Formula | Expected |
|---|---|---:|
| Ending balance | `Saldo Awal + Jumlah Debet - Jumlah Kredit` | calculated |
| Debit vs receipt | `Jumlah Debet - Penerimaan + Kasda` | 0 |
| Credit vs deposit | `Jumlah Kredit - Kas Bendahara Penerimaan` | 0 |
| BKU cash vs balance sheet | `Saldo akhir - Kas (BKU)` | 0 |
| Receipt vs LRA | `Penerimaan - Pendapatan LRA` | 0 |

These are present in rows 15 and 22–26.

### 3.2 Monthly and cumulative LRA revenue

| Row | Formula | Meaning |
|---:|---|---|
| 46 | `Penerimaan + Dana Desa + transfer non-BOS/BOK + BOS + BOK + TPG` | Monthly LRA revenue |
| 47 | cumulative SUM of row 46 | Cumulative LRA revenue |

The row labels and formulas are explicit in the source workbook.

### 3.3 LPJ reliability cross-check

Rows 55–61 contain:

- SP2D LS
- Total TBP UP+GU+TU
- GU KKPD
- TBP KKPD
- Pengembalian UP
- TOTAL LPJ
- Selisih

The explicit formula is:

`TOTAL LPJ - (SP2D LS + Total TBP UP+GU+TU - Pengembalian UP) - TBP KKPD`

The expected value for the Selisih control is 0.

### 3.4 Cash — expenditure treasurer

Rows 64–75 contain a second cash reconciliation.

Explicit formulas include:

- Ending balance = Saldo Awal + Jumlah Debet - Jumlah Kredit
- Selisih Debet dan Penerimaan = Jumlah Debet - SP2D (UP+GU+TU+LS) + SP2D LS
- Selisih Kredit dan Setoran = Jumlah Kredit - TBP (UP+GU+TU+LS) + SP2D LS
- Selisih RC BP dan Neraca = Saldo akhir - RC Bendahara Pengeluaran dan BPP

Expected value for each numeric selisih control is 0.

The source also contains free-text explanations of exceptions. These explanations are evidence that a non-zero result can be explainable; therefore **non-zero must not automatically mean the reconciliation is invalid**.

### 3.5 LRA expenditure

Rows 89–93 define:

- Belanja LRA = LRA Bulanan
- LRA Bulanan = TOTAL LPJ - Total Pengembalian
- LRA Akumulatif = cumulative prior-period values + current-period LRA values

### 3.6 LRA aggregate calculations

The workbook contains explicit subtotal formulas for:

- PAD
- Pendapatan Transfer Dana Perimbangan
- Pendapatan Transfer Pemerintah Pusat - Lainnya
- Pendapatan Transfer Antar Daerah
- Total Pendapatan Transfer
- Lain-lain Pendapatan Daerah yang Sah
- Jumlah Pendapatan
- Belanja Operasi
- Belanja Modal
- Belanja Tidak Terduga
- Belanja Transfer
- Jumlah Belanja
- Surplus/Defisit
- Pembiayaan
- SiLPA

These are **calculation definitions**, not necessarily cross-source reconciliation tests.

## 4. Cross-source reconciliation controls

### 4.1 LRA vs RKUD

Rows 174–175:

`LRA Pendapatan Akumulatif - Jumlah Pendapatan LRA`

The workbook expresses row 174 as:

`IF(LRA Pendapatan Akumulatif - Jumlah Pendapatan = 0, "BENAR", "SALAH")`

Therefore:

- numeric variance = row 175
- control status = BENAR when variance = 0

This is a cross-source control candidate because the surrounding labels distinguish RKUD/penatausahaan from LRA.

### 4.2 LRA expenditure vs SPJ

Rows 178–179:

`Jumlah Belanja - LRA Akumulatif`

Status:

`IF(Jumlah Belanja - LRA Akumulatif = 0, "BENAR", "SALAH")`

Numeric variance is row 179.

**Important:** the row label says “Selisih Belanja dengan SPJ”, but the formula references the LRA aggregate row 93. The implementation should preserve the source formula and label this mapping as requiring source-lineage verification rather than silently renaming it.

## 5. LO controls

The LO section contains the same style of aggregation:

- Pendapatan-LO by category
- Total Pendapatan-LO
- Beban by category
- Total Beban
- Surplus/Defisit
- Pembiayaan
- SiLPA

Rows 251–256 contain explicit cross-source formulas:

### Revenue

`Jumlah Pendapatan LRA - Jumlah Pendapatan-LO - cumulative supporting row 38`

Status is BENAR when the result equals 0.

### Expenditure

`Jumlah Beban - Jumlah Belanja LRA + Jumlah Belanja Modal + cumulative supporting row 38`

Status is BENAR when the result equals 0.

These formulas should be implemented only after row 38 and the relevant LRA/LO source values have been mapped to actual input files.

## 6. BMD / asset reconciliation

Rows 258 onward identify future/actual reconciliation subjects:

- Buku besar Persediaan
- Belanja persediaan
- Beban Persediaan
- Belanja Modal Tanah
- Pengadaan Tanah di BA Rekon Aset
- Belanja Modal Peralatan dan Mesin
- Pengadaan Peralatan dan Mesin di BA Rekon Aset
- Belanja Modal Gedung dan Bangunan
- Pengadaan Gedung dan Bangunan di BA Rekon Aset
- Belanja Modal Jalan, Jaringan, dan Irigasi
- Pengadaan Jalan, Jaringan, dan Irigasi di BA Rekon Aset
- Belanja Modal Aset Tetap Lainnya
- Pengadaan Aset Tetap Lainnya di BA Rekon Aset
- Belanja Modal Aset Lainnya
- Pengadaan Aset Lainnya di BA Rekon Aset

However, the supplied materials do not yet include the actual BA Rekon Aset/BMD source format. Therefore these rows are **not yet implementable as structured source parsers or production rules**.

Do not invent BMD fields.

## 7. Important semantic finding

The workbook proves that the reconciliation process contains at least three different kinds of logic:

```
SOURCE VALUES
    ↓
AGGREGATION / CALCULATION
    ↓
CROSS-SOURCE COMPARISON
    ↓
CONTROL RESULT
    ↓
EXPLANATION / REVIEW
```

Consequently, a single `financial_facts` table is useful for normalized values, but the reconciliation engine must also retain:

- rule identity/version,
- input facts or calculation references,
- calculated value,
- expected value/tolerance,
- status,
- lineage,
- explanation/review information.

## 8. Rules that are safe to implement first

The following are directly supported by formulas in the workbook and have relatively clear semantics:

1. Cash receiving: ending balance calculation.
2. Cash receiving: debit vs receipt variance.
3. Cash receiving: credit vs deposit variance.
4. Cash receiving: BKU vs balance-sheet cash variance.
5. Cash receiving: receipt vs LRA variance.
6. LPJ: total LPJ consistency.
7. Cash expenditure: ending balance calculation.
8. Cash expenditure: debit vs SP2D variance.
9. Cash expenditure: credit vs TBP variance.
10. Cash expenditure: RC BP/BPP vs balance variance.
11. LRA monthly and cumulative calculations.
12. LRA aggregate/subtotal calculations.
13. LRA revenue vs RKUD control.
14. LRA expenditure vs SPJ control.
15. LO aggregate/subtotal calculations.
16. LRA vs LO revenue control — pending precise source mapping for row 38.
17. LRA vs LO expenditure control — pending precise source mapping for row 38.

## 9. Rules deliberately deferred

Do not implement these yet:

- BMD/asset reconciliation.
- Exact mapping of every row to every input workbook.
- Automatic interpretation of free-text explanations.
- Any rule inferred only from a filename.
- Any pass/fail policy beyond what the source formula explicitly establishes.
- Any tolerance other than exact zero unless the source material establishes a tolerance.
- Automatic correction of source values.

## 10. Next implementation step

Before adding the reconciliation engine, create a **source-to-row mapping** for the above controls.

For each control, identify:

```
source document
→ sheet
→ source row/field
→ normalized metric
→ calculation
→ reconciliation rule
→ result
```

Only after that mapping is complete should the database-backed rule engine be implemented.

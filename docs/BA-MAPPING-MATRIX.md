# BA Mapping Matrix

## Purpose

This matrix is the controlled bridge between the legacy Berita Acara Rekonsiliasi workbook and the reconciliation engine.

A BA value may only be generated when its source, calculation, and reconciliation semantics are supported by evidence from the supplied workbooks.

## Status

- **MAPPED** — source and calculation are sufficiently identified for implementation.
- **PARTIAL** — source/calculation is identified but additional mapping is required.
- **BLOCKED** — required source workbook or authoritative mapping was not supplied.
- **VERIFY** — relationship was observed in the legacy workbook but its business meaning requires confirmation before automation.

## Matrix

| BA Section | Legacy Control | Primary Source | Supporting Source | Relationship | Status |
|---|---|---|---|---|---|
| Kas Bendahara Penerimaan | Saldo Akhir | Buku Besar Kas Penerimaan | BKU/neraca | Saldo Awal + Debet - Kredit | MAPPED |
| Kas Bendahara Penerimaan | Debet vs Penerimaan | Buku Besar | Rekon Pendapatan | Debet - Penerimaan | MAPPED |
| Kas Bendahara Penerimaan | Kredit vs Setoran | Buku Besar | STS/penyetoran | Kredit - Setoran | MAPPED |
| Kas Bendahara Penerimaan | Kas BKU vs Neraca | BKU | Neraca | saldo vs balance | PARTIAL |
| Kas Bendahara Penerimaan | Penerimaan vs LRA | Rekon Pendapatan | LRA | penerimaan - LRA | MAPPED |
| Pendapatan LRA | Bulanan | Rekon Pendapatan | Pengesahan | source components + transfer categories | PARTIAL |
| Pendapatan LRA | Akumulatif | Rekon Pendapatan | LRA | cumulative monthly values | PARTIAL |
| LPJ | Keandalan LPJ | Rekon Pendapatan/Pengeluaran | SP2D/TBP | TOTAL LPJ - (SP2D LS + TBP - pengembalian) | MAPPED |
| Kas Bendahara Pengeluaran | Saldo Akhir | Buku Besar | BKU/neraca | Saldo Awal + Debet - Kredit | PARTIAL |
| Kas Bendahara Pengeluaran | Debet vs SP2D | Buku Besar | Rekon Pengeluaran | Debet vs SP2D | PARTIAL |
| Kas Bendahara Pengeluaran | Kredit vs TBP | Buku Besar | Rekon Pengeluaran | Kredit vs TBP | PARTIAL |
| Kas Bendahara Pengeluaran | RC BP/BPP vs Neraca | Buku Besar | Neraca | saldo vs balance | PARTIAL |
| Belanja LRA | Bulanan | Rekon Pengeluaran | LRA | TOTAL LPJ - pengembalian | MAPPED |
| Belanja LRA | Akumulatif | Rekon Pengeluaran | LRA | cumulative monthly values | PARTIAL |
| Accounting | LRA Pendapatan vs Buku Besar | Buku Besar | LRA official report | canonical lra_revenue | MAPPED |
| Accounting | LRA Belanja vs Buku Besar | Buku Besar | LRA official report | canonical lra_expenditure | MAPPED |
| Accounting | Surplus LO vs LPE | LO | LPE | canonical surplus/deficit | MAPPED |
| Accounting | Ekuitas Neraca vs LPE | Neraca | LPE | ending equity | MAPPED |
| Accounting | LRA vs LO Pendapatan | LRA | LO + supporting value | LRA - LO - supporting | VERIFY |
| Accounting | Beban vs Belanja | LO | LRA + supporting value | Beban - Belanja + Belanja Modal + supporting | VERIFY |
| BMD | Aset/KKPD | BA Rekon Aset/BMD | Neraca | asset cross-check | BLOCKED |

## Source Scope

Official financial statements are distinguished from working papers.

- official_report: LRA, Neraca, LO, LPE supplied as official report workbooks.
- working_paper: kertas kerja LRA/Neraca/LO/LPE.
- financial_statement_unknown: financial statement-like source without reliable classification.

Working papers must not silently replace official reports in accounting cross-checks.

## Rules

1. Do not infer a BA value from row position alone.
2. Do not treat every numeric Excel cell as an accounting fact of equal semantic weight.
3. Preserve source document, sheet, row, column, and financial-fact lineage.
4. Derived values must identify their calculation inputs.
5. A non-zero variance is not automatically a failure unless the source semantics define it as such.
6. No tolerance is invented without source or approved business configuration.
7. BMD controls remain blocked until the authoritative BA Aset/BMD source is supplied.
8. Legacy labels and formulas are evidence; their business interpretation must remain distinguishable from implementation assumptions.

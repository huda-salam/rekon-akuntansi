# BA Cash and LRA Mapping

This document defines the next controlled mapping layer for the cash and LRA sections of the legacy BA.

## 1. Kas Bendahara Penerimaan

### 1.1 Saldo akhir

Observed legacy relationship:

\`Saldo Akhir = Saldo Awal + Debet - Kredit\`

Required source semantics:

- Saldo Awal
- Debet
- Kredit
- Saldo Akhir

Grain: SKPD + month.

Implementation requirement: the four values must retain source lineage. The derived saldo must not be imported as an independent authoritative fact when it can be calculated from the source components.

### 1.2 Debet versus penerimaan

Observed control:

\`Debet - Penerimaan\`

Expected result: 0 where the source workbook defines the control.

This is a cross-source control and must not be confused with the reported \`status\` field in other reconciliation workbooks.

### 1.3 Kredit versus setoran

Observed control:

\`Kredit - Setoran\`

Expected result: 0 where the BA defines the control.

The source for setoran must preserve the distinction between STS, STBP and LPJ penyetoran. These are not interchangeable labels.

### 1.4 Kas BKU versus Neraca

Observed relationship: cash recorded in the operational/BKU side is cross-checked against the balance-sheet cash value.

Status: PARTIAL.

Reason: the supplied source inventory does not yet establish a complete, authoritative mapping between the BA cash row and the exact balance-sheet metric for every SKPD/month combination.

Do not create a zero-value fallback.

### 1.5 Penerimaan versus LRA

Observed control:

\`Penerimaan - Pendapatan LRA\`

Expected result: 0 where the BA defines the control.

This should eventually use the canonical \`lra_revenue\` metric only when the period semantics are compatible.

## 2. Pendapatan LRA

### 2.1 Monthly value

The BA derives monthly revenue from source components. The supplied BA mapping identifies components including:

- Penerimaan
- Dana Desa
- transfer non-BOS/BOK
- BOS
- BOK
- TPG

The exact applicability of every component is SKPD/source dependent.

Status: PARTIAL.

Do not force all components into every SKPD.

### 2.2 Cumulative value

Observed relationship:

\`Pendapatan LRA Akumulatif = cumulative monthly LRA revenue\`

The cumulative value must be calculated from the monthly source values rather than independently imported as a second authoritative value when both are available.

## 3. Kas Bendahara Pengeluaran

Observed structural relationship:

\`Saldo Akhir = Saldo Awal + Debet - Kredit\`

Observed cross-check families:

- Debet versus SP2D
- Kredit versus TBP
- RC BP/BPP versus Neraca

Status:

- arithmetic balance: MAPPED conceptually
- Debet versus SP2D: PARTIAL
- Kredit versus TBP: PARTIAL
- RC BP/BPP versus Neraca: PARTIAL

The current \`rekonsiliasi pengeluaran.xlsx\` provides a separate SKPD-level summary of SP2D, SPJ, STS and cash values. It does not by itself prove all detailed BA cash-control fields.

## 4. Belanja LRA

Observed monthly relationship:

\`LRA Bulanan = TOTAL LPJ - TOTAL PENGEMBALIAN\`

Observed cumulative relationship:

\`LRA Akumulatif = cumulative monthly LRA values\`

Status:

- monthly calculation: MAPPED
- cumulative calculation: PARTIAL

The engine must preserve the distinction between reported values and derived values.

## 5. Implementation Guardrails

1. Month is part of the grain for these controls.
2. Annual financial statements must not be silently treated as monthly observations.
3. A missing source is INCOMPLETE, not zero.
4. A non-zero variance is a fact requiring interpretation; it is not automatically a failure.
5. Formula-derived values must retain input lineage.
6. The same source value must not be counted twice merely because it appears in a detailed row and a summary row.
7. The BA output must be generated from the immutable snapshot after finalization.
8. BMD remains BLOCKED until its source workbook is available.

## 6. Next implementation gate

Before adding new cash rules to the production seed:

- identify the exact source metric names in the parser;
- verify them against the original workbook rows/cells;
- add parser tests;
- add rule tests using known source values;
- add lineage assertions;
- only then expose the control in BA Excel.

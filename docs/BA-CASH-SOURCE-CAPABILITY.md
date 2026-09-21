# Cash Source Capability Matrix

## Decision

The current source inventory does **not** provide enough evidence to safely map every legacy BA cash-control row to a production metric.

This is intentional. The engine must not create cash values by guessing account codes or by treating an annual balance-sheet cash value as a monthly bendahara cash balance.

## Current evidence

### Available

- Ledger source workbooks contain transaction-level accounting data.
- Financial statements contain annual balance-sheet cash aggregates.
- Revenue reconciliation contains monthly SKPD-level reconciliation metrics.
- Expenditure reconciliation contains SKPD-level SP2D/SPJ/STS/cash summary values.

### Not yet proven

The supplied source inventory does not yet prove a complete monthly mapping for:

- Saldo Awal Kas Bendahara Penerimaan
- Debet Kas Bendahara Penerimaan
- Kredit Kas Bendahara Penerimaan
- Saldo Akhir Kas Bendahara Penerimaan
- Debet Kas Bendahara Pengeluaran
- Kredit Kas Bendahara Pengeluaran
- RC BP/BPP monthly balance

## Why we do not infer them

The BA formulas are clear, but formula clarity does not identify the authoritative source.

For example:

Saldo Akhir = Saldo Awal + Debet - Kredit

does not tell us which ledger account, BKU column, or working-paper cell is authoritative.

Likewise, the annual Neraca Kas dan Setara Kas value cannot automatically be used as a monthly Bendahara value.

## Safe implementation boundary

The engine may currently implement:

1. monthly revenue reconciliation metrics from rekonsiliasi pendapatan.xlsx;
2. expenditure summary controls from rekonsiliasi pengeluaran.xlsx;
3. annual official-report accounting controls from LRA/Neraca/LO/LPE;
4. source lineage for all of the above.

The engine must mark monthly BA cash controls as INCOMPLETE until an authoritative source mapping is established.

## Required evidence for activation

For each cash metric we need:

- source filename;
- sheet;
- row/column or table field;
- semantic label;
- period grain;
- SKPD grain;
- sign convention;
- opening/closing semantics;
- relationship to the BA row;
- one or more known-value test cases.

Only after these are available should the metric become an active reconciliation rule.

## Expected next source

The next useful source is the workbook/table that actually supplies the BA cash columns (BKU, bendahara cash ledger, or equivalent working paper). Once supplied or identified, it can be mapped without changing the BA architecture.

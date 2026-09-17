# Implementation Notes

## MVP workflow

1. Admin configures the active accounting year.
2. Admin maintains SKPD and pejabat records.
3. Admin creates users and assigns either SKPD scope or SKPKD/admin scope.
4. Source data for pengesahan is recorded/imported using the supplied source format.
5. SKPD can review its own source data and reconciliation results.
6. SKPKD/admin performs reconciliation between the relevant source data.
7. The reconciliation result is finalized through a Berita Acara (BA).
8. Creating the BA freezes an immutable snapshot of the reconciliation and the relevant identification of SKPD/pejabat data used at that moment.
9. Future master-data edits must not alter an existing reconciliation snapshot.
10. Asset/BMD BA will later be added as another structured source for matching asset additions; its exact fields and matching rules remain pending the actual BA format.

## Initial data areas

- `users`: authentication and role/SKPD scope
- `accounting_years`: active year configuration
- `skpds`: SKPD master data
- `officials`: pejabat master data
- `authorizations`: source data for approved/validated revenue and expenditure
- `reconciliations`: mutable working reconciliation header/status
- `reconciliation_details`: mutable working matching/results
- `reconciliation_snapshots`: immutable finalized snapshot header
- `reconciliation_snapshot_details`: immutable finalized snapshot details
- `berita_acaras`: BA metadata and links to the frozen snapshot
- `master_references`: imported hierarchical reference data from the master Excel file

## Master Excel import

The MVP supports an administrator-only Excel import at `POST /api/master-data/import`.

Expected sheet structure:

| Column | Meaning |
| --- | --- |
| `kode` | Master/reference code |
| `uraian` | Description/name |
| `jenis` | `urusan`, `bidang`, `program`, `sub_kegiatan`, `skpd`, `rekening_belanja`, `rekening_pendapatan`, or `rekening_pembiayaan` |
| `level` | Optional hierarchy level |
| `parent` | Optional parent code |

The supplied 2026 workbook uses one sheet named `ref` with these columns. Its data contains 3,630 rows across 8 master types, including 96 SKPD rows.

Import behavior is deliberately **upsert**, not destructive synchronization:

- Existing records are updated using `(jenis, kode)` as the natural key.
- New records are inserted.
- Imported rows are stored in `master_references` so the original hierarchy and master type are retained.
- Rows with `jenis = skpd` are also synchronized into the operational `skpds` table by `code`.
- Rows missing required fields, using an unknown `jenis`, or duplicated within the same `(jenis, kode)` import are rejected before database writes.
- The database write is transactional; a validation failure does not partially import the workbook.
- Import does not deactivate or delete records that are absent from a later workbook. This avoids destructive changes when a source workbook is incomplete or represents only part of a master set.

The import endpoint accepts `.xlsx` and `.xls` files up to 10 MB and optionally records the source year.

## Important constraint

The application is not an asset-management system. Asset/BMD integration is limited to the minimum structured source data and matching needed by the reconciliation workflow.

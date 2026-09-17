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

## Important constraint

The application is not an asset-management system. Asset/BMD integration is limited to the minimum structured source data and matching needed by the reconciliation workflow.

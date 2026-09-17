# Implementation Notes

## MVP workflow

1. Admin configures the active accounting year.
2. Admin maintains SKPD and pejabat records.
3. Admin creates users and assigns either SKPD scope or SKPKD/admin scope.
4. Admin/SKPKD records or imports source data for pengesahan using the supplied source format.
5. SKPD can review its own source data and reconciliation results.
6. SKPKD/admin performs reconciliation between the relevant source data.
7. The reconciliation result moves from `draft` to `in_review` when matching is saved.
8. The `in_review` result is finalized through a Berita Acara (BA).
9. Creating the BA freezes an immutable snapshot of the reconciliation and the relevant identification of SKPD/pejabat data used at that moment.
10. Future master-data edits must not alter an existing reconciliation snapshot.
11. Asset/BMD BA will later be added as another structured source for matching asset additions; its exact fields and matching rules remain pending the actual BA format.

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

## Server-side integrity rules

- Reconciliation creation is limited to Admin/SKPKD.
- New reconciliation and new source data use the active accounting year and an active SKPD.
- A reconciliation detail must reference an existing pengesahan source belonging to the same year and SKPD as the reconciliation.
- Source amount is recalculated from the source detail rows; client-provided source and difference amounts are not trusted.
- Matching amount must not exceed the source amount.
- `unmatched` requires a zero matching amount; `matched` requires full matching; `partial` requires a positive amount below the source amount; `exception` is available for cases requiring review.
- Difference is always calculated server-side as source amount minus matching amount.
- Finalization is allowed only from `in_review` and only when reconciliation details exist.
- After finalization, the working reconciliation cannot be updated through the API.

## Important constraint

The application is not an asset-management system. Asset/BMD integration is limited to the minimum structured source data and matching needed by the reconciliation workflow.

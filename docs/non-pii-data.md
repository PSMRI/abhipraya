# Non-PII export and import guide

Exports must contain only aggregated or irreversibly pseudonymised data. Exclude names, phone numbers, email, exact GPS coordinates, session tokens, IP addresses and free text unless separately approved and redacted.

The [redacted sample export](evidence/non-pii-sample-export.json) is the reference evidence artifact for release review. It contains facility codes, reporting period, indicator codes, response counts and averages only; it does not identify a beneficiary.

## Required controls

- [ ] role- and facility-scoped export permission;
- [ ] date-range and geography filters;
- [ ] minimum-cell threshold before release;
- [x] documented field dictionary and schema version;
- [x] redacted sample export retained for testing;
- [ ] import validation rejects unknown fields and unsafe values.

## Scope-control test evidence

Before release, retain request/response evidence for these cases:

1. An administrator assigned to Facility A cannot export Facility B.
2. A facility user cannot remove the facility filter or widen its geography scope.
3. A date range outside the permitted reporting window is rejected.
4. Aggregates below the approved minimum-cell threshold are suppressed.
5. Export output contains no identity, IP, token, exact-coordinate or unmoderated free-text fields.
6. Unknown columns and formula-like values are rejected on import.

Record tester, build version, date, request scope, expected result, actual result and evidence link for each test. The sample above is a format example, not production evidence.

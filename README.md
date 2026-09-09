# Abhipraya

Abhipraya is an open-source, multilingual public-feedback platform for public-service locations and operating units. The current implementation is configured for healthcare facilities; it supports QR-led surveys, location-aware submissions, service-area reporting, and administrative management.

## Current capabilities

- Mobile-friendly Hindi and English survey experience, including accessible audio guidance for OPD feedback.
- Feedback Explorer with searchable facility/NIN filters, department and date filters, and full-data export.
- Dashboard and Analytics views that use the same reporting-facility scope and facility-category breakdown.
- Responsive administrative navigation, language switching, and accessibility controls across the admin pages.
- Facility master data managed by `facilityCodes.json` and synchronised with `fac_master` when required.

## Database performance

The response table is intentionally kept wide for fast reporting of fixed survey questions. Splitting each answer into a separate row would add joins and slow the Dashboard, Analytics, and Feedback Explorer pages.

Apply the idempotent response-query index migration after deployment or database restoration:

```powershell
php api/database/migrations/20260909_response_query_indexes.php
```

It ensures composite indexes are available for facility/department/version grouping and department/date filtering, then refreshes response-table statistics. It does not change survey responses or facility records.

## Documentation

The GitBook-ready documentation is in [docs](docs/README.md). It includes user and developer guides, security controls, deployment notes, and a Digital Public Goods readiness checklist.

## License

Abhipraya is licensed under the [GNU GPL-3.0-or-later](LICENSE). Every redistributed release must also retain the [NOTICE](NOTICE) and [third-party notices](THIRD_PARTY_NOTICES.md).

# Open standards and best-practices mapping

## Approach

Abhipraya uses open, widely implemented web and data formats so an implementation is not tied to a proprietary client, analytics tool or hosting vendor.

| Area | Standard or practice | How Abhipraya applies it | Evidence |
| --- | --- | --- | --- |
| Web interface | HTML5, CSS, ECMAScript and responsive web design | Public survey and administrator UI run in modern standards-compliant browsers. | UI source and browser tests. |
| Accessibility | WCAG 2.2 AA target; WAI-ARIA where needed | Keyboard operation, focus visibility, semantic controls, readable contrast and language support are documented and tested. | [WCAG compliance guide](../testing/wcag_web_platform_compliance.md). |
| APIs | HTTPS, REST-style JSON, UTF-8 and HTTP status codes | Versioned PHP API endpoints exchange JSON and return standard status codes. | [API documentation](../api/README.md). |
| Configuration | JSON and JSON Schema-compatible validation | Survey packages are versioned JSON with stable question and indicator identifiers. | [Configuration formats](../architecture/configuration_formats.md). |
| Data export | CSV and XLSX | Standard report exports can be opened by common analytics and spreadsheet tools. | [Non-PII export guide](non_pii_data_export_import.md). |
| Time and location | ISO 8601 date/time representation; WGS 84 latitude/longitude when enabled | Use clear date/time formats and document location collection as optional, scoped and privacy-protected. | [Privacy and data protection](privacy_data_protection.md). |
| Licensing | GPL-3.0-or-later and published third-party notices | The core source release has a clear licence and must retain notices in every release. | [Open-source release status](open_source_dpg_release_status.md). |

## Interoperability commitments

1. No administrator or beneficiary should need a proprietary desktop application to use the core platform.
2. The API and exports should document fields, formats and version changes before release.
3. Survey question IDs and indicator keys are stable within a published survey version.
4. A new survey version must not reinterpret old response data; reports retain the response's survey version.

## Verified standards evidence

- All 20 registered versioned API routes have a corresponding path in the published [OpenAPI definition](../openapi.yaml.md); see the [test results](../testing/test_results.md).
- The published OpenAPI definition, Postman collection and 36 JSON configuration files parsed successfully; see the [passed-test evidence register](../testing/test_evidence_register.md).
- The report-export path produces standard CSV and XLSX files; the reviewed Bihar exports verify reusable aggregate CSV data without direct identifiers. See the [non-PII export guide](non_pii_data_export_import.md).
- The [WCAG and web-platform compliance record](../testing/wcag_web_platform_compliance.md) documents semantic, responsive, contrast, focus and NVDA screen-reader checks.
- The core platform has a functional path using open web formats and does not require a proprietary desktop application or proprietary analytics tool.

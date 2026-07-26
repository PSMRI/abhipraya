# Non-PII data export and import

## Objective

Abhipraya must allow a deployment owner to extract its data in reusable formats without exposing beneficiary identity. The core platform is designed for anonymous feedback; exports must preserve that principle.

## Supported export boundary

Exports should contain only data required for analysis and transfer:

| Dataset | Included | Excluded by default |
| --- | --- | --- |
| Aggregated reports | Counts, averages, stars, percentages, period, facility and department labels | IP address, device identifier, precise GPS coordinates, user agent and raw request data |
| Response-level analytics | Response identifier, configured question/indicator key, answer value, score, survey version, period and permitted scope | Name, phone, Aadhaar, email and any direct identifier |
| Survey configuration package | Versioned JSON, question metadata, labels, options, icon and analytics type | Secrets, database credentials and internal deployment paths |
| CAPA export | Indicator, action, status, owner role, target date and outcome | Personal contact details unless an authorised organisation explicitly requires them |

## Export rules

1. Enforce the logged-in official's state, district, facility and department scope on the server.
2. Offer CSV and XLSX for tabular analysis; use UTF-8 and stable field names.
3. Use a documented schema version in every machine-readable export.
4. Suppress or aggregate small cells where a local policy requires it.
5. Never export raw IP addresses, device fingerprints or precise coordinates through the standard reporting interface.
6. Record the export event in the audit log, including user, scope, filters, format and timestamp.

## Import rules

Configuration imports are JSON packages, not arbitrary database uploads. Validate against the survey configuration schema before activation, create a content hash, retain the previous version, and reject packages containing credentials or executable content.

## Example response-level export columns

```text
schema_version,response_id,submitted_date,facility_nin,department_id,survey_version,
indicator_key,analytics_type,answer_value,score
```

## Verification checklist

- Test a state, district and facility account separately.
- Verify each receives only its permitted aggregate or response-level data.
- Scan an export for direct identifiers, IP addresses, device IDs and coordinates.
- Open the CSV in a spreadsheet and confirm Unicode labels are preserved.
- Confirm the corresponding audit-log event exists.

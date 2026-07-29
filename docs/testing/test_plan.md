# Quality assurance and release test plan

## Purpose and scope

This plan defines the minimum test evidence for an Abhipraya release. It applies to public feedback journeys, administrative functions, APIs, configuration, database changes, deployment, and operational recovery. It is not a certification or a substitute for a deployment owner's legal or security review.

## Test levels

| Test level | What to test | When | Minimum evidence |
| --- | --- | --- | --- |
| Static checks | PHP syntax, JSON/YAML validity, broken documentation links, secrets and dependency review | Every change | Command output attached to pull request or release record |
| Unit tests | Pure validation, calculations, formatting and configuration helpers | Every changed behaviour | Automated test result or focused developer test note |
| API/integration tests | Route validation, database persistence, authentication, CSRF, scope and response envelope | Every API or data change | Postman/OpenAPI collection run against a disposable test environment |
| End-to-end tests | Browser journeys across UI, API, session and database | Every release candidate | Signed test checklist with screenshots where useful |
| Regression tests | Previously working critical journeys | Every release candidate | Passed critical-path results |
| User acceptance testing | Business rules, survey wording, reports and workflow acceptance | UAT before production | UAT approval and recorded limitations |
| Non-functional tests | Accessibility, security, performance, resilience and compatibility | Before production and after material changes | Test report and remediation decisions |
| Recovery exercises | Backup restore, rollback and incident communication | Before production; periodically after | Restore duration, result and owner sign-off |

## Functional and regression coverage

| Area | Required scenarios |
| --- | --- |
| Authentication and session | Valid and invalid sign-in; CAPTCHA; lockout; logout; timeout; expired/revoked session; CSRF failure; session storage unavailable; password-change rules. |
| Access control | Unauthenticated, forbidden-role, wrong service location, altered identifier, and successful request for every protected endpoint. Verify UI and API both enforce scope. |
| Public survey | QR/link resolution; language selection; required and conditional questions; malformed input; duplicate control; location/radius rule where enabled; anonymous submission; safe error messages. |
| Master data and survey publishing | Candidate JSON validation; manifest/hash generation; activation; rollback to prior version; old responses retain their original version and schema hash. |
| Boundaries and scope | Hierarchy, parent/child lookup, locale, invalid code, restricted import, and administrative-scope filtering. |
| Responses, analytics and reports | Correct aggregation for rating, binary, numeric and duration questions; empty states; filters; date range; scope; export redaction and minimum-cell rules. |
| CAPA and workflows | Create, read, update, status transition, assignment, validation, audit/event record, and scope enforcement. |
| Administration | User provisioning, profile edit, role change, service-location mapping, configuration changes, audit visibility and safe error handling. |
| Events | Local event log by default; when Kafka is enabled, successful publish, retry/failure logging, and no survey-response loss when a broker is unavailable. |

## Security and privacy testing

| Test | Minimum expectation |
| --- | --- |
| Threat and permission review | Review changed routes, roles, data fields, trust boundaries and abuse cases before merge. |
| Authentication/authorisation | Test OWASP-style authentication, session, CSRF and horizontal/vertical access-control failures. |
| Input handling | Test invalid types, oversized values, unexpected JSON keys, SQL injection, XSS, path traversal and file-upload cases where applicable. |
| Dependency and secret scan | Scan committed source, configuration and dependency advisories. Never publish credentials, production exports or session material. |
| Security headers/TLS | Verify HTTPS deployment, CSP, HSTS where appropriate, clickjacking protection, cache control and non-verbose errors. |
| VAPT | An independent or suitably qualified security assessment is required before a production launch and after high-risk changes. Track findings to closure or formally accepted risk. |
| Privacy review | Verify data minimisation, encryption settings, retention/deletion process, non-PII export, redacted test data, notice and consent/other lawful basis as applicable. |

## Accessibility, compatibility and usability

- Perform keyboard-only testing, visible-focus checks, semantic headings/labels, error announcement, colour contrast, zoom/reflow and screen-reader spot checks against the [WCAG guidance](wcag_web_platform_compliance.md).
- Test current supported desktop and mobile browsers defined by the deployment owner, including a narrow mobile viewport for the public survey.
- Check English and configured local-language content, including long labels and non-Latin text where used.
- Run an automated accessibility scan, then manually review its findings; automated tools cannot prove WCAG conformance.

## Performance, resilience and operations

| Area | Test and acceptance evidence |
| --- | --- |
| Performance | Establish a production-like baseline for key public survey and administrator API requests. Load testing must use synthetic, non-personal data and agreed volume/latency targets. |
| Capacity | Validate database connection limits, session store capacity, disk/log retention and application worker capacity for the planned deployment scale. |
| Failure handling | Verify database, session store, external event broker and configuration-file failure paths return safe errors and preserve data consistency. |
| Backup and restore | Execute and record a restore following the [backup and restore guide](../deployment/backup_restore_guide.md); verify row counts, application access and a sample response/CAPA record. |
| Deployment and rollback | Deploy to staging/UAT using the release procedure, run smoke tests, then prove the documented rollback path before production use. |
| Monitoring | Confirm health signals, error logs, audit/event logs, alert ownership and incident contacts are available without exposing confidential content. |

## Release gates

### Every pull request

- Changed PHP files pass `php -l`.
- Changed JSON and YAML configuration/API contracts parse successfully.
- Relevant functional, access-control and regression scenarios pass.
- Documentation, OpenAPI/Postman collection and migration notes are updated with the change.
- No secrets, personal data, production backups or private URLs are included.

### Release candidate / UAT

- Critical-path end-to-end tests, role/scope tests and API smoke tests pass in a representative environment.
- Accessibility, responsive and supported-browser checks pass for changed screens.
- Security/privacy review, dependency scan and required VAPT status are recorded.
- Migration, backup restore, deployment rollback and monitoring checks are completed.

### Production release

- UAT approval, release version/tag, changelog, known limitations and rollback contact are recorded.
- Only deployment-approved configuration is used; secrets are injected outside source control.
- Post-deployment smoke tests confirm HTTPS, login, public survey submit, role-scoped administration, reporting and monitoring.

## Defect handling

Record each defect with release version, environment, reproducible steps, expected/actual result, severity, owner and evidence. Block release for unresolved critical or high-risk privacy, security, data-loss, access-control or public-survey-submission defects. Document accepted lower-risk issues with an owner and target resolution release.

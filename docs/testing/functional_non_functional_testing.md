# Functional and non-functional testing

Functional testing confirms that Abhipraya performs the intended user and API behaviour. Non-functional testing confirms that it remains secure, usable, reliable and operable under expected conditions.

## Functional testing

Use a development, test, or approved UAT environment with synthetic data and dedicated test accounts.

| Area | Test scenarios | Pass criteria |
| --- | --- | --- |
| Authentication | CAPTCHA, valid and invalid login, lockout, logout, timeout, password change and CSRF validation. | Only valid users obtain a session; expired/revoked sessions and invalid writes are rejected safely. |
| Roles and scope | Test each administrator and service-location role for allowed, denied and cross-location actions. | UI and API return only permitted data and reject altered identifiers/scope. |
| Public survey | Resolve QR/reference, language selection, required/conditional questions, location check, valid submission and duplicate rule. | A valid synthetic response is stored once; invalid inputs return clear `4xx` errors without partial data. |
| Master data | Parse roles, locations, departments, boundaries and survey JSON; test valid/invalid changes. | Only approved valid configuration activates; invalid configuration fails safely. |
| Survey publishing | Candidate validation, schema hash, manifest update, activation, rollback and old-response compatibility. | Published version is traceable; prior responses retain their original version/hash. |
| Boundaries | List, filter, hierarchy lookup, locale, invalid code and restricted import. | Correct records are returned within approved administrative scope. |
| Reporting and export | Filters, empty state, aggregations, export redaction/minimum-cell rules and service-location scope. | Calculations and exports match synthetic source data and privacy rules. |
| CAPA | Create, read, update, status lifecycle, validation, assignment, scope and audit/event record. | Only authorized actions persist; lifecycle and audit data are correct. |
| API contract | Run the documented Postman collection and negative requests. | HTTP status, response envelope, fields and validation match the OpenAPI contract. |
| Deployment | Start application, load docs, serve assets, connect session store and perform configuration reload. | Service starts with safe diagnostics; critical routes and documentation load. |

## Non-functional testing

| Quality attribute | Required test | Pass criteria / evidence |
| --- | --- | --- |
| Security | Authentication/authorization, CSRF, input handling, dependency and secret scan, security headers/TLS, VAPT. | Findings are remediated or formally accepted; no critical unresolved issue. |
| Privacy | Data minimisation, encryption configuration, redacted test data, retention/deletion and non-PII export review. | Privacy controls and deployment-owner review recorded. |
| Accessibility | Automated scan plus keyboard, focus, labels, contrast, zoom/reflow and screen-reader checks. | Meets the documented WCAG 2.2 AA target or records approved exceptions. |
| Performance | Synthetic load test for public survey, login, reporting and exports using agreed volume/latency/error targets. | Target response time, error rate and capacity are met; results retained. |
| Reliability | Database/session/event-broker failure, restart, retry and safe error behaviour. | No corrupted or duplicate response; recovery and alerts work as designed. |
| Availability | Planned restart, health-check, dependency monitoring and alert escalation. | Health signal and owner response path are verified. |
| Recovery | Backup restore, migration rollback and incident runbook exercise. | Restored application/data integrity verified with duration and owner sign-off. |
| Compatibility | Supported browser, mobile viewport, current PHP/runtime and server-platform checks. | Core public and administrative journeys work in documented supported environments. |
| Maintainability | Lint, coding standards, documentation links, OpenAPI/JSON validation and peer review. | Changed code is readable, documented and passes automated checks. |
| Observability | Audit logs, error logs, event logs, metrics and alert routing. | Logs are useful, protected from sensitive exposure and reachable by accountable owners. |

## Execution order

1. Run static checks: PHP lint, JSON/XML/API contract validation, dependency and secret checks.
2. Run functional API tests with synthetic data, then UI end-to-end tests.
3. Run non-functional security, accessibility, compatibility and resilience checks.
4. Run performance and restore tests only in a representative non-production environment.
5. Record each result in [Test results](test_results.md). The recorded result must state the tested environment, data scope, and any remaining release limitation.

## Current development-environment status

Static checks, documentation/API smoke tests, and safe anonymous-route checks are recorded in [Test results](test_results.md). Database-backed functional tests and several non-functional exercises remain blocked until the configured database host/port is reachable from the PHP runtime. Do not mark those items as passed based on static or anonymous-route tests alone.

# White-box and black-box testing

Abhipraya uses both testing approaches. They complement each other: white-box testing verifies implementation logic; black-box testing verifies observable behaviour from a user or API consumer perspective.

## White-box testing

White-box tests are performed with knowledge of the PHP code, routes, configuration and database interactions.

| Component | White-box checks | Evidence |
| --- | --- | --- |
| PHP code | Syntax lint for changed PHP files; error-path review; type, null and exception handling. | `php -l` output. |
| Input validation | Exercise validation branches for missing, malformed, oversized and unexpected input. | Automated test output or API response record. |
| Access control | Review route middleware and scope checks; test branches for missing session, forbidden role and cross-service-location access. | Code review and authenticated API test results. |
| Survey publishing | Validate question types, translations, option values, indicator keys, hashes and manifest changes. | Publisher dry-run output. |
| Persistence | Review transactions, encryption calls, parameterised queries, error handling and rollback behaviour. | Integration test using a disposable database. |
| Session and events | Check expiry, Memurai/Redis failure handling, CSRF generation/validation and Kafka fallback logging where enabled. | Session/event test record. |
| Configuration | Parse JSON, XML and API contracts; verify invalid configuration fails safely. | Validator/lint output. |

### White-box commands

Run these from the repository root:

```powershell
# Lint a changed PHP file
php -l api/core/SessionManager.php

# Validate a candidate survey without writing files
php tools/publish_survey_version.php --department=1 --source=api/masters/surveys/department_1/v1.0/survey.json --version=1.1

```

Never run publisher commands with `--publish` while testing unless the target is an approved disposable/test configuration.

## Black-box testing

Black-box tests use the UI or documented HTTP interface without relying on internal implementation details.

| Journey/interface | Black-box checks | Expected result |
| --- | --- | --- |
| Public survey | Open QR/link, choose language, answer required/conditional questions, submit valid synthetic feedback. | Clear success confirmation; one valid response; no respondent identity exposed. |
| Invalid public input | Missing reference, invalid coordinates, malformed answers and repeated submission. | Safe `4xx` response with actionable validation message; no partial response saved. |
| Sign-in and session | Valid sign-in, invalid credentials, CAPTCHA, lockout, logout and session expiry. | Only authorized users receive a session; logout/expiry blocks protected pages. |
| Roles and scope | Administrator and service-location user try permitted and non-permitted UI/API actions. | Only authorized data/actions are visible; cross-scope actions are rejected. |
| QR, reports and CAPA | Generate permitted QR, filter reports, export permitted non-PII data, create/update an authorized CAPA action. | Correct data, scope and audit/event outcome. |
| API contract | Run the Postman collection against a test environment. | Status, response envelope and validation match the OpenAPI contract. |
| Accessibility and mobile | Keyboard-only navigation, focus, zoom/reflow, contrast, labels and narrow mobile viewport. | Usable public and administrative journey meeting the documented WCAG target. |
| Recovery | Stop/restart a dependency in a controlled test environment, then restore a backup. | Safe error/recovery behaviour and verified restored data. |

### Black-box safety rules

- Use synthetic or fully redacted test data only.
- Run write tests in a development, test, or approved UAT environment—not production.
- Use dedicated test accounts for each role.
- Record endpoint, input classification, expected result, actual result, timestamp and tester in the [test results](test_results.md) page or restricted release evidence.

## Minimum release coverage

| Release stage | White-box | Black-box |
| --- | --- | --- |
| Pull request | Lint, configuration validation, changed validation/auth branches. | Relevant API/UI smoke check. |
| UAT | Integration, persistence, migration and failure-path review. | End-to-end journeys for each role, public survey, reports and CAPA. |
| Production release | Dependency/security review, restore/rollback evidence. | Post-deployment smoke tests, monitoring and business-owner acceptance. |

Use this guide with the [test plan](test_plan.md) and [test results](test_results.md).

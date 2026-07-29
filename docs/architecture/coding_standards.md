# Coding standards and conventions

## Purpose

These standards keep Abhipraya secure, maintainable, and consistent across browser pages, versioned APIs, JSON configuration, database migrations, and documentation. They apply to new code and to changed areas of existing code; do not make unrelated large-format rewrites merely to apply a style rule.

## Technology baseline

| Area | Standard used | Required convention |
| --- | --- | --- |
| PHP runtime | PHP **8.2+** | Write compatible PHP 8.2 code. Use `declare(strict_types=1);` in new PHP files. |
| PHP style | PSR-12-inspired formatting | Four-space indentation, braces on the next line for classes/methods, one responsibility per method, and readable line lengths. |
| PHP naming | PSR naming conventions | Classes/interfaces/exceptions: `PascalCase`; methods/variables: `camelCase`; constants: `UPPER_SNAKE_CASE`. |
| Dependency management | Composer | Declare runtime libraries in `composer.json`; update the lock file and third-party notices when dependencies change. |
| Database access | MySQLi prepared statements / repository layer | Never concatenate untrusted values into SQL. Bind parameters and keep data access in repositories or small, well-scoped module queries. |
| Browser code | Standards-based HTML5, CSS3, JavaScript | Use external scripts/styles; no inline scripts or styles because CSP may block them. |
| API design | Versioned REST-style JSON APIs | Register routes in `api/routes.php`; use `/api/v1/...`; return the shared response envelope. |
| Configuration | Versioned JSON | Validate schema/meaning before publishing; retain stable IDs and survey hashes for historical records. |
| Documentation | Markdown and `docs/SUMMARY.md` | Add every new user-facing guide to `SUMMARY.md` so it appears in the documentation sidebar. |

## PHP implementation conventions

| Topic | Required practice | Example / rule |
| --- | --- | --- |
| File header | Start new PHP files with strict types after `<?php`. | `<?php` then `declare(strict_types=1);` |
| Types | Use scalar, array, object, nullable, and return types where the contract is stable. | `function getUser(int $userId): array` |
| Classes | Keep business rules in service classes, persistence in repositories, and shared cross-cutting rules in `api/core/`. | Do not put large SQL or authorisation decisions in browser code. |
| Functions | Use small descriptive functions and early validation/return paths. | `validateSurveyPayload()` is clearer than `processData()`. |
| Exceptions | Catch only when adding useful recovery, logging, or a safe API response. | Never return stack traces or database errors to the browser. |
| Constants | Use constants for fixed security/time limits and configuration keys. | `SESSION_TIMEOUT`, `LOGIN_MAX_FAILED_ATTEMPTS` |
| Comments | Explain intent, security constraints, or non-obvious decisions. | Do not comment obvious syntax. |
| Compatibility | Preserve existing public routes, JSON field names, database columns, and QR references unless a versioned migration is approved. | Keep `facilityNIN` fields where current contracts require them. |

## API, security, and data conventions

| Area | Required standard | Do not |
| --- | --- | --- |
| Routing | Add only allow-listed routes in `api/routes.php`. | Expose a PHP file merely because it exists under `api/`. |
| Responses | Use `Response::success()`, `validation()`, `error()`, `unauthorized()`, `forbidden()`, or `serverError()`. | Invent incompatible JSON response shapes. |
| Input | Validate type, format, size, allowed values, and business rules on the server. | Trust hidden fields, URL values, or browser validation. |
| Authentication | Require an active server-side session for administrator endpoints. | Trust client-supplied user, role, or scope values. |
| Authorisation | Enforce role and service-location/data scope before reading or writing records. | Rely only on hiding buttons or filters. |
| CSRF | Validate CSRF for authenticated state-changing browser requests. | Accept an administrator write without CSRF protection. |
| SQL | Use prepared statements, transactions for related writes, and `PersisterService` for multi-write workflows. | Build SQL using concatenated request values. |
| Sensitive data | Encrypt approved sensitive fields; redact logs and error messages. | Log passwords, session values, encryption keys, answers with identifiers, IP addresses, or device IDs unnecessarily. |
| Events | Publish only approved, non-sensitive event payloads. Keep consumers idempotent. | Make a Kafka outage fail a completed public response. |

## JavaScript, HTML, and CSS conventions

| Area | Required practice |
| --- | --- |
| JavaScript | Use `const` by default and `let` only for reassigned values. Keep API calls in a small reusable request helper where practical. |
| DOM safety | Use `textContent` for untrusted text. Escape or strictly control any HTML inserted into the DOM. |
| Accessibility | Use semantic controls, labels, visible focus, keyboard support, adequate contrast, and text alongside colour/icon cues. |
| Responsive UI | Test common phone, tablet, and desktop widths. Tables must remain usable through responsive layout or horizontal scrolling. |
| CSS | Use existing design tokens/classes and external stylesheets. Keep selectors scoped to the feature; avoid `!important` unless overriding a controlled third-party/mobile rule. |
| Browser storage | Do not store credentials, roles, scopes, or sensitive data in local storage. The browser holds only the protected session cookie. |

## JSON, migrations, and analytical semantics

| Asset | Required convention |
| --- | --- |
| Master JSON | Keep stable identifiers and validate the candidate file before publishing. Use project-relative paths in commands and documentation. |
| Survey JSON | Each analytical question requires `report_type`, stable option values, `indicator_key`, and `question_id`. |
| Survey version | Never edit a published survey package in place. Publish a new version with its manifest hash. |
| Database migration | Use a dated, descriptive migration file; make forward changes reviewable and document backup/rollback implications. |
| CAPA / operational records | Preserve auditable identifiers and timestamps; use transactions when one operation changes multiple tables. |

## Required checks before review

| Change type | Minimum checks |
| --- | --- |
| PHP change | `php -l <changed-file>` and an appropriate route/feature test. |
| API change | Valid request, validation failure, unauthenticated/forbidden case, scope case, and response-envelope check. |
| Security/session change | HTTPS/cookie behaviour, CSRF, sign-in/sign-out, timeout, and unauthorised access checks. |
| JSON/survey change | Survey publisher validation, dry run, hash/manifest review, and rendered-question check. |
| UI change | Keyboard, narrow-screen, and browser-console/network-error check. |
| Documentation change | Verify links, add the page to `docs/SUMMARY.md`, and confirm the sidebar section remains expanded for the active page. |

## References

- [Developer guide](../developer-guide.md)
- [API specifications](../api/api_specifications.md)
- [Access control](../api/access_control.md)
- [Master data management](master_data_management.md)
- [Survey version publishing](../survey-version-publishing.md)

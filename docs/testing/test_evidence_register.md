# Passed-test evidence register

## Purpose and handling

This register indexes the supporting evidence for every test currently marked **Pass** (including the recorded `Pass with warnings` Composer manifest validation) in the [test results](test_results.md). It is intended for development-environment review and release preparation. It does not contain passwords, session cookies, database credentials, personally identifiable information, or production broker secrets.

Evidence recorded as a command or HTTP result was executed on the dates stated in the test results. Re-run it in an approved representative environment before using it as production-release evidence.

## Build and configuration checks

| Test | Recorded evidence | Supporting artefact / repeat method |
| --- | --- | --- |
| 1. PHP syntax lint | `php -l` passed for 105 PHP files with no failures. | Application and UI PHP sources; repeat with the PHP lint command used by the release run. |
| 2. JSON configuration/API validation | 36 JSON files parsed successfully. | [OpenAPI specification](../api/openapi.yaml), [Postman collection](../api/postman_collection.json), master-data JSON files. |
| 3. Web-server configuration XML | `web.config` parsed as valid XML. | [Root web configuration](../../web.config) and [API web configuration](../../api/web.config). |
| 4. Composer manifest | `composer validate --no-check-publish` passed with two non-blocking manifest warnings. | [composer.json](../../composer.json) and [composer.lock](../../composer.lock). |
| 5. Survey publishing validation | Department 1/version 1.1 dry run passed; 40 question records validated without file changes. | [Survey publishing script](../../tools/publish_survey_version.php) and versioned master-data files. |
| 6. QR generation smoke test | `php tools/testqr.php` returned exit code 0. | [QR smoke-test script](../../tools/testqr.php). |
| 7. CAPTCHA API smoke test | `GET /api/v1/auth/captcha` returned HTTP 200. | [CAPTCHA endpoint](../../api/modules/auth/v1/captcha.php). |
| 8. Authentication protection smoke test | Anonymous `GET /api/v1/auth/me` returned HTTP 401. | [Authenticated-user endpoint](../../api/modules/auth/v1/me-current.php). |
| 19. Database connectivity and migrated schema | Development database connected; five required tables and the user-profile foreign-key type compatibility were confirmed. | [Database migration guide](../database/database_setup_and_migration.md) and migration files under `api/database/migrations/`. |
| 29. API route/OpenAPI coverage | All 20 registered `/v1` routes had OpenAPI paths. | [Route definitions](../../api/routes.php) and [OpenAPI specification](../api/openapi.yaml). |
| 30. Dependency vulnerability audit | `composer audit --locked --no-interaction` reported no known advisory for locked dependencies on 2026-07-28. | [composer.lock](../../composer.lock); repeat with the stated Composer command when network access is approved. |
| 46. Encryption round-trip | Synthetic AES-256-GCM value encrypted and decrypted successfully. | [Encryption documentation](../api/encryption.md) and application cryptography implementation. |
| 47. Database index verification | Five named MySQL indexes were verified after migrations. | [Database indexing guide](../database_indexing.md) and `20260728_core_performance_indexes.php`. |

## Public and anonymous API checks

| Test | Recorded evidence | Supporting artefact / repeat method |
| --- | --- | --- |
| 13. Boundary API availability | `GET /api/v1/boundaries` returned HTTP 200. | [Boundary endpoint documentation](../api/boundary.md). |
| 14. Public survey required reference | Missing reference returned HTTP 404. | [Public-survey API routes](../../api/routes.php). |
| 15. Public survey invalid reference | Invalid reference returned HTTP 404. | [Public-survey API routes](../../api/routes.php). |
| 16. QR route authentication | Anonymous `GET /api/v1/qr` returned HTTP 401. | [API access-control guide](../api/access_control.md). |
| 17. Analytics route authentication | Anonymous `GET /api/v1/analytics/summary` returned HTTP 401. | [API access-control guide](../api/access_control.md). |
| 18. CAPA route authentication | Anonymous `GET /api/v1/capa/actions` returned HTTP 401. | [CAPA endpoint](../../api/modules/capa/v1/actions.php). |
| 20. Anonymous logout | Empty anonymous logout request returned HTTP 401. | [Authentication module](../../api/modules/auth/v1/logout.php). |
| 21. Anonymous QR generation | Empty anonymous QR-generation request returned HTTP 401. | [QR API module](../../api/modules/qr/). |
| 22. Invalid public-survey location | Empty location request returned HTTP 422. | [Public-survey location endpoint](../../api/modules/public-survey/v1/location.php). |
| 23. Invalid public-survey submission | Empty submission request returned HTTP 422 before persistence. | [Public-survey submission endpoint](../../api/modules/public-survey/v1/submit.php). |
| 24. Anonymous profile read/update | Anonymous profile GET and empty POST both returned HTTP 401. | [Profile endpoint](../../api/modules/auth/v1/profile.php). |
| 25. Anonymous response list/view/export | Anonymous response list, view and export requests returned HTTP 401. | [Response endpoints](../../api/modules/responses/v1/). |
| 26. Anonymous password/CAPA update | Empty anonymous password-change and CAPA-update requests returned HTTP 401. | [Change-password endpoint](../../api/modules/auth/v1/change-password.php) and [CAPA endpoint](../../api/modules/capa/v1/actions.php). |
| 27. CSRF-token access control | Anonymous CSRF request returned HTTP 401; authenticated session received HTTP 200 and a token. | [CSRF endpoint](../../api/modules/auth/v1/csrf-current.php) and [CSRF-token endpoint](../../api/modules/auth/v1/csrf-token.php). |
| 34. SQL-injection input handling | On 2026-07-29, a non-destructive quote-based payload returned HTTP 404 from public reference resolution and HTTP 200 with zero results from authenticated response search; no SQL, driver, stack-trace or fatal-error detail was exposed. | [Public-survey API routes](../../api/routes.php), [response endpoints](../../api/modules/responses/v1/) and prepared-statement data access. |

## Browser, security and operational checks

| Test | Recorded evidence | Supporting artefact / repeat method |
| --- | --- | --- |
| 28. HTTP security headers | Development documentation response included content-type, frame, referrer, CSP and cache-control headers. | [Root web configuration](../../web.config). |
| 31. Browser/mobile responsive baseline | At 390px, Testing page exposed the menu, used an off-canvas sidebar and had no horizontal overflow. | [Documentation UI](../../ui/docs.php) and [documentation CSS](../../ui/assets/css/docs.css). |
| 32. Development-environment performance baseline | Twenty sequential test-results requests were HTTP 200; minimum 64 ms, maximum 411 ms, average 137.95 ms. | Development-environment recorded timing; repeat through the rendered [test results page](test_results.md). |
| 33. Privacy and security exposure baseline | HTTPS access to `.env` and `.git/config` returned a generic unavailable-resource response; anonymous auth response had no stack trace, database detail or credential text. | [Root web configuration](../../web.config), [API web configuration](../../api/web.config), and [security guide](../security.md). |
| 39. Session-store resilience | With Memurai stopped, unauthenticated `auth/me` returned controlled HTTP 503 without error content; after restart, `PING`, fresh sign-in and `auth/me` all succeeded. | [Session manager](../../api/core/SessionManager.php) and [Memurai configuration guide](../deployment/memurai_session_configuration.md). |
| 40. Kafka event path | The application event layer published a non-sensitive `survey.response.submitted` event and the isolated local Kafka consumer received it; broker-unavailable fallback retained a local event log. | [Kafka development and production configuration](../architecture/event_driven_architecture.md), [event layer](../../api/core/Event.php), and `api/storage/events/` development logs. |
| 41. Database migration and restore | Schema-only and data backup/restores to separate temporary development databases preserved core-table row counts; temporary databases were removed. | [Backup and restore guide](../deployment/backup_restore_guide.md). |
| 48. NVDA screen-reader journey | NVDA Speech Viewer with Firefox announced landmarks, fields, validation, CAPTCHA, controls and navigation across the tested administrator journey. | [WCAG and web-platform compliance evidence](wcag_web_platform_compliance.md). |

## Authenticated workflow checks

| Test | Recorded evidence | Supporting artefact / repeat method |
| --- | --- | --- |
| 36. Role and service-location scope | Main-administrator and facility-administrator sessions passed protected read checks; facility user was denied an outside service location; logout passed. | [API access-control guide](../api/access_control.md) and authenticated endpoint modules. |
| 37. Public survey end-to-end submission | Authorized generated reference resolved; questions/location checks passed; synthetic response returned HTTP 201; duplicate device submission returned HTTP 409; administrator logout passed. | [Public-survey API routes](../../api/routes.php) and [survey publishing documentation](../survey-version-publishing.md). |
| 38. Analytics, reports, export and CAPA workflow | Filtered analytics/list requests passed; labelled synthetic CAPA record was created, updated and read; filtered CSV export returned HTTP 200 as an attachment. | [Analytics endpoints](../../api/modules/analytics/), [response export endpoint](../../api/modules/responses/v1/export.php), and [CAPA endpoint](../../api/modules/capa/v1/actions.php). |

## Status relationship

This register supports only tests marked Pass in [test results](test_results.md). Partial tests, including formal VAPT, accessibility keyboard journeys, capacity, and wider cross-browser/UAT validation, remain governed by the [test plan](test_plan.md) and must not be inferred as passed from this register.

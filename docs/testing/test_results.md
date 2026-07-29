# Test results — updated 2026-07-29

## Result scope

The following checks were executed in the development environment on 2026-07-28 and 2026-07-29. Each result includes the command or request used as evidence. The [passed-test evidence register](test_evidence_register.md) indexes the supporting artefacts and repeat methods for every test currently marked Pass. See the separate [VAPT test report](vapt_test_report.md) and [performance test results](performance_test_results.md) for their detailed scope and evidence.

| No. | Test | Result | Evidence / outcome |
| --- | --- | --- | --- |
| 1 | PHP syntax lint | Pass | `php -l` executed for 105 application, UI and tool PHP files; 0 failures after compatibility fixes. |
| 2 | JSON configuration/API validation | Pass | 36 JSON files in master data, API documentation, evidence and UI were parsed successfully. |
| 3 | Web-server configuration XML | Pass | `web.config` parsed as valid XML. |
| 4 | Composer manifest | Pass with warnings | `composer validate --no-check-publish` completed successfully. Composer warns that the root manifest should declare its licence and avoid an exact dependency constraint. |
| 5 | Survey publishing validation | Pass | `publish_survey_version.php` dry run for department 1 and candidate version 1.1 passed; 40 question records validated; no files changed. |
| 6 | QR generation smoke test | Pass | `php tools/testqr.php` exited with code 0. |
| 7 | CAPTCHA API smoke test | Pass | `GET /api/v1/auth/captcha` returned HTTP 200 locally. |
| 8 | Authentication protection smoke test | Pass | Anonymous `GET /api/v1/auth/me` returned HTTP 401 as expected. |

## Live read-only API smoke tests

| No. | Test | Result | Evidence / outcome |
| --- | --- | --- | --- |
| 13 | Boundary API availability | Pass | `GET /api/v1/boundaries` returned HTTP 200. |
| 14 | Public survey required-reference handling | Pass | `GET /api/v1/public-survey/resolve` without a reference returned HTTP 404. |
| 15 | Public survey invalid-reference handling | Pass | `GET /api/v1/public-survey/resolve?ref=invalid-test-reference` returned HTTP 404. |
| 16 | QR route authentication | Pass | Anonymous `GET /api/v1/qr` returned HTTP 401. |
| 17 | Analytics route authentication | Pass | Anonymous `GET /api/v1/analytics/summary` returned HTTP 401. |
| 18 | CAPA route authentication | Pass | Anonymous `GET /api/v1/capa/actions` returned HTTP 401. |
| 19 | Database connectivity and migrated schema | Pass | PHP connects to the development database with the required migrations applied. All five required tables are present: `user_master`, `user_profile_secure`, `auth_login_attempts`, `srvy_responses`, and `capa_actions`. The `user_profile_secure.user_id` type matches `user_master.u_id`, confirming foreign-key compatibility. |

## Extended safe API checks

| No. | Test | Result | Evidence / outcome |
| --- | --- | --- | --- |
| 20 | Anonymous logout | Pass | `POST /api/v1/auth/logout` with an empty JSON object returned HTTP 401. |
| 21 | Anonymous QR generation | Pass | `POST /api/v1/qr/generate` with an empty JSON object returned HTTP 401. |
| 22 | Invalid public-survey location | Pass | `POST /api/v1/public-survey/location` with an empty JSON object returned HTTP 422. |
| 23 | Invalid public-survey submission | Pass | Empty `POST /api/v1/public-survey/submit` now returns HTTP 422, confirming invalid payloads are rejected before persistence. |
| 24 | Anonymous profile read/update | Pass | Anonymous `GET` and empty `POST /api/v1/auth/profile` both return HTTP 401, confirming profile data and updates require a session. |
| 25 | Anonymous response list/view/export | Pass | Anonymous `GET /api/v1/responses`, `/view`, and `/export` all return HTTP 401, confirming response data and export are protected. |
| 26 | Anonymous password change and CAPA update | Pass | After database migration, empty anonymous `POST /api/v1/auth/change-password` and `/api/v1/capa/actions` both returned HTTP 401, confirming protected write access is rejected before any update occurs. |
| 27 | CSRF-token access control | Pass | On 2026-07-29, anonymous `GET /api/v1/auth/csrf` returned HTTP 401 after the access-control fix. The same endpoint returned HTTP 200 with a valid authenticated development session and included a CSRF token. The legacy CSRF-token alias now applies the same session requirement. |
| 34 | SQL-injection input handling | Pass | On 2026-07-29, a non-destructive quote-based SQL-injection payload returned HTTP 404 from public-survey reference resolution and returned HTTP 200 with zero results from an authenticated response-search request. Neither response disclosed SQL syntax, database-driver, stack-trace, or fatal-error details. |

## Additional documentation and security checks

| No. | Test | Result | Evidence / outcome |
| --- | --- | --- | --- |
| 28 | HTTP security headers | Pass | Development-environment documentation response includes `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Content-Security-Policy`, and `Cache-Control`. |
| 29 | API route / OpenAPI coverage | Pass | All 20 currently registered `/v1` routes in `api/routes.php` have a corresponding path in `docs/api/openapi.yaml`. |
| 30 | Dependency vulnerability audit | Pass | `composer audit --locked --no-interaction` completed successfully on 2026-07-28; no known security vulnerability advisories were found for locked Composer dependencies. |
| 31 | Browser/mobile responsive baseline | Pass | At a 390px mobile viewport, the Testing page showed its menu control, used an off-canvas fixed sidebar, and had no horizontal overflow (`scrollWidth = clientWidth = 375`). The viewport override was reset after the check. |
| 32 | Development-environment performance baseline | Pass | 20 sequential requests to the rendered Test results page returned HTTP 200: minimum 64 ms, maximum 411 ms, average 137.95 ms. This is a development-environment smoke baseline, not a capacity/load-test result. |
| 33 | Privacy and security exposure baseline | Pass | HTTPS requests to `/.env` and `/.git/config` do not expose file contents or IIS handler/configuration details; both return the same generic unavailable-resource response. Anonymous `/api/v1/auth/me` output contained no detected stack trace, database-connect detail, or credential text. |

## Defects found and corrected during this run

| No. | Finding | Resolution | Retest |
| --- | --- | --- | --- |
| 1 | Four PHP files did not lint on the active PHP runtime because callable properties and database-dependent parameter defaults were invalid. | Replaced callable properties with stored `Closure` instances and initialized repositories inside service constructors. | All 105 PHP files now lint successfully. |
| 2 | The survey publisher rejected active `duration`, `numeric`, and `text` question types and required two options for a numeric prompt that correctly has one. | Added the active types and a one-option numeric validation rule. | Department 1 dry run now passes without writing files. |

## Additional validation before production

The following checks require a representative environment. Their recorded status reflects the evidence available from the development-environment run; any Partial item requires completion and retesting before production approval.

| No. | Test | Current status | Required evidence |
| --- | --- | --- | --- |
| 36 | Authenticated role and service-location scope baseline | Pass | The provided main-administrator account authenticated as role 3 (`MAIN_ADMIN`); the provided service-location account authenticated as role 2 (`FACILITY_ADMIN`). For both accounts, `auth/me`, QR, analytics and response-list reads returned HTTP 200; POST logout returned HTTP 200. The facility account received HTTP 403 when requesting responses for an outside service-location identifier. CAPA list without its required selector returned HTTP 422 and is not marked as a workflow pass. |
| 37 | Public survey end-to-end submission | Pass | Using an authorized generated public reference and facility coordinates, the flow returned HTTP 200 for reference resolution, questions and location validation; a synthetic response was accepted with HTTP 201; a repeat from the same synthetic device identifier was rejected with HTTP 409. Administrator session logout returned HTTP 200. |
| 38 | Analytics, reports, export and CAPA workflow | Pass | Authenticated filtered analytics and response-list requests returned HTTP 200. A labelled synthetic CAPA action was created, updated, and read back successfully (all HTTP 200; one updated action observed). The response-export HTTP 500 was corrected by supplying the explicit CSV escape argument and aligning export filtering and facility scope with the response-list endpoint. On 2026-07-29, an authenticated filtered export returned HTTP 200 as a `text/csv; charset=utf-8` attachment (201 bytes). |
| 39 | Session-store resilience | Pass | On 2026-07-29, Memurai was manually stopped and the unauthenticated `auth/me` check returned controlled HTTP 503 with no error content. After Memurai was started again, the service returned `PING`, a fresh authenticated development-environment login succeeded, and `auth/me` returned HTTP 200. |
| 40 | Kafka event path (if enabled) | Pass | On 2026-07-29, `php-rdkafka`, the development Kafka configuration, and the isolated local broker were verified. The application event layer published a non-sensitive `survey.response.submitted` test event to `abhipraya.survey.response.submitted`, and the Kafka consumer received the expected event envelope. With the broker previously unavailable, a request retained its local event-log entry and returned its normal HTTP 401 response, confirming safe fallback behaviour. See the [development PHP/Kafka configuration](../architecture/event_driven_architecture.md#development-environment-configuration) and [production PHP/Kafka configuration](../architecture/event_driven_architecture.md#production-configuration). |
| 41 | Database migration and restore | Pass | The migrated schema was validated with all five core tables present and compatible. A schema-only backup/restore and a full data backup/restore were each run into separate uniquely named temporary development databases. Core table row counts (`user_master`, `srvy_responses`, `capa_actions`) matched after restore; both temporary databases were removed successfully. The active application database was not modified. |
| 42 | Accessibility | Partial | At a 390px viewport, the tested documentation page had no horizontal overflow and exposed its mobile menu. Semantic checks found one H1, banner/navigation/main/complementary landmarks, table headers, no images without `alt`, and no unlabeled controls. Tested colour pairs meet WCAG AA contrast: body 15.26:1, paragraph text 7.71:1, links 6.65:1, focus outline 6.31:1. The mobile menu control can receive focus; automated Tab progression was inconclusive in the development-environment automation session. Full keyboard-only public/admin journeys remain required. |
| 48 | NVDA screen-reader journey | Pass | On 2026-07-29, NVDA Speech Viewer with Firefox announced skip links, banner/main/navigation landmarks, headings, required fields, invalid-entry state, CAPTCHA instructions, login errors, buttons, links, comboboxes, tabs, and alert messages. The tested journey covered administrator sign-in, dashboard, QR Center (including poster download and copied survey link), analytics, reports, CAPA, and sign-out navigation. |
| 43 | VAPT and privacy review baseline | Partial | On 2026-07-29, the development-environment VAPT baseline verified the HTTPS administrator sign-in surface at `https://localhost`, generic 404 responses for `/.env`, `/.git/config`, `web.config` and `api/web.config`, and HTTP 401 for unauthenticated `auth/me` and response export requests. AES-256-GCM is available. The anonymous CSRF-token endpoints were corrected: they now return HTTP 401 without a session and HTTP 200 with an authenticated session. The prior Composer audit passed, but a fresh advisory lookup was blocked by restricted network access. This baseline is not a substitute for an independent formal VAPT, production TLS verification, retention/data-flow review, or an approved authenticated vulnerability assessment. |
| 44 | Performance and capacity | Partial | Development-environment baseline passed (20 sequential documentation requests; 137.95 ms average). A controlled read-only load smoke test then ran 100 documentation and 100 public-boundary API requests at concurrency 10: both had 100% HTTP 200 success with zero errors. Observed averages/p95 were 613.35/3990 ms for documentation and 441.32/3408 ms for boundaries (maximums 5512/8492 ms). No capacity target has been approved and this development-environment result is not production-representative; set targets and repeat in UAT/production-like infrastructure before release approval. |
| 45 | Cross-browser/mobile and UAT | Partial | In the available Chromium-compatible browser, the administrator sign-in page was tested at 320px, 390px, and 768px: all widths had no horizontal overflow, visible sign-in control, and no unlabeled inputs. The documentation mobile baseline also passed. Testing in additional supported browser engines/devices and business-owner UAT acceptance remain required. |
| 46 | Encryption round-trip | Pass | Application AES-256-GCM encryption/decryption of a synthetic value completed successfully. |
| 47 | Database index verification | Pass | Applied `20260728_core_performance_indexes.php` after the idempotent response survey-version migration. MySQL verification confirmed `idx_user_master_scope (NIN_fk, u_role, active)`, `idx_auth_login_attempts_locked_until (locked_until)`, `idx_srvy_responses_scope_time (hospital_nin, department_id, srvy_rpl_dt)`, `idx_srvy_responses_survey (survey_code, survey_version, survey_schema_hash)`, and `idx_capa_scope (hospital_nin, month, question_key)`. |

Follow the [quality assurance and release test plan](test_plan.md) to complete these remaining checks.

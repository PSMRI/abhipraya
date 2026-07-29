# VAPT test report

## Scope

This report records the development-environment vulnerability-assessment checks completed on 2026-07-29. It is a technical baseline, not an independent formal VAPT certificate or production security approval. No production host, external organisation, or destructive exploit technique was used.

## Result summary

| Area | Result | Evidence |
| --- | --- | --- |
| HTTPS administrator sign-in | Pass | `https://localhost` rendered the administrator sign-in surface. |
| Sensitive-file exposure | Pass | HTTPS requests for `/.env`, `/.git/config`, `web.config`, and `api/web.config` returned a generic unavailable-resource response, without handler/configuration detail. |
| Anonymous protected API access | Pass | Anonymous `auth/me` and response-export requests returned HTTP 401. |
| CSRF-token access control | Pass | Anonymous CSRF access returned HTTP 401; a valid authenticated session returned HTTP 200 and a token. |
| SQL-injection input handling | Pass | A safe quote-based payload returned HTTP 404 for public reference resolution and HTTP 200 with zero results for authenticated response search; no SQL/driver/stack-trace detail was exposed. |
| Session-store outage handling | Pass | When Memurai was stopped, `auth/me` returned controlled HTTP 503 without error content; after recovery, sign-in and `auth/me` returned HTTP 200. |
| Encryption capability | Pass | AES-256-GCM synthetic encryption/decryption round trip completed successfully. |
| Dependency advisory audit | Pass (recorded) | `composer audit --locked --no-interaction` completed with no known advisory on 2026-07-28. A fresh external advisory lookup was not repeated because network access was restricted. |
| Formal authenticated VAPT | Pending | Requires approved independent assessor, representative environment, scope approval, authenticated test account, and formal report. |

## Detailed test cases

| ID | Test | Method | Expected result | Observed result | Status |
| --- | --- | --- | --- | --- | --- |
| VAPT-01 | HTTPS availability | Open the administrator sign-in URL over HTTPS. | TLS-protected sign-in surface loads. | Sign-in surface loaded at `https://localhost`. | Pass |
| VAPT-02 | Hidden-file protection | Request sensitive deployment/source paths over HTTPS. | Generic unavailable response; no source/configuration disclosure. | All four tested paths returned generic unavailable responses. | Pass |
| VAPT-03 | Anonymous authorization | Request protected user and export APIs without a session. | HTTP 401 and no protected data. | Both protected requests returned HTTP 401. | Pass |
| VAPT-04 | CSRF-token authorization | Request CSRF endpoint anonymously and with a valid session. | Anonymous denied; authenticated session receives a token. | HTTP 401 anonymous; HTTP 200 authenticated. | Pass |
| VAPT-05 | SQL injection | Send a non-destructive quote-based payload through public-reference and authenticated-search inputs. | No data bypass, HTTP 4xx/normal empty result, no database error disclosure. | HTTP 404 public; HTTP 200/zero authenticated results; no error detail. | Pass |
| VAPT-06 | Session-store failure | Stop the development Memurai service; request a session-protected endpoint; restart and re-authenticate. | Controlled 401/503 during outage; normal authenticated recovery after restart. | Controlled HTTP 503 without body during outage; PING/login/authenticated HTTP 200 after recovery. | Pass |
| VAPT-07 | Cryptographic availability | Run a synthetic application encryption/decryption round trip. | AES-256-GCM can encrypt and decrypt correctly. | Round trip succeeded. | Pass |
| VAPT-08 | Dependency advisory review | Run Composer audit against locked dependencies. | No known advisory or documented remediation. | Recorded audit completed without a known advisory. | Pass (recorded) |

## Security controls verified in source

- MySQLi prepared statements are used for data parameters; the SQL-injection test covered public reference and response-search inputs.
- Session failure handling returns a generic service-unavailable response rather than a PHP/IIS/database error.
- CSRF endpoints require an authenticated session.
- IIS configuration routes sensitive-file requests to generic error handling.
- Kafka, database, session, and encryption secrets are not recorded in this report.

## Required before production approval

1. Commission an independent authenticated VAPT against an approved representative environment.
2. Verify production certificate chain, TLS versions/ciphers, redirect policy, HSTS, and security headers.
3. Test approved authenticated roles and all high-risk business workflows with written scope and non-production data.
4. Review retention, data flows, exports, audit logging, backup protection, and incident-response arrangements.
5. Repeat dependency advisory audit with approved external network access and record the date/output.

## Related records

- [Test results](test_results.md)
- [Passed-test evidence register](test_evidence_register.md)
- [Security guide](../security.md)
- [API access control](../api/access_control.md)

# Test plan

## Test objectives

Verify that Abhipraya safely collects anonymous feedback, enforces administrative scope, preserves survey-version history, and produces consistent Home, Analytics, Reports, QR, and CAPA results.

## Core functional tests

| Area | Minimum test |
| --- | --- |
| Login | Valid sign-in, invalid login, CAPTCHA, temporary lockout, sign-out, session timeout. |
| Scope | Facility Administrator cannot list, filter, export, or request another facility's data. |
| QR | Generate and resolve a permitted facility-department QR link. |
| Public survey | Language selection, required validation, location validation, duplicate control, anonymous submission. |
| Survey versions | Old response remains associated with its original version and schema hash. |
| Analytics | Rating stars, binary favourable/unfavourable values, category distributions, numeric/duration results. |
| Reports | Scope, period, facility count, average score/star presentation, exports. |
| CAPA | Create, edit, version-reference, and download a permitted action record. |
| Profile | Update permitted fields and change password with CSRF validation. |

## Release gate

- PHP lint passes for changed files.
- IIS XML configuration validates.
- Required routes return expected status codes.
- No inline scripts/styles are added where CSP blocks them.
- Manual authenticated scope tests are completed for each role.
- Accessibility and responsive checks are completed for affected pages.
- Database migration and rollback/restore checks are documented.

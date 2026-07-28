# Developer guide

## Architecture

- `ui/` contains browser pages, JavaScript, CSS, and the UI router.
- `api/` contains versioned PHP APIs, security helpers, database access, and master survey configuration.
- `api/masters/` contains survey questions, facilities, departments, button labels, and radius configuration.
- The repository includes `web.config` reference rules for browser pages and versioned APIs; equivalent Apache or Nginx rules may be used in other deployments.

## Local configuration

Create a local `.env` with database connection values. Never commit `.env` or production credentials.

## Application routes

- `/` redirects to `/admin/login`.
- `/survey?nin=FACILITY_NIN_DEPARTMENT_ID` opens the public survey language page.
- `/question.php?nin=FACILITY_NIN_DEPARTMENT_ID` remains compatible with existing QR posters.
- `/api/v1/...` routes through the API allow-list.

## Required deployment steps

1. Configure an HTTPS binding and redirect HTTP to HTTPS.
2. Apply database migrations in `api/database/migrations/`, including `20260721_login_rate_limit.sql`.
3. Set production database values outside the web root.
4. Reload the selected PHP runtime or application process after deployment.
5. Run a VAPT retest against the deployed HTTPS URL.

## Extending surveys

Add questions by department in `api/masters/dept_id_<departmentId>.json`. Use language code `1` for English and `2` for Hindi. Survey radius defaults and explicit per-facility overrides belong in `api/masters/radius.json`.

## Validation and test status

The following static checks were run on the workspace on 2026-07-21:

- The IIS reference XML configuration passed validation for `web.config` and `api/web.config`.
- Required landing, documentation, security, and migration assets are present.
- No third-party JavaScript `<script>` sources were found in the UI pages.
- The landing route, documentation renderer route, and developer hub route are registered.
- Modified PHP files for routing, sessions, CSRF, authentication, and public survey work passed `php -l` checks.

### Existing PHP lint blockers

Four existing files fail standalone PHP lint and must be corrected before a full release gate can pass:

| File | Reported issue |
| --- | --- |
| `api/middleware/AuditMiddleware.php` | `?callable` typed property is not valid PHP. |
| `api/middleware/RateLimitMiddleware.php` | `?callable` typed property is not valid PHP. |
| `api/services/FacilityService.php` | Invalid constant expression. |
| `api/services/PublicSurveyService.php` | Invalid constant expression. |

### Production test checklist

1. Apply all database migrations, including `20260721_login_rate_limit.sql`.
2. Reload the selected PHP runtime or application process after deployment.
3. Open `/`, `/developer.php`, and each `/docs/*.md` guide.
4. Verify administrator login, CAPTCHA, five-failure lockout, QR generation, survey location validation, language selection, and submission.
5. Verify HTTPS headers and cookies with a production VAPT retest.

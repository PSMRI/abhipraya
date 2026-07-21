# Developer guide

## Architecture

- `ui/` contains browser pages, JavaScript, CSS, and the UI router.
- `api/` contains versioned PHP APIs, security helpers, database access, and master survey configuration.
- `api/masters/` contains survey questions, facilities, departments, button labels, and radius configuration.
- Root `web.config` routes browser pages; `api/web.config` routes versioned APIs.

## Local configuration

Create a local `.env` with database connection values. Never commit `.env` or production credentials.

## IIS routes

- `/` redirects to `/admin/login`.
- `/survey?nin=FACILITY_NIN_DEPARTMENT_ID` opens the public survey language page.
- `/question.php?nin=FACILITY_NIN_DEPARTMENT_ID` remains compatible with existing QR posters.
- `/api/v1/...` routes through the API allow-list.

## Required deployment steps

1. Configure an HTTPS binding and redirect HTTP to HTTPS.
2. Apply database migrations in `api/database/migrations/`, including `20260721_login_rate_limit.sql`.
3. Set production database values outside the web root.
4. Recycle the IIS application pool after deployment.
5. Run a VAPT retest against the deployed HTTPS URL.

## Extending surveys

Add questions by department in `api/masters/dept_id_<departmentId>.json`. Use language code `1` for English and `2` for Hindi. Survey radius defaults and explicit per-facility overrides belong in `api/masters/radius.json`.

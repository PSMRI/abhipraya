# Developer guide

## Source code

The Abhipraya source repository is [PSMRI/abhipraya](https://github.com/PSMRI/abhipraya). Use the repository for cloning the project, reviewing change history, creating branches, and contributing approved changes.

For authorised development-account creation, see [Administrator and service-location user provisioning](user_provisioning.md). Do not store plaintext passwords in SQL files or source control.

## Architecture

- `ui/` contains browser pages, JavaScript, CSS, and the UI router.
- `api/` contains versioned PHP APIs, security helpers, database access, and master survey configuration.
- `api/masters/` contains survey questions, facilities, departments, button labels, and radius configuration.
- The repository includes `web.config` reference rules for browser pages and versioned APIs; equivalent Apache or Nginx rules may be used in other deployments.

## Development environment configuration

Create a development-environment `.env` with database connection values. Never commit `.env` or production credentials.

### Redis / Memurai session development

Abhipraya supports Redis-compatible Memurai for shared administrator sessions. This is recommended when multiple PHP/web-server instances serve the application. The application uses PHP's native Redis session handler, so configure the connection in the active `php.ini`; do not put Memurai passwords in source code or browser JavaScript.

```ini
; php.ini — example only
extension=redis
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?auth=<memurai-password>&database=0"
```

In the development-environment `.env`, set `ABHIPRAYA_SESSION_HANDLER=redis`. If the PHP configuration already has `session.save_handler = redis`, this variable is optional. For a development or single-server setup without Memurai, use `ABHIPRAYA_SESSION_HANDLER=files`.

Verify the selected PHP runtime before starting the application:

```text
php -m | findstr redis
php --ri redis
```

Both the web-server PHP runtime and the command-line PHP runtime must load the Redis extension from their respective active PHP configurations. Test a sign-in, a protected page request, and sign-out after changing session configuration. See [User session management](api/user_session_management.md) for the full lifecycle and the [Memurai session configuration guide](deployment/memurai_session_configuration.md) for complete development and production instructions.

### Optional Kafka event development

Kafka is disabled by default. To develop a consumer or integration, install `php-rdkafka`, run an approved development broker, and set `ABHIPRAYA_EVENT_DRIVER=kafka` plus the broker values in the uncommitted `.env` file. Follow the [Kafka development and production configuration](architecture/event_driven_architecture.md#development-environment-configuration) guide; never point a development workstation at a production broker.

## Application routes

- `/` redirects to `/admin/login`.
- `/survey?nin=FACILITY_NIN_DEPARTMENT_ID` opens the public survey language page.
- `/question.php?nin=FACILITY_NIN_DEPARTMENT_ID` remains compatible with existing QR posters.
- `/api/v1/...` routes through the API allow-list.

## Modify or add an API

| Step | What to change | Required practice |
| --- | --- | --- |
| 1. Define the contract | `docs/api/api_specifications.md` and endpoint inventory | Decide method, path, request fields, response, roles, scope, validation, and error cases before coding. |
| 2. Register the route | `api/routes.php` | Add only the intended method and versioned `/v1/...` path. A module file is not public until it is allow-listed. |
| 3. Create the handler | `api/modules/<feature>/v1/<action>.php` | Load `public_api.php`; validate method/input; return only through `Response`. |
| 4. Apply protection | Session, role/scope, CSRF helpers | Require session for administrator APIs, enforce service-location scope on the server, and validate CSRF for writes. |
| 5. Add business/data logic | `api/services/`, `api/repositories/`, or a small scoped module query | Use prepared statements. Use `PersisterService::transaction()` when multiple related writes must succeed together. |
| 6. Test and document | PHP lint, browser/API test, docs | Test success, validation, `401`, `403`, scope rejection, and error-envelope behaviour. Update API documentation. |

Maintain the [OpenAPI definition and Postman collection](api/openapi_postman.md) whenever the API contract changes.

Minimal handler pattern:

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
Security::requireMethod('GET');
SessionManager::requireLogin();

// Validate scope and input, call approved service/repository code.
Response::success('Resource loaded.', ['items' => []]);
```

Do not add an endpoint by linking browser JavaScript directly to an arbitrary PHP file. Do not trust a user ID, role, or service-location ID sent by the browser.

## Develop or add a UI page

| Step | Location | Required practice |
| --- | --- | --- |
| 1. Add view | `ui/pages/<feature>/` | Use semantic HTML and existing page-shell patterns. |
| 2. Register route | `ui/router.php` | Add the public or administrator route to the `$views` allow-list. Add administrator routes to `$adminRoutes` so the router checks the session before serving the page. |
| 3. Add behaviour | `ui/assets/js/<feature>.js` | Reuse the existing request/CSRF/session helpers; never embed secrets or authorisation rules in JavaScript. |
| 4. Add styling | `ui/assets/css/<feature>.css` | Reuse tokens/components first; keep feature styles scoped and responsive. |
| 5. Connect API | `/api/v1/...` | Handle loading, success, validation, unauthorised, and forbidden states clearly. |
| 6. Test | Browser, keyboard, narrow screen | Test focus order, labels, error messages, network failures, and mobile/tablet layout. |

## Reuse existing UI components

| Need | Reuse first | Location |
| --- | --- | --- |
| Global design values | Colours, spacing, type scale | `ui/assets/css/tokens-v2.css`, `variables.css`, `brand.css` |
| Buttons, cards, forms, tables, alerts | Existing component classes | `buttons.css`, `cards.css`, `forms.css`, `tables.css`, `alerts.css`, `components.css` |
| Layout and responsive behaviour | App shell, grid, mobile rules | `app-shell.css`, `layout.css`, `responsive.css` |
| API and session requests | API/CSRF/session helpers | `api.js`, `csrf.js`, `session.js`, `session-keepalive.js` |
| Accessible interactions | Modal, confirmation, notification, loading, pagination | `modal.js`, `confirm.js`, `notifications.js`, `loader.js`, `pagination.js` |
| Form checks and controls | Validation, searchable select, date utilities | `validation.js`, `forms.js`, `searchable-select.js`, `date-utils.js` |
| Survey UI | Renderer, validation, local survey state | `survey-renderer.js`, `survey-validator.js`, `survey-storage.js` |

Before creating a new component, search these shared assets and an existing administrator page for a matching pattern. Use `textContent` for untrusted values, labelled controls, visible focus, and text in addition to icons/colour.

## Change workflow

1. Create a focused branch and read the relevant architecture/API/configuration documentation.
2. Reuse an existing route, handler, component, or style pattern where possible.
3. Implement server-side validation and access control before browser convenience behaviour.
4. Run `php -l` for changed PHP files and test the affected API/UI journey.
5. Update the relevant docs, sidebar entry, test evidence, and third-party notices if a dependency changed.
6. Submit the change for review with the route/API/security impact stated clearly.

## Required deployment steps

1. Configure an HTTPS binding and redirect HTTP to HTTPS.
2. Apply database migrations in `api/database/migrations/`, including `20260721_login_rate_limit.sql`.
3. Set production database values outside the web root.
4. Reload the selected PHP runtime or application process after deployment.
5. Run a VAPT retest against the deployed HTTPS URL.

## Extending surveys

Add questions by department in `api/masters/dept_id_<departmentId>.json`. Use language code `1` for English and `2` for Hindi. Survey radius defaults and explicit per-facility overrides belong in `api/masters/radius.json`.

## Validation and test status

The following static checks were run in the development environment on 2026-07-21:

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

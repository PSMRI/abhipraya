# Technical architecture overview

## Architecture at a glance

Abhipraya is a PHP and MySQL web application with a static browser interface, versioned REST APIs, JSON-driven survey packages, and web-server routing. It separates public survey functions from authenticated administrator functions.

![Abhipraya technical architecture diagram](/ui/assets/img/docs/abhipraya-technical-architecture.svg)

```text
Public respondent or administrator browser
                 ↓
        Web-server routes and UI front controller
                 ↓
        Static UI pages and JavaScript
                 ↓
       Versioned API routes: /api/v1/...
                 ↓
 Core security, session, configuration, service, and repository layers
                 ↓
     JSON configuration files              MySQL transactional data
       surveys / roles / labels              responses / users / CAPA
```

## Browser and user-interface layer

The `ui/` directory contains the browser-facing pages, styles, scripts, images, and front controller.

| Route | Audience | Page purpose |
| --- | --- | --- |
| `/` | Public | Landing page and documentation entry point. |
| `/admin/login` | Administrators | Secure administrator sign-in. |
| `/admin/dashboard` | Administrators | Home dashboard with scope-based feedback summary. |
| `/admin/qr` | Administrators | QR Center for department survey posters. |
| `/admin/analytics` | Administrators | Type-aware indicators, trends, facility/department comparisons, and survey version filters. |
| `/admin/reports` | Administrators | Scoped report preview and export. |
| `/admin/capa` | Administrators | Corrective and preventive action tracking. |
| `/admin/account` | Administrators | Profile update and password change. |
| `/survey` and `/question.php` | Public | QR-led public survey entry; the legacy `question.php` QR format remains supported. |

The UI calls versioned endpoints through `/api/v1/`. Browser pages must not contain database credentials or run database queries directly.

## API layer

The API entry point uses an allow-list of routes defined in `api/routes.php`. A URL is not mapped directly to an arbitrary PHP file. This avoids accidental execution of unregistered module files.

### Main API groups

| API group | Main responsibility |
| --- | --- |
| `auth` | CAPTCHA, sign-in, sign-out, current-session details, CSRF token, profile update, and password change. |
| `public-survey` | Resolve QR context, load questions, validate location, and submit anonymous feedback. |
| `qr` | Generate, list, preview, and validate department QR links. |
| `analytics` | Produce scoped summary, indicator, distribution, trend, and survey-version data. |
| `responses` | List, view, and export permitted anonymous feedback records. |
| `capa` | Create, view, update, and download corrective and preventive actions. |

The public-survey APIs are intentionally separate from administrator APIs. A public respondent does not receive an administrator session or access to operational reports.

## Core application services

The `api/core/` layer provides the shared foundation:

- Environment and database bootstrap.
- Request routing and standard JSON responses.
- Session management, authentication, authorisation, CSRF protection, and security headers.
- JSON configuration loading and schema/version controls.
- Error handling and event/audit helpers.

Module handlers should delegate business rules to the service and repository layers rather than placing complex SQL or permission logic in browser code.

```text
Route handler
    ↓
Authentication and scope checks
    ↓
Service: validation and business rules
    ↓
Repository: prepared database access
    ↓
Response: consistent JSON result
```

## Scope enforcement

Data scope is a security boundary, not merely a filter choice.

- A **Facility Administrator** is bound to the facility NIN assigned at sign-in.
- The backend applies that facility NIN to dashboard, response, analytics, report, QR, and CAPA queries.
- A request that supplies another facility identifier is rejected rather than trusted.
- Higher-scoped roles receive the data allowed by their assigned role and scope.

The UI may lock or hide irrelevant filters for clarity, but it is the API that prevents cross-facility data access.

## JSON configuration layer

Abhipraya uses JSON files under `api/masters/` for stable product configuration:

```text
api/masters/
├── facilityCodes.json
├── departmet.json
├── rolecode.json
├── radius.json
├── buttons.json
├── thankyou_messages.json
└── surveys/
    └── department_<id>/
        ├── manifest.json
        └── v<version>/survey.json
```

The survey package defines question number, multilingual labels, option values, report type, icon, `indicator_key`, and `question_id`. The manifest identifies the active version and its SHA-256 schema hash.

Configuration is not duplicated into database master tables. Transactional records retain the submitted survey version and question identity so historical reporting remains meaningful after a new version is published.

## Feedback and analytics flow

```text
QR poster
    ↓
Facility + department are resolved
    ↓
Active survey version and schema hash are loaded
    ↓
Beneficiary submits answers
    ↓
Response and answer values are stored
    ↓
Analytics service applies scope, version, period, and indicator filters
    ↓
Home, Analytics, Reports, and CAPA consume the same interpreted result
```

The analytics layer follows the question `report_type`. A rating produces an average and stars; a binary or availability question produces favourable/unfavourable percentages; category and severity questions produce distributions; numeric and duration questions produce numeric summaries. This avoids using an incorrect star score for every question.

## Survey version safety

Published survey files are immutable. A new survey version is created when questions, options, rating scales, or reporting semantics change. Existing responses continue to reference the survey version and schema hash that was active when they were submitted.

This allows analytics and reports to filter by survey version and prevents old and new indicators from being silently treated as identical.

See [Publishing a survey version](../survey-version-publishing.md).

## Security boundaries

Key controls include:

- Authenticated administrator sessions with secure cookie settings and timeout.
- CSRF checks for authenticated write operations.
- Rate limiting, CAPTCHA, and failed-login lockout controls.
- Prepared database access through repositories.
- Allow-listed API routing.
- Content Security Policy and protective HTTP headers.
- QR and public-survey validation, including configured location checks where enabled.
- Scope enforcement for facility-bound users.

Deployment administrators must configure HTTPS, secure database credentials, backups, monitoring, and log retention. See the [Security guide](../security.md).

## Deployment model

Abhipraya can be deployed behind any web server or reverse proxy that can serve PHP, enforce HTTPS, and apply the documented route and security rules. The repository includes an IIS reference configuration; Apache and Nginx are supported deployment choices when those rules are translated.

```text
HTTPS client
    ↓
Web-server / reverse-proxy rewrite rules
    ↓
PHP UI router and API front controller
    ↓
MySQL and protected configuration/storage
```

The `.env` file holds environment-specific database values and must never be committed or exposed beneath the public web root. Production deployment should use HTTPS only and reload the selected application process or PHP runtime after configuration or code changes.

## Extension rules

When extending Abhipraya:

1. Add a new API only through the allow-listed route configuration.
2. Enforce authentication, CSRF, and user scope before data access.
3. Keep survey content in a new published JSON version rather than editing an existing published package.
4. Add a `report_type`, stable option values, and an `indicator_key` to every new analytical question.
5. Keep analysis and reports aligned by consuming the same analytics interpretation.
6. Update the relevant user, API, configuration, and testing documentation in the same change.

## Related documentation

- [Abhipraya overview](project_overview.md)
- [User guide](../user/user_guide.md)
- [Survey question types](../survey-question-types-reference.md)
- [Survey version publishing](../survey-version-publishing.md)
- [Developer guide](../developer-guide.md)

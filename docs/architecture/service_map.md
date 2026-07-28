# Service architecture and map

## Service architecture at a glance

![Abhipraya service architecture diagram](/ui/assets/img/docs/abhipraya-service-architecture.svg)

The service architecture separates public feedback functions from authenticated administrative operations. Every request enters through an allow-listed route, passes security and scope controls, and uses shared configuration or transactional data only through server-side services and repositories.

## Components

| Component | Location | Responsibility |
| --- | --- | --- |
| Public UI | `ui/pages/public-survey/` | Public QR survey screens and client-side interaction. |
| Administrator UI | `ui/pages/dashboard/`, `qr/`, `analytics/`, `reports/`, `capa/`, `account/` | Scope-aware operational interface. |
| UI router | `ui/router.php` | Resolves approved browser routes and protects administrator pages. |
| API router | `api/index.php`, `api/routes.php`, `api/core/Router.php` | Allows only registered `/api/v1/` endpoints. |
| Authentication | `api/modules/auth/v1/` | Login, session, CAPTCHA, CSRF, profile, and password operations. |
| Survey service | `api/modules/public-survey/v1/` | QR resolution, questions, location validation, and submission. |
| QR service | `api/modules/qr/v1/` | QR generation, preview, listing, and validation. |
| Analytics service | `api/modules/analytics/v1/summary.php` | Scoped dashboard, trends, distributions, and indicator summaries. |
| Response service | `api/modules/responses/v1/` | Permitted response list, detail, and export. |
| CAPA service | `api/modules/capa/v1/` | Corrective and preventive action records. |
| Configuration loader | `api/core/ConfigLoader.php` | JSON master and versioned survey package loading. |
| Database repositories | `api/repositories/` | Prepared access to transactional data. |

## Request lifecycle

```text
Browser request
  → Web-server or reverse-proxy route rule
  → UI router or API front controller
  → session / CSRF / scope checks
  → module handler
  → service and repository
  → MySQL or JSON configuration
  → standard HTML or JSON response
```

## Trust boundaries

- **Public survey:** can submit only validated survey answers; cannot access administrator endpoints.
- **Administrator session:** accesses only routes permitted by its authenticated role and scope.
- **JSON configuration:** is server-side trusted configuration; it is not editable through public input.
- **Database:** stores operational records; browser code must never connect directly.

## Adding a new service

New services can be added without changing the overall architecture. A service must be registered deliberately; browser code must never call an arbitrary PHP file or connect directly to the database.

```text
New feature requirement
        ↓
Register an allow-listed API route in api/routes.php
        ↓
Create the versioned module handler under api/modules/<service>/v1/
        ↓
Apply authentication, CSRF, and role/facility scope checks
        ↓
Implement business rules in a service class
        ↓
Use a repository for prepared MySQL access and ConfigLoader for JSON data
        ↓
Return the standard JSON response and record required audit events
        ↓
Add UI integration, tests, and documentation
```

### Service implementation rules

- Use a descriptive, versioned endpoint group such as `/api/v1/notifications` or `/api/v1/facility-settings`.
- Register every endpoint in the API allow-list. Do not expose a new file merely by placing it beneath `api/`.
- Enforce authentication, CSRF protection for state-changing administrator requests, and role/facility scope before retrieving or changing data.
- Keep request handling thin: validate input and delegate business logic to a service; use repositories for all database queries.
- Put stable configuration in JSON only when it belongs to the configuration model; store operational, user-generated, or auditable records in MySQL.
- Reuse standard error responses, logging, and audit helpers so that new services behave consistently with existing modules.
- Add the service to the component table above, update the diagram if it introduces a distinct service category, and add automated tests before release.

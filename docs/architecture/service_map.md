# Service architecture and map

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
  → IIS rewrite rule
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

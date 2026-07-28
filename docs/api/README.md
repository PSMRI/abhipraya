# API developer documentation

## Base path

Use the versioned API base path:

```text
/api/v1/
```

Routes are registered in `api/routes.php`. Do not add an endpoint by exposing a physical module file directly.

## Response rules

- Return JSON for API endpoints.
- Use appropriate HTTP status codes.
- Require an authenticated session for administrator endpoints.
- Require CSRF validation for authenticated write operations.
- Apply server-side scope checks before querying or returning data.
- Do not return passwords, session secrets, raw exception traces, or unnecessary personal data.

## Endpoint groups

- Authentication and profile.
- QR Center.
- Public survey resolution, questions, location, and submit.
- Analytics summary.
- Feedback responses and export.
- CAPA actions.

See [Endpoint inventory](endpoint_inventory.md).

For API conventions, standard request/response envelopes, authentication, HTTP status codes, and API extension rules, see [API specifications](api_specifications.md).

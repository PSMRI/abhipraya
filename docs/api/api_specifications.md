# API specifications

## API overview

Abhipraya exposes versioned JSON APIs for public feedback journeys and authenticated administrative functions. APIs are registered through an allow-list; a URL cannot invoke an arbitrary PHP module file.

| Item | Specification |
| --- | --- |
| Base path | `/api/v1/` |
| Transport | HTTPS in production |
| Format | JSON request and response bodies |
| Versioning | URL path versioning (`v1`) |
| Public API groups | CAPTCHA and public-survey context, questions, location checks, and feedback submission |
| Administrative API groups | Authentication, profile, QR, analytics, responses, and CAPA |

## API design principles

- Use resource/group paths that describe a product capability, for example `/api/v1/public-survey/submit`.
- Keep new APIs versioned and register each method/path in `api/routes.php`.
- Return the common JSON response envelope and an appropriate HTTP status code.
- Authenticate administrator endpoints on the server; do not trust UI state or a browser-supplied facility identifier.
- Require CSRF validation for authenticated state-changing requests.
- Apply role and facility scope before data access, export, or change.
- Do not return passwords, session secrets, raw stack traces, or unnecessary personal data.

## Request conventions

### Headers

| Header | Use |
| --- | --- |
| `Accept: application/json` | Indicates that the client expects JSON. |
| `Content-Type: application/json` | Required for JSON request bodies on POST requests. |
| Session cookie | Sent automatically by the browser for authenticated administrative requests. |
| CSRF token | Required by authenticated write endpoints; obtain token data from `GET /api/v1/auth/csrf`. |

Public survey endpoints do not use an administrator session. Their validation is based on the resolved QR/survey context and configured public-submission controls.

### Query parameters and request bodies

Use query parameters for read filters and identifiers. Use JSON request bodies for create or update actions. Parameters are endpoint-specific; only documented fields should be accepted by a client.

Example public survey request:

```http
POST /api/v1/public-survey/submit
Content-Type: application/json

{
  "facility_nin": "<resolved facility identifier>",
  "department_id": 4,
  "survey_version": "v1.0",
  "answers": [
    {"question_id": "waiting_time", "value": 4}
  ]
}
```

The final validation rules and accepted fields are determined by the active survey package. Clients must resolve the QR context and load the active questions before submission.

## Standard response envelope

All API responses use the following envelope:

```json
{
  "status": "success",
  "message": "Human-readable result message",
  "data": {},
  "errors": null
}
```

Error responses set `status` to `error`, set `data` to `null`, and may include field-level or operational details in `errors`:

```json
{
  "status": "error",
  "message": "Validation failed",
  "data": null,
  "errors": {
    "field_name": "Explanation of the validation issue"
  }
}
```

## HTTP status codes

| Status | Meaning in Abhipraya APIs |
| --- | --- |
| `200 OK` | Request completed successfully. |
| `201 Created` | A new resource was created where the endpoint uses the created response. |
| `400 Bad Request` | Invalid or incomplete request format. |
| `401 Unauthorized` | Administrator session is missing, invalid, or expired. |
| `403 Forbidden` | Authenticated user lacks the required role/scope, or a public validation rule denies the action. |
| `404 Not Found` | Registered resource or QR/survey context was not found. |
| `405 Method Not Allowed` | The URL is registered, but not for the requested HTTP method. |
| `409 Conflict` | Duplicate public feedback submission within the configured window. |
| `422 Unprocessable Content` | Request body or business-rule validation failed. |
| `429 Too Many Requests` | Login protection rate limit or temporary lockout applies. |
| `500 Internal Server Error` | Unexpected server failure; raw implementation details are not returned. |
| `503 Service Unavailable` | A required operational dependency, such as an unapplied secure-profile migration, is unavailable. |

## Authentication and authorisation

Administrator authentication starts with `POST /api/v1/auth/login`. A successful login establishes a protected server-side session. The browser can read the current permitted user/scope with `GET /api/v1/auth/me` and obtain CSRF token data with `GET /api/v1/auth/csrf`.

Every administrative endpoint must enforce scope on the server. For example, a Facility Administrator can access only records for the facility assigned to that session. Changing a facility value in a request does not grant access to another facility.

## Endpoint groups

| Group | Base endpoints | Main purpose |
| --- | --- | --- |
| Authentication | `/auth/captcha`, `/auth/login`, `/auth/logout`, `/auth/me`, `/auth/csrf` | Administrator access, session information, and request protection. |
| Profile | `/auth/profile`, `/auth/change-password` | Read/update permitted profile data and change password. |
| Public survey | `/public-survey/resolve`, `/questions`, `/location`, `/submit` | Resolve QR context, load active survey, validate public submission, and submit feedback. |
| QR | `/qr`, `/qr/generate` | List or generate QR records in permitted scope. |
| Analytics | `/analytics/summary` | Scoped dashboard and analytical summaries. |
| Responses | `/responses`, `/view`, `/export` | List, view, or export permitted anonymous feedback records. |
| CAPA | `/capa/actions` | Read and record corrective and preventive actions. |
| Boundaries | `/boundaries`, `/boundaries/{code}` | Read configured administrative/facility boundary records. |

See the [endpoint inventory](endpoint_inventory.md) for the complete method-by-method list.

## Adding or changing an API

1. Define the endpoint's user need, data contract, authentication requirement, scope rule, validation, and error cases.
2. Add the method/path to the allow-list in `api/routes.php`; do not publish a route by exposing a physical PHP file.
3. Implement the versioned handler under the matching API module.
4. Apply session, CSRF, and role/facility checks before any protected operation.
5. Use the common response envelope, prepared database access, and safe error handling.
6. Add the endpoint to the [endpoint inventory](endpoint_inventory.md), tests, and relevant user/developer documentation.

## Related documentation

- [API developer documentation](README.md)
- [API access control](access_control.md)
- [Encryption and cryptographic protection](encryption.md)
- [Endpoint inventory](endpoint_inventory.md)
- [Service architecture and map](../architecture/service_map.md)
- [Security guide](../security.md)

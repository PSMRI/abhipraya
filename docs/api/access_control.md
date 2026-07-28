# API access control

## Overview

Abhipraya protects APIs with layered access control. Public survey APIs are deliberately limited to the anonymous feedback journey. Administrative APIs require a valid server-side session, appropriate role permission, facility/data scope enforcement, and CSRF protection for state-changing requests.

```text
Incoming API request
        ↓
Registered route and allowed HTTP method
        ↓
Public endpoint or authenticated session check
        ↓
Role permission check
        ↓
Facility/data scope enforcement
        ↓
CSRF validation for authenticated write requests
        ↓
Validated service and repository operation
```

## Access-control layers

| Layer | Applies to | Purpose |
| --- | --- | --- |
| Route allow-list | Every API request | Only paths and HTTP methods registered in `api/routes.php` can run. |
| Authentication | Administrative APIs | Establishes and verifies a protected server-side administrator session. |
| Role authorisation | Protected feature APIs | Confirms that the assigned role is allowed to use a capability such as QR, analytics, responses, or CAPA. |
| Service-location/data scope | Protected data APIs | Limits service locations and records to the scope assigned to the signed-in user. |
| CSRF protection | Authenticated write APIs | Prevents another site from causing a browser with an active session to perform a state-changing request. |
| Input and business validation | Every accepting endpoint | Rejects malformed, unauthorised, or invalid requests before database changes. |

## Public and administrative API separation

| API category | Session required | CSRF required | Permitted purpose |
| --- | --- | --- | --- |
| Public survey | No administrator session | No | Resolve a QR context, load active questions, validate configured location controls, and submit anonymous feedback. |
| Authentication | No before login | Login uses its own request controls | Obtain CAPTCHA, sign in, and establish an administrator session. |
| Administrative read | Yes | No | Read the signed-in user's permitted profile, QR, analytics, response, and CAPA data. |
| Administrative write | Yes | Yes | Update profile/password, generate QR records, and create or update CAPA actions. |

Public survey APIs must not expose administrative reports, user records, or cross-facility data. Administrator APIs must not identify or retaliate against public respondents.

## Session authentication

An administrator signs in through `POST /api/v1/auth/login`. On success, Abhipraya creates a protected server-side session containing the authenticated user identity, role identifier, and permitted facility scope. Clients should use `GET /api/v1/auth/me` to obtain the current permitted user context rather than retaining role/scope values as their own authority source.

When a session is missing, invalid, or expired, protected APIs return `401 Unauthorized`. Signing out through `POST /api/v1/auth/logout` ends the active session.

## Role-based access control

Feature modules enforce allowed role identifiers before operating. For example, QR, analytics, responses, and CAPA APIs check that the current role is authorised for the capability. Roles and their labels are configuration/administration concerns; endpoint code must still make the authorisation decision on the server.

Do not rely on hiding a button, route, or filter in the user interface as access control. A user can modify browser requests. The API must reject unauthorised requests with `403 Forbidden`.

## Service-location and data scope

Service-location scope is a core data-security boundary. In the current healthcare configuration, the technical identifier remains `facilityNIN`:

- A facility-scoped user is bound to the facility assigned at sign-in.
- The API applies that facility to permitted QR, response, analytics, report, and CAPA operations.
- If a facility-scoped request provides a different facility identifier, the server rejects or overrides it according to the endpoint's scope rule; it never treats that client value as authority.
- Higher-scoped roles receive only the facilities and records explicitly allowed by their assigned role/scope.

## CSRF protection for writes

Before an authenticated write request, the client retrieves token information from `GET /api/v1/auth/csrf`. Send the token as the `X-CSRF-TOKEN` header. The server also supports a `csrf_token` value in form or JSON request bodies for compatible clients.

The CSRF token is tied to the active server-side session and is compared using a timing-safe check. Missing or invalid tokens return `403 Forbidden`.

```http
POST /api/v1/capa/actions
Content-Type: application/json
X-CSRF-TOKEN: <token obtained for this session>
```

## Error behaviour

| Situation | Expected HTTP status |
| --- | --- |
| Route is not registered for the method | `405 Method Not Allowed` |
| Protected route has no valid session | `401 Unauthorized` |
| Role does not permit the requested capability | `403 Forbidden` |
| Facility/data is outside the permitted scope | `403 Forbidden` or endpoint-specific validation failure |
| CSRF token is missing or invalid | `403 Forbidden` |
| Request body is invalid | `400 Bad Request` or `422 Unprocessable Content` |

Responses follow the common [API response envelope](api_specifications.md#standard-response-envelope) and must not expose secrets, password values, raw SQL, or stack traces.

## Implementation checklist for a new protected API

1. Register only the intended HTTP method and path in `api/routes.php`.
2. Require the active session before protected business logic runs.
3. Check allowed role(s) for the feature.
4. Derive or validate the permitted facility/data scope server-side.
5. Validate CSRF for authenticated state-changing methods.
6. Validate input, use prepared repositories, and return the standard JSON envelope.
7. Add tests for unauthenticated, forbidden-role, wrong-facility, missing-CSRF, and successful cases.

## Related documentation

- [API specifications](api_specifications.md)
- [Boundary and administrative scope](boundary.md)
- [Endpoint inventory](endpoint_inventory.md)
- [Security guide](../security.md)
- [Service architecture and map](../architecture/service_map.md)

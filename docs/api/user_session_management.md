# User Session Management

## Overview

Abhipraya uses secure, server-side sessions for authenticated administrator access. The browser receives only an opaque session cookie; user identity, role, service-location scope, department scope, CSRF token, and session timing data remain on the server. When enabled in the deployment, sessions are shared through the PHP Redis handler backed by Memurai.

![Abhipraya user session management diagram](/ui/assets/img/docs/abhipraya-user-session-management.svg)

## Session lifecycle

| Stage | Abhipraya behaviour |
| --- | --- |
| Sign in | `POST /api/v1/auth/login` validates CAPTCHA, credentials, account status, and rate-limit controls. |
| Session creation | The server regenerates the session ID, stores the permitted user context, and returns a CSRF token. |
| Browser storage | The browser receives the `ABHIPRAYA_SESSION` cookie. It is `HttpOnly`, `SameSite=Strict`, and `Secure` when HTTPS is used. |
| Protected request | The server loads the session, checks inactivity timeout, client binding, role, scope, and CSRF token for state-changing requests. |
| Renewal | The session ID is regenerated every 10 minutes while the session remains active. |
| End | `POST /api/v1/auth/logout` destroys the session and expires its cookie. Inactivity also ends the session after 30 minutes. |

## Storage configuration

For shared sessions, configure PHP with its Redis session handler and point it to the protected Memurai service. Abhipraya does not hard-code the server address or credentials; it honors PHP's `session.save_path` configuration.

```ini
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?auth=<memurai-password>&database=0"
```

Set `ABHIPRAYA_SESSION_HANDLER=redis` in `.env` if PHP does not already select the Redis handler. The PHP Redis extension must be enabled. For development or single-server use only, set `ABHIPRAYA_SESSION_HANDLER=files`.

## Session security controls

- Session fixation protection through session-ID regeneration at sign in and periodically during use.
- Cookie protections: `HttpOnly`, `SameSite=Strict`, HTTPS-aware `Secure`, and no persistent cookie lifetime.
- 30-minute inactivity timeout.
- Session binding to hashed client IP address and browser user agent; a mismatch ends the session.
- Server-side role and scope checks for every protected capability.
- CSRF validation for authenticated state-changing requests.
- Login CAPTCHA and temporary lockout controls for repeated failed sign-ins.

## API endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/auth/captcha` | Obtain the sign-in CAPTCHA challenge. |
| `POST` | `/api/v1/auth/login` | Establish an administrator session. |
| `GET` | `/api/v1/auth/me` | Read the current permitted session context. |
| `GET` | `/api/v1/auth/csrf` | Obtain a CSRF token for an authenticated write. |
| `POST` | `/api/v1/auth/logout` | End the current session. |

## Related documentation

- [Access control](access_control.md)
- [Encryption and cryptographic protection](encryption.md)
- [Deployment guide](../deployment/deployment_guide.md)

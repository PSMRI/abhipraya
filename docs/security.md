# Security guide

## Implemented controls

- CSRF validation for authenticated profile, password, and QR-generation actions.
- Secure session cookies: HttpOnly, SameSite=Strict, Secure on HTTPS, host-only scope, timeout, and ID regeneration.
- Login CAPTCHA, randomized invalid-login delay, and five-failure / fifteen-minute username-and-IP lockout.
- CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, Referrer Policy, Permissions Policy, HSTS, and technology-header suppression.
- No-store cache headers for API and routed administrator pages.
- Locally hosted Bootstrap Icons for supported administrator pages.

## Operational requirements

Security headers must be validated on the live HTTPS endpoint because IIS, a reverse proxy, CDN, or load balancer can override headers. Keep backups, database access, and server logs restricted to authorised operators.

## Reporting a vulnerability

Do not publish sensitive security details in public issues. Follow the process in [SECURITY.md](../SECURITY.md).

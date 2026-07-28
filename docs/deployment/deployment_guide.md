# Deployment guide

## Runtime verification

Before deploying, confirm that the selected PHP runtime has OpenSSL enabled. Abhipraya uses OpenSSL-backed AES-256-GCM encryption for sensitive administrator-profile fields.

```text
php -m | findstr openssl
```

Expected output is `openssl`. On Linux or macOS, use `php -m | grep -i openssl`. If the extension is missing, enable it in the active PHP configuration and reload the PHP/web-server process before continuing. See [Encryption and cryptographic protection](../api/encryption.md) for the full key-management and verification guidance.

## Supported deployment shape

Abhipraya requires PHP and MySQL/MariaDB behind a compatible web server or reverse proxy. The repository includes IIS reference rules, but Apache, Nginx, or another compatible deployment stack can be used with equivalent routing and HTTPS controls.

```text
HTTPS browser
  → web server or reverse-proxy route rules
  → PHP UI router and versioned API front controller
  → MySQL database
  → protected JSON configuration and storage
```

## Prerequisites

- A compatible web server or reverse proxy with HTTPS and route-rewrite support.
- Supported PHP runtime configured for the selected web-server integration.
- MySQL instance reachable from the application server.
- HTTPS certificate and DNS name.
- Least-privilege database account.
- Scheduled database and file backup destination.

## Deployment procedure

1. Copy the approved release to the IIS application directory.
2. Set `.env` database and environment values outside source control.
3. Confirm `api/masters/` contains the approved facility, department, role, radius, and survey JSON configuration.
4. Apply approved database migrations after taking a backup.
5. Configure an HTTPS binding and redirect HTTP to HTTPS.
6. Confirm IIS identity can read the application and write only to required storage/log directories.
7. Recycle the IIS application pool.
8. Test landing page, documentation, administrator sign-in, Home, QR generation, public survey, Analytics, Reports, CAPA, and sign-out.

## Production hardening

- Do not commit `.env`, database passwords, or private keys.
- Keep PHP display errors off in production.
- Confirm CSP, HSTS, clickjacking, cache-control, and cookie headers on the live HTTPS URL.
- Restrict database network access to application hosts.
- Review logs and failed-login events.
- Keep survey JSON release-controlled and immutable after publishing.

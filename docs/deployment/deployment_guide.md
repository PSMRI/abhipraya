# Deployment guide

## Supported deployment shape

The current application is designed for IIS, PHP, and MySQL.

```text
HTTPS browser
  → IIS site and rewrite rules
  → PHP UI router and versioned API front controller
  → MySQL database
  → protected JSON configuration and storage
```

## Prerequisites

- Windows Server with IIS and URL Rewrite support.
- Supported PHP runtime configured for IIS/FastCGI.
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

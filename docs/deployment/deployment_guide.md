# Deployment guide

## Runtime verification

Before deploying, confirm that the selected PHP runtime has OpenSSL enabled. Abhipraya uses OpenSSL-backed AES-256-GCM encryption for sensitive administrator-profile fields.

```text
php -m | findstr openssl
```

Expected output is `openssl`. On Linux or macOS, use `php -m | grep -i openssl`. If the extension is missing, enable it in the active PHP configuration and reload the PHP/web-server process before continuing. See [Encryption and cryptographic protection](../api/encryption.md) for the full key-management and verification guidance.

## Shared sessions with Memurai

For multiple web/application servers, configure PHP's Redis session handler to connect to Memurai. Abhipraya preserves PHP's `session.save_path`, so the deployer controls the Memurai endpoint, credentials, and TLS settings in `php.ini` or the approved server configuration.

```ini
; php.ini — example only; use protected deployment secrets
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?auth=<memurai-password>&database=0"
```

Set `ABHIPRAYA_SESSION_HANDLER=redis` in `.env` when PHP does not already select the Redis handler. The PHP Redis extension must be enabled. Do not expose Memurai to the public network. Set `ABHIPRAYA_SESSION_HANDLER=files` only for development or single-server deployments.

For step-by-step development, production, verification, high-availability, and rollback guidance, see [Memurai session configuration](memurai_session_configuration.md).

## Optional Kafka event delivery

Kafka is disabled by default and is not required for core feedback collection. When enabled, it requires the `php-rdkafka` extension, protected broker credentials, and a monitored producer topic. Use the [Kafka local and production configuration](../architecture/event_driven_architecture.md#local-development-configuration) guide to configure and verify it safely.

## Supported deployment shape

Abhipraya requires PHP and MySQL/MariaDB behind a compatible web server or reverse proxy. Apache, Nginx, IIS, or another compatible deployment stack can be used with equivalent routing and HTTPS controls.

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

1. Copy the approved release to the application directory.
2. Set `.env` database and environment values outside source control.
3. Confirm `api/masters/` contains the approved facility, department, role, radius, and survey JSON configuration.
4. Apply approved database migrations after taking a backup.
5. Configure an HTTPS binding and redirect HTTP to HTTPS.
6. Confirm the web-server runtime identity can read the application and write only to required storage/log directories.
7. Reload the PHP/web-server process.
8. Test landing page, documentation, administrator sign-in, Home, QR generation, public survey, Analytics, Reports, CAPA, and sign-out.

## Production hardening

- Do not commit `.env`, database passwords, or private keys.
- Keep PHP display errors off in production.
- Confirm CSP, HSTS, clickjacking, cache-control, and cookie headers on the live HTTPS URL.
- Restrict database network access to application hosts.
- Review logs and failed-login events.
- Keep survey JSON release-controlled and immutable after publishing.

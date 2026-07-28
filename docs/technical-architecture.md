# Technical architecture and platform independence

Abhipraya consists of a browser UI, web-server/reverse-proxy routing, PHP UI/API routers, JSON master configuration, MySQL persistence and browser GPS validation.

## Open deployment path

Required runtime: PHP 8.2+, MySQL 8+, HTTPS, a standards-compliant browser, and a web server or reverse proxy that can apply the documented routing rules. The repository includes IIS URL Rewrite reference rules, but a deployment may use Apache or Nginx by translating them. No proprietary runtime is required.

Mandatory external integrations are limited to the configured QR image provider (optional server-side fallback) and SMTP/provider services when enabled. Pin versions and document replacement options.

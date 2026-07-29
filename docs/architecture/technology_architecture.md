# Technology architecture and tools

## Overview

Abhipraya is built with an open, web-based technology stack. It uses PHP for the server application, MySQL or MariaDB for transactional data, browser standards for the user interface, and JSON files for stable product configuration. The application does not require a proprietary application runtime.

```text
Standards-compliant browser
        ↓
HTML, CSS, and JavaScript user interface
        ↓
Web-server or reverse-proxy route rules
        ↓
PHP 8.2+ UI router and versioned REST APIs
        ↓
JSON configuration files and MySQL/MariaDB data storage
```

## Overall technology stack

| Architecture area | Technology or tool | Use in Abhipraya | Licence or availability |
| --- | --- | --- | --- |
| Browser client | HTML5, CSS3, JavaScript | Public QR survey and administrator workspace | Open web standards |
| Accessibility and icons | Bootstrap Icons | Locally bundled interface icons | MIT licensed |
| Web server and routing | IIS, Apache, Nginx, or an equivalent reverse proxy | HTTPS hosting and browser/API route rewriting | Deployment choice; the repository includes IIS reference rules |
| Application runtime | PHP 8.2+ | UI routing, API endpoints, security controls, services, repositories, and tools | PHP License |
| Dependency management | Composer | Installs and manages PHP libraries | MIT licensed |
| QR-code library | `chillerlan/php-qrcode` 6.0 | Server-side QR code generation | MIT or Apache-2.0 licensed |
| Database | MySQL 8+ or MariaDB | Users, responses, QR records, CAPA actions, and audit data | MySQL or MariaDB deployment option |
| Shared session store | Memurai through PHP Redis handler (optional) | Shared authenticated sessions across multiple PHP/web-server instances | Redis-compatible, Windows-native option |
| Event streaming | Apache Kafka through `php-rdkafka` (optional) | Publish selected, non-sensitive domain events to independent consumers | Apache-2.0; optional deployment component |
| Configuration format | JSON | Service locations (currently: facilities), service areas, roles, labels, survey packages, and survey versions | Open standard |
| Data access | PHP MySQLi prepared statements and repository layer | Parameterised transactional data access | PHP extension and application code |
| Security controls | PHP sessions, CSRF tokens, password hashing, CSP, and HTTP headers | Authentication, authorisation, request protection, and browser hardening | PHP and web standards |
| Testing and maintenance tools | PHP CLI and project scripts | Linting and survey publishing | PHP tooling and project code |

## Key tool roles

### PHP and Composer

PHP runs the UI and API application code. Composer manages the declared PHP dependency set in `composer.json`. The current QR dependency requires PHP 8.2 or later.

### MySQL or MariaDB

MySQL stores transactional records such as anonymous responses, authorised users, QR records, CAPA actions, and security/audit events. MariaDB may be used when it is compatible with the selected schema and deployment process.

### Memurai and Redis-compatible sessions

Memurai is an optional, Redis-compatible session-store component for deployments with multiple PHP/web-server instances. It holds authenticated administrator sessions so a request can be served by any approved application node. The browser does not connect to Memurai and does not store role or scope data; it holds only the protected session cookie.

Memurai is a deployment choice, not an Abhipraya source-code dependency and not a replacement for MySQL. The PHP Redis extension is the integration point. A development or single-server deployment can use file sessions instead. Configure it as a protected internal service, with a memory limit, credentials, monitoring, and an appropriate availability design. See [Memurai session configuration](../deployment/memurai_session_configuration.md) for development and production instructions.

### Bootstrap Icons

Bootstrap Icons are bundled under `ui/assets/vendor/bootstrap-icons/`. Bundling icons avoids a required third-party browser request and supports restrictive Content Security Policy settings.

### chillerlan/php-qrcode

The `chillerlan/php-qrcode` package generates QR codes for department survey links. It is managed through Composer and is licensed under MIT or Apache-2.0 terms. Any dependency upgrade must also update the third-party notice and release inventory.

## Development stack tools

The following tools support development, validation, and release work. They are not all part of the deployed application runtime.

| Tool | Development use | Required for every developer? |
| --- | --- | --- |
| Git | Source control, branches, change review, and release history | Yes |
| PHP CLI 8.2+ | Run project scripts and validate PHP syntax with `php -l` | Yes |
| Composer | Install the locked PHP dependency set with `composer install` | Yes when setting up or updating dependencies |
| MySQL or MariaDB client | Create databases, apply migrations, inspect development data, and troubleshoot queries | Required for database setup and administration |
| Web browser developer tools | Inspect UI behaviour, network requests, accessibility, and responsive layouts | Yes for frontend/UI work |
| Web-server configuration tools | Configure development routing, PHP integration, HTTPS, and process reloads | Needed for the selected development web-server environment |
| Memurai and PHP Redis extension | Run and test shared administrator sessions | Needed only for shared-session or multi-node development |
| Text editor or IDE | Edit PHP, JavaScript, CSS, JSON, SQL, Markdown, and configuration files | Yes; the project does not require a specific editor |

### Development workflow

```text
Clone with Git
        ↓
Install PHP dependencies with Composer
        ↓
Create MySQL/MariaDB database and apply migrations
        ↓
Configure .env values locally (never commit them)
        ↓
Serve with a selected development web-server or reverse-proxy setup
        ↓
Use browser developer tools and PHP CLI checks while developing
        ↓
Run project checks, review changes, and document new dependencies
```

### Optional tooling policy

Abhipraya does not mandate a particular IDE, database GUI, API client, or operating system. Teams may select tools that meet their security and workflow needs. Any new runtime library, bundled frontend asset, or deployment dependency must be declared, version-pinned where practical, licence-reviewed, and recorded in the third-party notice before release.

## Deployment portability

The repository includes an IIS and URL Rewrite reference configuration, but the application logic is not tied to IIS or Windows Server. A deployment may use Apache, Nginx, or another compatible reverse proxy when the documented public, administrator, and API route rules are translated and HTTPS/security headers are applied equivalently.

The deployment environment must provide:

- PHP 8.2+ with the required extensions, including MySQLi and mbstring.
- MySQL 8+ or a compatible MariaDB installation.
- Optional Memurai service and PHP Redis extension for shared sessions across application nodes.
- HTTPS termination and equivalent rewrite/security-header rules.
- Restricted database credentials, backups, monitoring, and log retention.

## Dependency and licence management

All added libraries must be recorded in [Third-party notices](../../THIRD_PARTY_NOTICES.md) and must be compatible with Abhipraya's GPL-3.0-or-later licence. Before a public release, review `composer.json`, bundled frontend assets, and the dependency inventory to confirm versions, licences, attribution, and any required notice text.

## Related documentation

- [Technical architecture overview](technical_architecture.md)
- [Service architecture and map](service_map.md)
- [Memurai session configuration](../deployment/memurai_session_configuration.md)
- [Deployment guide](../deployment/deployment_guide.md)
- [Open standards mapping](../compliance/open_standards_mapping.md)
- [Licence consistency and attribution](../compliance/license_consistency.md)

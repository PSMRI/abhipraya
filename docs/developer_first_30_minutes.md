# First 30 minutes for a new developer

## 0–5 minutes: orient yourself

1. Read [Why Abhipraya](architecture/why_abhipraya.md), [Technical architecture](architecture/technical_architecture.md), and the [Developer guide](developer-guide.md).
2. Clone the source repository: [PSMRI/abhipraya](https://github.com/PSMRI/abhipraya).
3. Confirm PHP 8.2+, Composer, MySQL 8+/compatible MariaDB, and a supported web-server/PHP environment are available.

## 5–15 minutes: configure locally

1. Create an uncommitted `.env` with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
2. Run `composer install` from the repository root.
3. Create an empty development database and run the [core schema](../api/database/schema/abhipraya_core_schema.sql).
4. Review `api/masters/`; it supplies service locations, service areas, roles, and survey packages.
5. Set `ABHIPRAYA_SESSION_HANDLER=files` for a simple single-node setup, or follow the [Memurai guide](deployment/memurai_session_configuration.md) for shared sessions.

## 15–25 minutes: run and verify

1. Configure the selected web server/reverse proxy to route the UI and `/api/v1/` endpoints to the project.
2. Run `php -l api/index.php` and `php -l ui/docs.php`.
3. Open the landing page and documentation; confirm the sidebar works.
4. Test administrator sign-in with an approved development account, then sign out.
5. Open a configured QR survey and submit a non-production test response.

## 25–30 minutes: learn the change flow

1. Read [Coding standards](architecture/coding_standards.md) before editing code.
2. For surveys, use [Survey version publishing](survey-version-publishing.md); do not edit published versions in place.
3. For database changes, create a reviewed migration and follow the [indexing guide](database_indexing.md) when changing indexes.
4. Follow [administrator and service-location user provisioning](user_provisioning.md) when an approved development account is needed.
5. Run the relevant lint/feature checks, update documentation, and add new pages to `docs/SUMMARY.md`.

## Next steps

- [API specifications](api/api_specifications.md)
- [Data dictionary and ER overview](database/data_dictionary_erd.md)
- [Backup and restore](deployment/backup_restore_guide.md)

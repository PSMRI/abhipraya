# Database setup and migration

## Before deployment

1. Create or obtain the approved MySQL database.
2. Create a database account with only the privileges required by the application.
3. Store connection values in the server-side `.env` file, outside source control.
4. Back up the database before applying any migration.

## New database bootstrap

For a new, empty database, run [`abhipraya_core_schema.sql`](../../api/database/schema/abhipraya_core_schema.sql) once. It creates the current active application tables in dependency order:

1. `user_master`
2. `user_profile_secure`
3. `auth_login_attempts`
4. `srvy_responses`
5. `capa_actions`

Example command (run from the repository root with protected credentials):

```text
mysql --default-character-set=utf8mb4 -u <database-user> -p <database-name> < api/database/schema/abhipraya_core_schema.sql
```

The schema script uses `CREATE TABLE IF NOT EXISTS`; it does not alter existing table structures or migrate data. For an existing deployment, use the controlled migration order below instead.

## Migration order

Review and apply the migrations in `api/database/migrations/` in a controlled environment before production. Important current migrations include:

| Migration | Purpose |
| --- | --- |
| `20260720_secure_user_profile.sql` | Expands password-hash storage and adds protected profile storage. |
| `20260720_encrypt_legacy_user_pii.php` | Moves legacy administrator profile information to protected storage. |
| `20260721_login_rate_limit.sql` | Adds login attempt and lockout records. |
| `20260725_survey_versioning.sql` | Adds survey code, version, schema-hash, and related response support. |
| `20260725_assign_legacy_department_4.php` | Marks identified legacy rows with department 4 where approved. |
| `20260725_capa_survey_versioning.sql` | Associates CAPA records with survey version information. |
| `20260725_capa_scope_index.sql` | Adds CAPA lookup/index support for scoped operational use. |
| `20260728_core_performance_indexes.php` | Adds missing scoped user, response/survey, and CAPA performance indexes safely. |

## Safe migration process

1. Take a tested backup.
2. Run the migration in development or UAT.
3. Check row counts and sample records before and after.
4. Confirm the application can load Home, Analytics, Reports, QR Center, and CAPA.
5. Apply in production during an approved change window.
6. Reload the selected PHP/web-server process and test critical journeys.


## Rollback principle

Do not delete response data as a rollback method. Use a pre-migration backup, an approved corrective migration, or a documented restoration procedure. Survey JSON changes must be versioned rather than overwritten.

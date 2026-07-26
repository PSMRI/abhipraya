# Database setup and migration

## Before deployment

1. Create or obtain the approved MySQL database.
2. Create a database account with only the privileges required by the application.
3. Store connection values in the server-side `.env` file, outside source control.
4. Back up the database before applying any migration.

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

## Safe migration process

1. Take a tested backup.
2. Run the migration in development or UAT.
3. Check row counts and sample records before and after.
4. Confirm the application can load Home, Analytics, Reports, QR Center, and CAPA.
5. Apply in production during an approved change window.
6. Recycle the IIS application pool and test critical journeys.

## Rollback principle

Do not delete response data as a rollback method. Use a pre-migration backup, an approved corrective migration, or a documented restoration procedure. Survey JSON changes must be versioned rather than overwritten.

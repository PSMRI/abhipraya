# Troubleshooting and FAQ

## Deployment and runtime

| Symptom | Checks and resolution |
| --- | --- |
| Bootstrap script stops with an administrator/root error | Run the Windows script from elevated PowerShell or the Linux script with `sudo`. Do not bypass this requirement. |
| `php`, `mysql.exe`, or `memurai-cli.exe` is not found on Windows | Open a new PowerShell session after the bootstrap script updates the machine `PATH`. Confirm the supplied PHP/MySQL/Memurai bin paths exist. |
| PHP page downloads instead of running | Confirm the web server PHP FastCGI/PHP-FPM handler is configured, the PHP CGI/FPM executable exists, and the web-server process was reloaded. |
| `Database configuration is missing` | Create the protected `.env` with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`; then reload PHP/web server. Never add those values to Git. |
| Database connection fails | Confirm MySQL/MariaDB is running, the database/account exists, firewall access is restricted but permitted from the application host, and the `.env` values match. Check the PHP/server error log without sharing credentials. |
| Table does not exist | For a new empty database, apply `api/database/schema/abhipraya_core_schema.sql`. For an existing installation, apply the approved migrations instead of replacing the schema. |
| Documentation page shows old content | Reload the PHP/web-server process and hard-refresh the browser. Clear only approved server/browser caches; do not edit generated production files manually. |
| Port 80/443 is already in use | Identify the existing web-server/site binding and choose an unused test port first. Add the production HTTPS binding only after certificate approval. |

## Sessions, login, and access

| Symptom | Checks and resolution |
| --- | --- |
| Login succeeds but the next request is unauthorised | Confirm HTTPS/cookie settings, the PHP session directory or Memurai endpoint, and that all application nodes use the same session configuration. Sign out, clear the site cookie, and sign in again. |
| Memurai/Redis sessions do not work | Confirm the PHP Redis extension is enabled, `session.save_handler = redis`, the protected `session.save_path` is correct, and the Memurai service is reachable only from approved application hosts. Review [Memurai session configuration](memurai_session_configuration.md). |
| A user receives `401 Unauthorized` after inactivity | This is expected after the 30-minute session timeout. Sign in again. |
| A user receives `403 Forbidden` | Confirm the account role, service-location scope, and CSRF token for state-changing requests. Do not attempt to bypass the API by editing browser requests. |
| A facility user sees another facility in a filter | Sign out and sign in again, then refresh. The API must enforce the assigned `facilityNIN`; if cross-location data is returned, stop using the account and report it to the system administrator. |
| New administrator cannot sign in | Confirm `active = 1`, the role/scope values, the username, and that `u_pass` contains a PHP `password_hash()` value—not plaintext. See [user provisioning](../user_provisioning.md). |
| Account is temporarily locked | Wait for the configured lockout period or follow the approved administrator unlock process. Do not delete login-attempt records as a routine workaround. |

## Survey, analytics, and CAPA

| Symptom | Checks and resolution |
| --- | --- |
| Home page shows no data | Check facility/service location, service area, survey version, and date range. Confirm a public response exists for the selected scope. |
| Report or score looks unexpected | Check `report_type` and survey version. Only rating questions produce average stars; binary, category, severity, numeric, duration, and text types have different outputs. |
| QR poster does not generate | Confirm a permitted service location and service area are selected and that an active survey package exists. Check browser network errors and server logs. |
| Public survey rejects location | Confirm device location permission, configured coordinates/radius, and the device position. Do not weaken location controls without programme approval. |
| CAPA action does not save | Confirm the user role/scope, CSRF token, selected month, survey version, and question key. Review server logs for the request ID. |

## Backup, restore, and events

| Symptom | Checks and resolution |
| --- | --- |
| Backup cannot be restored | Restore to an isolated verification database first. Confirm the backup checksum, MySQL version compatibility, and matching survey JSON configuration before touching production. |
| Restored analytics do not match | Check that the database backup and `api/masters/` survey package version/hashes were restored together. |
| Kafka events are not visible | Kafka is optional. Confirm `ABHIPRAYA_EVENT_DRIVER=kafka`, `php-rdkafka`, private broker connectivity, producer topic permission, and application error logs. Core survey submission continues using local event logging if Kafka is unavailable. |
| Event payload contains unexpected data | Stop the consumer/integration, review the event contract and data-protection approval, then remove unapproved fields before re-enabling delivery. |

## When to escalate

Escalate immediately for suspected unauthorised access, cross-service-location data exposure, credential/key disclosure, unexpected production data loss, failed restore, or a public survey outage. Preserve logs and evidence, but do not copy passwords, session cookies, encryption keys, or sensitive raw records into tickets.

## Related documentation

- [Deployment bootstrap scripts](deployment_bootstrap_scripts.md)
- [Backup and restore](backup_restore_guide.md)
- [Memurai session configuration](memurai_session_configuration.md)
- [User session management](../api/user_session_management.md)

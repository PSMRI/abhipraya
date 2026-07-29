# Deployment bootstrap scripts

Abhipraya provides starter automation for two deployment families. They configure the runtime baseline but do not replace security review, certificate management, secret management, database backup, or production acceptance testing.

| Platform | Script | What it does |
| --- | --- | --- |
| Windows Server | `deploy/windows/bootstrap-windows-iis.ps1` | Optionally installs IIS features, extracts a supplied PHP ZIP, optionally runs supplied MySQL/Memurai MSI installers, configures IIS PHP FastCGI, and grants required storage access. |
| Debian/Ubuntu Linux | `deploy/linux/bootstrap-linux-nginx.sh` | Installs Nginx, PHP-FPM/PHP extensions, MariaDB, Redis, creates storage directories, and writes an Nginx site template. |

## Windows Server / IIS

### Prepare files

1. Copy the approved repository release to `C:\apps\abhipraya` (or another protected application directory).
2. Place approved installation packages on the server, for example:

```text
C:\installers\php.zip
C:\installers\mysql.msi
C:\installers\memurai.msi
```

3. Open **PowerShell as Administrator**, then enter the application directory:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
cd C:\apps\abhipraya
```

### Dry run

Run with `-WhatIf` first. Use port `8080` for the initial verification, then configure the approved HTTPS binding separately.

```powershell
.\deploy\windows\bootstrap-windows-iis.ps1 -AppPath 'C:\apps\abhipraya' -PhpZipPath 'C:\installers\php.zip' -PhpInstallPath 'C:\PHP' -MySqlInstallerPath 'C:\installers\mysql.msi' -MemuraiInstallerPath 'C:\installers\memurai.msi' -MySqlBinPath 'C:\Program Files\MySQL\MySQL Server 8.0\bin' -MemuraiBinPath 'C:\Program Files\Memurai' -InstallIis -WhatIf
```

### Run installation

After reviewing all paths, remove `-WhatIf` and add the initial test port:

```powershell
.\deploy\windows\bootstrap-windows-iis.ps1 -AppPath 'C:\apps\abhipraya' -PhpZipPath 'C:\installers\php.zip' -PhpInstallPath 'C:\PHP' -MySqlInstallerPath 'C:\installers\mysql.msi' -MemuraiInstallerPath 'C:\installers\memurai.msi' -MySqlBinPath 'C:\Program Files\MySQL\MySQL Server 8.0\bin' -MemuraiBinPath 'C:\Program Files\Memurai' -InstallIis -Port 8080
```

The script accepts approved PHP ZIP, MySQL MSI, and Memurai MSI packages; it does not download installers or embed credentials.

When `-MySqlBinPath` and `-MemuraiBinPath` are supplied, the script adds PHP, MySQL, and Memurai executable folders to the machine `PATH`. Open a new PowerShell session after the script completes before running `php`, `mysql.exe`, or `memurai-cli.exe`.

### Configure after installation

1. Enable `mysqli`, `openssl`, and `redis` in `C:\PHP\php.ini`.
2. Configure MySQL administrator/database credentials and create the approved Abhipraya database.
3. Configure Memurai with private binding, authentication, memory limit, and TLS where required. Configure PHP `session.save_handler = redis` and the protected `session.save_path`.
4. Create the protected `C:\apps\abhipraya\.env` with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `ABHIPRAYA_SESSION_HANDLER=redis`.
5. Apply the new-database schema only to an empty database:

```powershell
mysql -u <database-user> -p <database-name> < api\database\schema\abhipraya_core_schema.sql
```

6. Open `http://<server>:8080`, verify documentation, sign-in, sign-out, and a non-production survey response. Then configure the approved HTTPS certificate and port 443.

## Linux / Nginx

### Prepare and install

Copy the approved release to the target host, then run from the repository root. This script supports Debian/Ubuntu systems with `apt`.

```bash
sudo APP_PATH=/var/www/abhipraya DOMAIN_NAME=feedback.example.org bash deploy/linux/bootstrap-linux-nginx.sh
```

The Linux script installs Nginx, PHP-FPM/PHP extensions, MariaDB, and Redis. Use an approved equivalent automation method for other distributions.

### Configure after installation

1. Create a protected `/var/www/abhipraya/.env` with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
2. Secure MariaDB, create the approved database/account, and apply the schema only to an empty database:

```bash
mysql --default-character-set=utf8mb4 -u <database-user> -p <database-name> < api/database/schema/abhipraya_core_schema.sql
```

3. Configure Redis or another approved Redis-compatible service with private binding, credentials, and TLS where required. Set `ABHIPRAYA_SESSION_HANDLER=redis` after PHP session configuration is verified.
4. Configure the organisation-approved HTTPS certificate and redirect HTTP to HTTPS.
5. Run `nginx -t`, reload Nginx/PHP-FPM, and verify the landing page, documentation, administrator sign-in/sign-out, and a non-production survey response.

## Required manual steps

1. Create the protected `.env` with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
2. Configure Memurai/Redis only on private application networks; set `ABHIPRAYA_SESSION_HANDLER=redis` after PHP Redis session configuration is verified.
3. Apply the [new database schema](../../api/database/schema/abhipraya_core_schema.sql) to an empty database, or use controlled migrations for an existing deployment.
4. Configure HTTPS certificates and HTTP-to-HTTPS redirection.
5. Run the [backup/restore](backup_restore_guide.md), [Memurai](memurai_session_configuration.md), and production validation procedures.

## Safety rules

- Do not run either script against a production host without change approval and a verified backup.
- Do not put database, Memurai, Kafka, or certificate secrets in script parameters, source control, or logs.
- Review generated Nginx/IIS settings against the organisation's network, TLS, monitoring, and least-privilege standards.

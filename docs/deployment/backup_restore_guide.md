# Backup and restore guide

## Backup scope

Back up the MySQL database; approved `api/masters/` survey/configuration packages; protected environment configuration; required QR/export storage; and web-server/TLS configuration records. Memurai holds short-lived sessions and is not a system of record; do not rely on it as a substitute for database backup.

## MySQL backup

Run backups from a protected administration host. Store the password through the approved secret mechanism rather than placing it in shell history or source control.

```text
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 --hex-blob -u <database-user> -p <database-name> > <backup-file>.sql
```

`--single-transaction` supports a consistent backup for InnoDB tables without a long write lock. For a small maintenance window or non-InnoDB tables, obtain database-administrator guidance before using a different method.

After creating the backup:

1. Record the timestamp, database version, application release, and configuration/survey package version.
2. Calculate and retain a checksum.
3. Encrypt the archive and copy it to an access-controlled backup destination.
4. Test restoration regularly; a backup is not verified until it restores successfully.

## Configuration backup

Keep an approved, version-controlled copy of `api/masters/`. Before a production change, record the release tag and published survey manifest hashes. Protect `.env`, encryption keys, TLS private keys, and Kafka/Memurai credentials separately; they must not be committed into Git or stored alongside broadly accessible database backups.

## Restore procedure

1. Declare the incident and stop writes if required.
2. Select the approved recovery point and matching configuration release.
3. Restore to an isolated verification database first:

```text
mysql --default-character-set=utf8mb4 -u <database-user> -p <verification-database> < <backup-file>.sql
```

4. Verify table counts, the latest survey versions, administrator sign-in, QR resolution, response/analytics access, and CAPA records.
5. Obtain operational approval before switching production traffic.
6. Restore the matching JSON configuration package and reload the PHP/web-server process.
7. Record the recovery point, people involved, validation evidence, and any data gap.

Never restore a newer database with older survey JSON without checking survey version and schema-hash compatibility. Do not delete response data as a rollback shortcut.

## Suggested schedule and retention

| Activity | Minimum practice |
| --- | --- |
| Automated database backup | Daily or according to the programme recovery objective. |
| Pre-change backup | Before migrations, configuration publishing, or production releases. |
| Restore drill | At least quarterly and after material recovery-process changes. |
| Retention | Follow approved legal, privacy, and operational retention rules. |
| Access | Limit backup creation, download, restore, and key access to authorised operators. |

## Related documentation

- [Database setup and migration](../database/database_setup_and_migration.md)
- [Database indexing guide](../database_indexing.md)
- [Data dictionary and ER overview](../database/data_dictionary_erd.md)

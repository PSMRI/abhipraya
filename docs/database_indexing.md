# Database indexing guide

## Purpose

Indexes support the active Abhipraya lookup patterns without changing application behaviour. Add an index only after measuring a slow query or reviewing its execution plan; every additional index increases write and storage cost.

## Current indexes

| Table | Index | Supports |
| --- | --- | --- |
| `user_master` | `PRIMARY (u_id)`, `uq_user_master_username (u_name)` | Administrator lookup and sign-in. |
| `user_master` | `idx_user_master_scope (NIN_fk, u_role, active)` | Scoped administrator lookup. |
| `user_profile_secure` | `PRIMARY (user_id)` | One secure profile per administrator. |
| `auth_login_attempts` | `PRIMARY (username_hash, ip_hash)` | Failed-login lookup per user/IP pair. |
| `auth_login_attempts` | `idx_auth_login_attempts_locked_until (locked_until)` | Lockout maintenance and inspection. |
| `srvy_responses` | `PRIMARY (id)`, `uq_srvy_responses_submission_id (submission_id)` | Response detail and idempotent submission lookup. |
| `srvy_responses` | `idx_srvy_responses_scope_time (hospital_nin, department_id, srvy_rpl_dt)` | Scoped response lists, duplicate checks, and time-based analytics. |
| `srvy_responses` | `idx_srvy_responses_survey (survey_code, survey_version, survey_schema_hash)` | Survey-version filtering and historical analytics. |
| `capa_actions` | `PRIMARY (id)` | CAPA action identity. |
| `capa_actions` | `idx_capa_scope (hospital_nin, month, question_key)` | Scoped CAPA lookup by respondent scope, period, and indicator. |

## Inspect and measure

```sql
SHOW INDEX FROM srvy_responses;
SHOW INDEX FROM capa_actions;
ANALYZE TABLE srvy_responses, capa_actions;
```

Use `EXPLAIN ANALYZE` on a representative, parameterised query in a safe environment before proposing a new index. Do not run expensive exploratory queries against production during peak traffic.

## Adding or changing an index

1. Capture the slow query, its frequency, current `EXPLAIN ANALYZE`, and expected improvement.
2. Confirm the proposed index matches the query's leading equality filters, then its range/sort columns.
3. Add the change as a dated migration in `api/database/migrations/`.
4. Test migration duration, write impact, and query plan in development/UAT with representative data.
5. Take a verified backup, apply during an approved window, and monitor query latency and database load.
6. Document the index in this guide and the data dictionary.

Avoid indexing every answer column (`srvy_Q0`–`srvy_Q30`) by default. Add a targeted index only for a measured report/query that benefits from it.

## Related documentation

- [New database schema](../api/database/schema/abhipraya_core_schema.sql)
- [Database setup and migration](database/database_setup_and_migration.md)
- [Backup and restore](deployment/backup_restore_guide.md)

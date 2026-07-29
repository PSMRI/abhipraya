# Data dictionary and ER overview

## Data boundary

The active versioned API uses five application tables. JSON files remain the source of truth for service locations (currently healthcare facilities), service areas (currently departments), roles, labels, and survey definitions. MySQL holds user, security, response, and operational records.

![Abhipraya active database ER overview](/ui/assets/img/docs/abhipraya-er-overview.svg)

## Active application tables

| Table | Primary key | Purpose | Important fields |
| --- | --- | --- | --- |
| `user_master` | `u_id` | Administrator account, password hash, and assigned role/scope. | `u_name`, `u_pass`, `active`, `u_role`, `NIN_fk`, `u_identity` |
| `user_profile_secure` | `user_id` | Encrypted administrator profile values. | encrypted name, email, mobile, job title, `updated_at` |
| `auth_login_attempts` | username/IP hash pair | Failed-login rate-limit and temporary lockout state. | `username_hash`, `ip_hash`, `failed_attempts`, `locked_until`, `last_attempt_at` |
| `srvy_responses` | `id` | Anonymous public survey responses. | `submission_id`, `hospital_nin`, `department_id`, `srvy_Q0`–`srvy_Q30`, version/hash fields, validation metadata |
| `capa_actions` | `id` | Corrective and preventive actions connected to survey indicators. | `hospital_nin`, `dept_id`, `month`, `question_key`, survey fields, root cause, action plan, responsible person, timeline |

The complete new-database DDL is maintained in [`api/database/schema/abhipraya_core_schema.sql`](../../api/database/schema/abhipraya_core_schema.sql). Existing deployments must use the [database migration guide](database_setup_and_migration.md) rather than replacing their schema.

## Relationships and reference rules

| From | To | Relationship | Enforcement |
| --- | --- | --- | --- |
| `user_profile_secure.user_id` | `user_master.u_id` | One administrator account can have zero or one protected profile. | Database foreign key with cascade delete. |
| `user_master.NIN_fk` | Service-location configuration | Administrator assigned scope. | Logical application/configuration reference. |
| `srvy_responses.hospital_nin`, `department_id` | Versioned service-location/service-area and survey JSON | Response context. | Logical application/configuration reference. |
| `capa_actions.hospital_nin`, `dept_id`, survey fields | Versioned service-location/service-area and survey JSON | Operational action context. | Logical application/configuration reference. |
| `auth_login_attempts` | None | Security-control record, deliberately independent of user rows. | No foreign key; supports unknown-user login attempts. |

## Response and survey-version fields

`srvy_responses` retains legacy answer fields `srvy_Q0` through `srvy_Q30`; configured survey question `qn: 1` maps to `srvy_Q0`. The response also records `survey_code`, `survey_version`, and `survey_schema_hash`. Analytics must use this version context, rather than assuming a column number has the same meaning across published survey versions.

## Non-database storage

| Storage | Location / technology | Purpose |
| --- | --- | --- |
| Master data and survey packages | `api/masters/` JSON files | Authoritative configuration, labels, service locations, service areas, roles, and published surveys. |
| Administrator sessions | Memurai through PHP Redis handler, or local files for a single-node deployment | Short-lived authenticated session state; not a MySQL table. |
| Event records | `api/storage/events/` JSON-line logs; optional Kafka publisher | Operational event logging and optional downstream integration. |

## Data minimisation

Public feedback must not collect direct identity. Location/device metadata supports configured validation and duplicate control only. Restrict administrator profile access, responses, CAPA records, exports, session storage, and event payloads according to role and service-location scope.

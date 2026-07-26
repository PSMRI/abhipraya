# Data dictionary and ER overview

## Data boundary

Abhipraya uses JSON for master configuration and MySQL for transactional, security, and operational records. The database is not the authoritative source for survey question wording, departments, or roles.

## Core tables

| Table | Purpose | Important fields |
| --- | --- | --- |
| `fac_master` | Facility records used for NIN-based facility resolution. | `id`, `NIN_no`, facility name, location and radius fields. |
| `user_master` | Administrator credentials and assigned role/scope. | `u_id`, username, password hash, `NIN_fk`, role and identity fields. |
| `user_profile_secure` | Protected administrator profile data. | `user_id`, encrypted profile values and update metadata. |
| `auth_login_attempts` | Login-rate-limit and temporary lockout state. | hashed username/IP, failure count, lock time. |
| `srvy_responses` | Anonymous feedback submissions. | response ID, facility NIN, department, answer columns, location/device metadata, submission time, survey version/hash. |
| `capa_actions` | Corrective and preventive action records. | facility, department, indicator, survey version, action status, owner, dates, evidence. |

## Relationships

```text
fac_master (facility NIN)
       ├── user_master (facility-scoped administrator assignment)
       ├── srvy_responses (anonymous feedback)
       └── capa_actions (improvement actions)

user_master
       └── user_profile_secure

JSON survey package
       └── srvy_responses (survey_code, survey_version, schema_hash)
       └── capa_actions (survey version and indicator reference)
```

## Response storage and legacy columns

The current response table uses legacy `srvy_Q0` through `srvy_Q30` fields. The survey `qn` value maps a configured question to the appropriate response field. For example, `qn: 1` maps to `srvy_Q0`.

New analytics must resolve the answer using the response's survey version, department, and question metadata. A column number by itself does not describe the meaning of a historical answer.

## Survey-version fields

Survey-version migrations add or populate:

- `survey_code`
- `survey_version`
- `survey_schema_hash`

These fields preserve the package that was active at submission time. They are essential when a later version adds, removes, reorders, or changes questions.

## Data minimisation

Public feedback should not collect direct personal identity. Location and device metadata are used only for configured validation and duplicate-control purposes. Access to administrator profile data and operational exports must be restricted.

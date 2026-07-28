# Master data management

## Purpose

Master data defines the stable reference information that Abhipraya uses to operate surveys, control access, validate public submissions, and present consistent reports. Transactional feedback, sessions, CAPA actions, and audit events are not master data; they remain in the database.

The current implementation uses controlled JSON files under `api/masters/`. It is a managed, file-based master-data model. Changes are made through the repository/change-control process, not through a public browser administration screen.

## Terminology and reuse beyond healthcare

Abhipraya is designed as a configurable public-feedback platform. Healthcare facilities are the current implementation context, but the same model can be used for other public-service locations or operating units, such as schools, service centres, local offices, transport points, or programme sites.

In current source code and master files, `facilityNIN` is the stable identifier for the configured service location. It must remain unchanged for compatibility with existing QR references, feedback records, scope checks, and reports. In new general documentation, use **service location (currently: facility)** or **operating unit** where that improves reuse clarity; use `facilityNIN` only when referring to the current technical field or API/data contract.

```text
Approved master-data change
        ↓
Edit controlled JSON source or prepare a new survey version
        ↓
Validate structure, identifiers, languages, and compatibility
        ↓
Peer review and release approval
        ↓
Deploy controlled configuration
        ↓
Application loads the approved master data
        ↓
Audit operational effect and retain release history
```

## Master-data domains

| Domain | Source | Used by | Change rule |
| --- | --- | --- | --- |
| Service locations (currently: facilities) | `facilityCodes.json` | QR resolution, scope, reports, location checks, boundaries | Preserve the stable `facilityNIN`; validate administrative/location fields. |
| Service areas (currently: departments) | `departmet.json` | QR context, survey selection, analytics, labels | Preserve the stable department ID; provide supported-language labels. |
| Roles | `rolecode.json` | Session interpretation and permitted feature scope | Review role impact with security owners before release. |
| Location controls | `radius.json` | Configured public-survey location validation | Change only with privacy/operational approval; keep values proportionate. |
| Interface labels | `buttons.json` | Public survey controls | Preserve the button identifier and add every supported language. |
| Submission messages | `thankyou_messages.json` | Public feedback completion message | Preserve language codes and review content for clarity/accessibility. |
| Survey packages | `surveys/department_<id>/` | Question rendering, validation, analytics, reporting | Published versions are immutable; publish a new version for a material change. |
| Legacy department survey files | `dept_id_<id>.json` | Existing configured survey compatibility | Maintain only through the documented survey-version process. |

## Source of truth and identifiers

Each domain has a designated source of truth. Do not create a second, manually maintained database master table for service locations, service areas, roles, or survey definitions unless a formally approved migration establishes ownership, synchronisation, audit history, and rollback.

Stable identifiers must not be repurposed:

- `facilityNIN` identifies the current configured service location (a healthcare facility in the current implementation) across QR context, records, and scope.
- `departmentId` identifies a department.
- Role identifiers determine server-side feature permissions.
- Survey `question_id` identifies a versioned question instance.
- `indicator_key` remains stable only while the analytical meaning is comparable.
- Option values remain stable; display order or translated labels must not redefine their meaning.

## Roles and responsibilities

| Role | Responsibility |
| --- | --- |
| Master-data owner | Approves business meaning, service-location/service-area/role changes, and effective date. |
| Survey manager | Prepares survey-package changes, translations, report types, and version publication. |
| Security/operations reviewer | Reviews role, scope, location, privacy, and deployment impact. |
| Developer/release manager | Validates structure, performs controlled deployment, and retains release evidence. |
| Service-location/programme representative | Confirms operational accuracy of location, service-area, and service information. |

One person may fill more than one role in a small deployment, but approval and review evidence should remain clear.

## Change lifecycle

### 1. Request and classify

Classify the requested change as routine reference data, access/scope data, location/privacy data, or survey/analytics data. Higher-risk changes require appropriate business and security approval.

### 2. Prepare a controlled change

Edit a working copy and validate JSON syntax, required fields, stable identifiers, language coverage, and cross-file references. Never alter a published survey file in place.

### 3. Review impact

Check the effect on QR links, public survey experience, service-location scope, analytics comparability, reports, privacy, and downstream integrations. A `facilityNIN`, department ID, or active survey version change can affect operating records and must be reviewed carefully.

### 4. Validate and publish

For surveys, use the documented [survey version publishing](../survey-version-publishing.md) dry run before publishing. The publisher generates a schema hash, preserves the previous version, and records the new active version.

### 5. Deploy and verify

Deploy through the approved release process. Verify that the application loads the intended service-location/service-area context, labels, location settings, survey version, and authorised scope.

### 6. Retain history and rollback plan

Retain the reviewed source change and release/version record. For surveys, roll forward with a corrected new version rather than overwriting a published version. For other masters, restore a previously approved configuration only after assessing data and operational impact.

## Data-quality checks

- Each service location has a unique, valid `facilityNIN` and supported language entries.
- Service-location state, district, block, address, coordinates, and radius settings are reviewed for completeness and accuracy.
- Each department has a unique stable ID and supported-language labels.
- Role codes and role descriptions match the server-side permission model.
- JSON files parse successfully and contain only documented fields/values.
- Survey translations, option values, report types, and indicator keys pass publication validation.
- Production releases contain no test service locations, placeholder location data, secrets, or personal data.

## Future administration interface

A browser-based master-data administration interface is not currently part of the documented implementation. If introduced, it must use restricted administrator APIs with role permission, CSRF protection, audit logging, validation, approval workflow where appropriate, and an export/rollback mechanism. It must not allow public users or service-location-scoped users to modify unrestricted programme-level master data.

## Related documentation

- [Configuration JSON formats](configuration_formats.md)
- [Survey version publishing](../survey-version-publishing.md)
- [Boundary and administrative scope](../api/boundary.md)
- [API access control](../api/access_control.md)
- [Data dictionary and ER overview](../database/data_dictionary_erd.md)

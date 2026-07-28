# Boundary and administrative scope

## Current implementation

Abhipraya currently uses a **facility-centred boundary model**. Each configured facility has a stable `facilityNIN` and includes administrative-location attributes in `api/masters/facilityCodes.json`:

| Attribute | Current purpose |
| --- | --- |
| `facilityNIN` | Stable facility identifier used by QR context, feedback records, and server-side scope checks. |
| `facilityName` and `facilityAddress` | Human-readable facility identification in interfaces and reports. |
| `stateCode` | Administrative state reference stored with the facility. |
| `districtCode` | Administrative district reference stored with the facility where configured. |
| `blockCode` | Administrative block reference stored with the facility where configured. |
| `facilityLat`, `facilityLong`, and `geo_radius` | Configured location-validation reference for public feedback where enabled. |

The user session also retains an assigned facility and can retain a block identifier. The primary active security boundary is the facility assignment: a facility-scoped administrator cannot use a client-supplied facility identifier to retrieve another facility's data.

```text
State code
    ↓
District code
    ↓
Block code
    ↓
Facility NIN  ← current operational and access-control boundary
    ↓
Department
    ↓
QR survey and feedback records
```

## What is implemented today

- Facility configuration carries state, district, and block code fields alongside facility identity and coordinates.
- Public QR flows resolve feedback to a specific facility and department.
- Session and API checks enforce facility scope for facility-scoped administrative users.
- Analytics, response, QR, report, and CAPA operations apply permitted facility scope on the server.
- Higher-scoped roles can receive only facilities permitted by their assigned role/scope.

## What is not currently implemented

Abhipraya does **not** currently provide a separate Boundary API for searching or managing a complete hierarchy of state, district, block, ward, and locality entities. It also does not currently treat `stateCode`, `districtCode`, or `blockCode` as independently managed authorisation boundaries for all queries.

Some facility entries may use placeholder or incomplete administrative codes. Therefore, deployments must not rely on the current facility master as a certified administrative-boundary registry without first validating and completing that data.

## Boundary read API

Abhipraya now exposes read-only boundary records derived from the configured facility master. This is useful for lookup and navigation; it does not make the facility master a certified government boundary registry.

```text
Authoritative administrative boundary source
        ↓
Versioned boundary master (state → district → block → facility)
        ↓
Boundary search/read API
        ↓
Role-to-boundary assignment
        ↓
Server-side query scope for analytics, responses, QR, reports, and CAPA
```

### Current API shape

| Method | Suggested endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/v1/boundaries` | Search/read configured boundary records by hierarchy level, parent, or text search. |
| `GET` | `/api/v1/boundaries/{code}` | Read one configured boundary record and its direct child records. |

`GET /api/v1/boundaries` supports the optional query parameters `level` (`state`, `district`, `block`, or `facility`), `parent`, `search`, and `limit`. Boundary codes are generated from the configured hierarchy, for example `state-10`, `district-10-0`, `block-10-0-0`, and `facility-<facilityNIN>`.

The read endpoints are public because they expose only configured administrative/facility reference data. They do not expose users, responses, sessions, credentials, or analytics.

## Controlled import extension

`POST /api/v1/boundaries/import` is not implemented. It should be introduced only after the deployment confirms an authoritative source and import contract.

### Requirements before implementation

1. Select an authoritative government or programme boundary source and define refresh ownership.
2. Establish stable identifiers, parent-child relationships, labels, language/localisation fields, and effective dates.
3. Validate current facility `stateCode`, `districtCode`, and `blockCode` values against that master.
4. Define which roles may access state, district, block, facility, and department scopes.
5. Add server-side boundary predicates to every protected data query; never enforce them only in UI filters.
6. Add a restricted, allow-listed import route with role/CSRF checks and documented retention/audit requirements.

## Related documentation

- [API access control](access_control.md)
- [API specifications](api_specifications.md)
- [Technical architecture overview](../architecture/technical_architecture.md)
- [Data dictionary and ER overview](../database/data_dictionary_erd.md)

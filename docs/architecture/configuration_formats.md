# Configuration JSON formats

## Before you change master data

Abhipraya uses controlled JSON files under `api/masters/` for reference and survey configuration. In general documentation, a **service location** is the place where feedback is collected; the current implementation calls it a facility and uses the existing `facilityNIN` technical identifier. A **service area** is the current department concept.

Do not edit production configuration directly. Make a working copy, validate JSON, review the change, and deploy it through the approved release process. For published surveys, use the version publisher; never overwrite a published survey file.

## Configuration locations

| File or directory | Purpose | Primary identifier |
| --- | --- | --- |
| `api/masters/facilityCodes.json` | Service-location identity and lookup information | `facilityNIN` |
| `api/masters/departmet.json` | Service-area/department names and language labels | `departmentId` |
| `api/masters/rolecode.json` | Role identifiers and descriptions | `role_id`, `role_code` |
| `api/masters/radius.json` | Default and service-location-specific location validation | `facilityNIN` override key |
| `api/masters/buttons.json` | Public interface button labels | `lang`, `btn` |
| `api/masters/thankyou_messages.json` | Public submission messages | `lang` |
| `api/masters/surveys/department_<id>/manifest.json` | Active survey version and schema hash | `department_id`, `version` |
| `api/masters/surveys/department_<id>/v<version>/survey.json` | Versioned questions and analytical metadata | `question_id`, `indicator_key` |
| `api/masters/dept_id_<id>.json` | Existing legacy department survey compatibility | `qn`, `lang` |

## 1. Create a service location (current file: facility)

Add one record per language to `api/masters/facilityCodes.json`. Use the same `facilityNIN` in every language record. Do not reuse or change an existing `facilityNIN`; it is used by QR references, feedback records, and scope checks.

```json
[
  {
    "langCode": 1,
    "facilityNIN": 1234567890,
    "facilityName": "Example Citizen Service Centre",
    "facilityAddress": "Example District",
    "facilityLat": "25.61000",
    "facilityLong": "85.12000",
    "facilityType": "Service Centre",
    "stateCode": "10",
    "districtCode": "101",
    "blockCode": "1001",
    "geo_radius": "500"
  },
  {
    "langCode": 2,
    "facilityNIN": 1234567890,
    "facilityName": "Example service location name in the second language",
    "facilityAddress": "Example district name in the second language",
    "facilityLat": "25.61000",
    "facilityLong": "85.12000",
    "facilityType": "Service Centre",
    "stateCode": "10",
    "districtCode": "101",
    "blockCode": "1001",
    "geo_radius": "500"
  }
]
```

### Required rules

- `langCode: 1` is the primary English record; add every language supported by the deployment.
- `facilityNIN` must be numeric, unique for the service location, and stable.
- Use decimal latitude/longitude strings and validate that they point to the actual service location.
- `stateCode`, `districtCode`, and `blockCode` should come from the approved administrative master where available.
- `facilityType` is a configurable category; use a controlled list appropriate to the programme.
- `geo_radius` is descriptive facility data. The active validation rule is managed in `radius.json`.

## 2. Create a service area (current file: department)

Add a language entry for every service area to `api/masters/departmet.json`. The same `departmentId` must be used for all translations.

```json
[
  {
    "langCode": 1,
    "departmentId": 10,
    "departmentName": "Citizen Help Desk"
  },
  {
    "langCode": 2,
    "departmentId": 10,
    "departmentName": "Citizen Help Desk - second-language label"
  }
]
```

### Required rules

- Choose a new positive integer `departmentId`; never reuse an ID that belongs to another service area.
- Keep the ID stable after QR links or survey packages use it.
- Add the service area to each required language.
- Create the matching survey package directory: `api/masters/surveys/department_10/`.

## 3. Create or update a role

Roles are defined in `api/masters/rolecode.json`. Role changes affect server-side access and must be reviewed by security/operations owners before release.

```json
{
  "role_id": 7,
  "role_code": "LOCATION_MANAGER",
  "role_name": "Service Location Manager",
  "active_status": 1,
  "description": "Manages the assigned service location, QR codes, and permitted reports."
}
```

### Required rules

- `role_id` is a stable positive integer; do not renumber existing roles.
- `role_code` uses uppercase letters, numbers, and underscores only.
- `active_status` is `1` for active and `0` for disabled.
- Adding JSON role data alone does not grant a capability. Update the server-side authorisation checks and tests before assigning a new role to users.

## 4. Configure location validation

`api/masters/radius.json` controls public-survey location validation. The global values set the allowed bounds; `facility_overrides` can set a radius for a specific existing `facilityNIN`.

```json
{
  "default_radius_meters": 200,
  "minimum_radius_meters": 25,
  "maximum_radius_meters": 5000,
  "duplicate_window_hours": 24,
  "facility_overrides": {
    "1234567890": 500
  }
}
```

Keep location rules proportionate to the service context and privacy purpose. Do not increase a radius or duplicate window without programme approval.

## 5. Configure public button labels and thank-you messages

Add button labels to `buttons.json`:

```json
{ "lang": "1", "btn": "1", "txt": "Start survey" }
```

`btn` values currently identify the public-flow controls: `1` Start, `2` Previous, `3` Next, and `4` Submit. Add a matching record for every language.

Add a completion message to `thankyou_messages.json`:

```json
{
  "lang": 1,
  "icon": "check-circle",
  "message": "Thank you. Your feedback has been recorded."
}
```

Keep messages short, clear, non-identifying, and appropriate for the configured service domain.

## 6. Create a survey package and manifest

Create a directory for the service area/department, then create its manifest:

```text
api/masters/surveys/department_10/
├── manifest.json
└── v1.0/
    └── survey.json
```

Example `manifest.json`:

```json
{
  "department_id": 10,
  "survey_code": "SERVICE_AREA_10_FEEDBACK",
  "active_version": "1.0",
  "versions": [
    {
      "version": "1.0",
      "status": "published",
      "path": "v1.0/survey.json",
      "schema_hash": "<generated SHA-256 hash>"
    }
  ]
}
```

Do not invent or manually change a published `schema_hash`. Use the survey publisher to validate the candidate file, generate the hash, and update the manifest.

## 7. Create survey questions

Each question has one record for each supported language. The current public form supports `qn` values from `1` to `31`. Use a stable question/indicator identity and explicit option values.

```json
{
  "qn": 1,
  "lang": "1",
  "question_id": "SERVICE_AREA_10_Q1_V1_0",
  "indicator_key": "SERVICE_AREA_10_WAIT_TIME",
  "ques": "How satisfied were you with the waiting time?",
  "report_type": "rating",
  "icon": "clock",
  "options": [
    { "value": 1, "text": "Very dissatisfied", "sentiment": "negative" },
    { "value": 2, "text": "Dissatisfied", "sentiment": "negative" },
    { "value": 3, "text": "Neutral" },
    { "value": 4, "text": "Satisfied", "sentiment": "positive" },
    { "value": 5, "text": "Very satisfied", "sentiment": "positive" }
  ]
}
```

### Question fields

| Field | Requirement |
| --- | --- |
| `qn` | Integer from `1` to `31`, stable within the versioned survey. |
| `lang` | Supported language identifier, for example `1` and `2`. |
| `question_id` | Immutable question identity for this survey version. |
| `indicator_key` | Stable only while the measured concept remains comparable. |
| `ques` | The question text for that language. |
| `report_type` | Defines analytics/validation treatment. |
| `options` | Explicit stable values and translated labels. |
| `sentiment` | Recommended for positive/negative interpretation where relevant. |
| `positive_value` / `negative_value` | Required where binary meaning must be made explicit. |
| `icon` | Optional presentation token. |

Supported current public-form report types are `rating`, `binary`, `category`, `availability`, `severity`, `demographic`, and `consent`. See [Survey question types](../survey-question-types-reference.md) for interpretation rules and reserved types.

## 8. Validate and publish a survey version

Create the candidate survey JSON outside any published `v<version>/` directory. The publisher requires an existing configured department and manifest, and the candidate version must be higher than the manifest's active version.

For example, prepare:

```text
<candidate-survey.json>
```

Run a dry run from the project root first:

```text
php tools\publish_survey_version.php --department=4 --version=1.1 --source=<candidate-survey.json>
```

The dry run validates JSON, language coverage, question numbers, indicator keys, report types, option values, and compatibility with the active version. It also calculates and displays the SHA-256 schema hash, but changes no files.

Review added, removed, reworded, moved, or option-changed indicators. When validation succeeds, the command ends with:

```text
Validation passed. No files were changed.
```

Only after review, publish the version:

```text
php tools\publish_survey_version.php --department=4 --version=1.1 --source=<candidate-survey.json> --publish
```

Publishing automatically creates `api/masters/surveys/department_4/v1.1/survey.json`, verifies the generated hash, updates `manifest.json`, retires the previous active version, and activates `1.1`.

For later changes, publish `1.2`, `1.3`, and so on. Never overwrite `v1.0` or any other published version. Create a new `indicator_key` when a question's meaning, rating scale, or reporting semantics change.

## Final checklist

- JSON parses successfully.
- Stable IDs are unique and never repurposed.
- All required translations are present.
- Facility/service-location values match approved operational data.
- Role changes have matching server-side authorisation tests.
- Location rules are approved and proportionate.
- Survey questions have stable option values, report types, and versioned identities.
- A survey dry run passes before publication.
- The deployed configuration is verified with a QR/public-survey and authorised-administrator test.

## Related documentation

- [Master data management](master_data_management.md)
- [Survey version publishing](../survey-version-publishing.md)
- [Survey question types](../survey-question-types-reference.md)
- [Boundary and administrative scope](../api/boundary.md)

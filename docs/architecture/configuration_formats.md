# Configuration JSON formats

## Configuration locations

| File or directory | Purpose |
| --- | --- |
| `api/masters/facilityCodes.json` | Facility identity and lookup information. |
| `api/masters/departmet.json` | Department names and language labels. |
| `api/masters/rolecode.json` | Role identifiers and descriptions. |
| `api/masters/radius.json` | Default and facility-specific location radius configuration. |
| `api/masters/buttons.json` | Interface button labels. |
| `api/masters/thankyou_messages.json` | Public submission messages. |
| `api/masters/surveys/department_<id>/manifest.json` | Active survey version and schema hash. |
| `api/masters/surveys/department_<id>/v<version>/survey.json` | Versioned questions and analytical metadata. |

## Survey manifest

```json
{
  "department_id": 4,
  "survey_code": "DEPARTMENT_4_FEEDBACK",
  "active_version": "1.0",
  "versions": [
    {
      "version": "1.0",
      "status": "published",
      "path": "v1.0/survey.json",
      "schema_hash": "<sha-256 hash>"
    }
  ]
}
```

## Question metadata

Each analytical question should include:

- `qn`: stable question number used by the current legacy response-column mapping.
- `question_id`: immutable identity for this question version.
- `indicator_key`: stable identity only while the indicator meaning remains unchanged.
- `report_type`: how validation and analytics interpret the answer.
- `options`: stable numeric values and translated display labels.
- `icon`: presentation icon for the UI.
- `positive_value`, `negative_value`, or option sentiment where the result is favourable/unfavourable.

```json
{
  "qn": 17,
  "question_id": "DEPT_1_Q17_V1_0",
  "indicator_key": "DEPT_1_Q17",
  "report_type": "binary",
  "ques": "Did you have to pay any extra money at the hospital?",
  "positive_value": 2,
  "negative_value": 1,
  "icon": "💵",
  "options": [
    {"value": 1, "text": "Yes", "sentiment": "negative"},
    {"value": 2, "text": "No", "sentiment": "positive"}
  ]
}
```

## Authoring rules

1. Never edit a published survey version in place.
2. Create a new version when meaning, response scale, option semantics, or report type changes.
3. Use a new `indicator_key` when an old indicator is no longer comparable.
4. Keep option values stable; do not rely on their array position.
5. Add English and Hindi labels for public questions.
6. Validate the package and publish it through the version publishing process.

See [Survey question types](../survey-question-types-reference.md) and [Survey version publishing](../survey-version-publishing.md).

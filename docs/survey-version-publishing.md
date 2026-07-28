# Publishing a new Abhipraya survey version

Published survey JSON files are immutable. Never edit a file inside an existing
`v1.0`, `v1.1`, or other version directory.

## 1. Prepare the candidate JSON

Copy the active `survey.json` to a separate working location and edit that copy.

Every question must:

- use `qn` from 1 through 31;
- have English (`lang: "1"`) and Hindi (`lang: "2"`) translations;
- retain the same `indicator_key` when the indicator meaning is unchanged;
- use a new `indicator_key` when its meaning, report type, or rating scale changes;
- define `report_type`;
- define at least two answer options;
- use stable positive integer option values.

The current public UI supports these single-choice report types:

- `rating`
- `binary`
- `category`
- `availability`
- `severity`
- `demographic`
- `consent`

Other types in `survey-question-types-reference.md` are reserved until their
corresponding public form controls and storage validation are implemented.

## 2. Run a dry run

Run the command from the Abhipraya project root:

```powershell
php tools\publish_survey_version.php `
  --department=4 `
  --version=1.1 `
  --source=<candidate-survey.json>
```

The dry run validates the file, compares it with the active version, displays
added, removed, moved, reworded, and option-changed indicators, and writes
nothing.

## 3. Publish

After reviewing the dry-run output:

```powershell
php tools\publish_survey_version.php `
  --department=4 `
  --version=1.1 `
  --source=<candidate-survey.json> `
  --publish
```

Publishing:

1. creates `api/masters/surveys/department_4/v1.1/survey.json`;
2. generates its SHA-256 schema hash;
3. marks the previous version as `retired`;
4. marks version `1.1` as `published`;
5. changes `active_version` in `manifest.json`;
6. leaves existing QR codes unchanged.

Responses already started on an older version can still submit against that
retired version because the browser sends the exact version and schema hash it
loaded.

## Safety rules

- Existing version directories can never be overwritten by the publisher.
- A version number must be greater than the active version.
- A published file whose hash differs from its manifest blocks publication.
- Changing a rating scale or report type requires a new `indicator_key`.
- Always run the dry run before using `--publish`.

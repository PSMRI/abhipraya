# Abhipraya overview

## Anonymous feedback that leads to better services

**Abhipraya** is an open-source, QR-based public-feedback platform for healthcare facilities. It gives beneficiaries a quick and anonymous way to share their experience, and gives authorised health officials clear evidence to identify and improve service gaps.

The first implementation focuses on healthcare facilities, departments, and patient experience. The core design is configuration-driven so that survey packages can evolve without changing the application code or rewriting historical responses.

> **Mission:** Turn protected beneficiary feedback into practical service improvement.

## The problem it addresses

A beneficiary may experience a problem—such as medicine being unavailable, a long wait, poor cleanliness, or staff unavailability—and leave the facility without a safe, simple channel to report it. Administrators then have limited timely evidence of where service gaps occur.

Abhipraya closes that loop:

```text
Beneficiary scans a department QR code
        ↓
Completes a short anonymous survey
        ↓
Responses are validated and stored
        ↓
Officials see scoped trends and low-performing indicators
        ↓
Facility records corrective and preventive action (CAPA)
        ↓
Service improvement can be monitored over time
```

## Objectives

- Make it easy for beneficiaries to give feedback without an account, name, mobile number, or Aadhaar number.
- Collect feedback at the relevant facility and department through QR-code entry points.
- Support English and Hindi surveys, with survey content controlled by JSON configuration.
- Preserve respondent privacy while using configured location validation and duplicate-submission controls.
- Present clear, role-scoped dashboards, analysis, reports, and CAPA actions to health officials.
- Keep historical feedback interpretable when questions or survey versions change.
- Provide an open-source foundation that can be adopted and configured by other health programmes.

## Who uses Abhipraya

| User | What they do |
| --- | --- |
| Beneficiary / public respondent | Scans a QR code, selects a language, answers a short survey, and submits anonymous feedback. |
| Facility Administrator | Views only the assigned facility, generates department QR posters, reviews analysis and reports, and records CAPA actions. |
| Super Administrator / Main Administrator | Views permitted cross-facility information, manages operational use, and monitors trends. |
| Survey Manager | Prepares, validates, and publishes new survey versions. |
| Data Analyst / Viewer | Reviews permitted analytics and reports without changing survey configuration. |

Administrative access is enforced on the server. A facility-scoped account cannot obtain another facility's data merely by changing a browser filter.

## What the product includes

### Public feedback journey

1. A beneficiary scans a QR poster displayed at a facility department.
2. The link resolves the facility, department, and active survey version.
3. The beneficiary selects a supported language.
4. The survey collects the configured answers. Location validation is requested when enabled for that facility.
5. The response is submitted anonymously and a thank-you message is shown.

### Administrative workspace

| Module | Purpose |
| --- | --- |
| **Home** | Shows the current feedback position, priority issues, trends, department performance, recent feedback, and CAPA follow-up in the user’s permitted scope. |
| **QR Center** | Generates, previews, downloads, and prints department QR posters. |
| **Analytics** | Explores facility, department, survey-version, and indicator trends, including response count and type-appropriate results. |
| **Reports** | Produces scoped summary and detailed reports for the selected period and filters. |
| **CAPA** | Records corrective and preventive actions for low-performing department indicators. |
| **Profile** | Lets an authenticated administrator update permitted profile details and change their password. |

## Configuration and data model

Abhipraya separates stable configuration from transactional feedback data.

### JSON configuration

The following are maintained in JSON under `api/masters/`:

- Facility codes and configured facility radius.
- Department labels and translations.
- Role codes.
- Button labels and thank-you messages.
- Department survey packages, question definitions, option values, icons, reporting types, and survey versions.

### Database records

The database stores operational and audit data, including:

- Administrator accounts, sessions, and login attempts.
- Feedback response rows and their answer values.
- QR activity and generated QR records.
- CAPA action records.
- Audit and export events where enabled.

The database is not used as a second master source for departments, questions, or role definitions. This avoids configuration drift.

## Survey versioning

Every department has a survey manifest and a versioned survey package, for example:

```text
api/masters/surveys/department_4/
├── manifest.json
└── v1.0/
    └── survey.json
```

The manifest identifies the active published version and its SHA-256 schema hash. When a question meaning, reporting type, or scale changes, a new survey version and—where appropriate—a new `indicator_key` are used. Historical responses remain linked to the version and question structure that was active when they were submitted.

See [Publishing a survey version](../survey-version-publishing.md) and the [Survey question type reference](../survey-question-types-reference.md).

## Analytics principles

Abhipraya does not treat every answer as a star rating. Each question declares a `report_type`, such as:

- `rating` for average score and stars;
- `binary` or `availability` for positive/negative or available/unavailable percentages;
- `category` and `multi_category` for distributions;
- `numeric` and `duration` for numeric summaries;
- `text` for response counts and governed review;
- `severity` for priority alerts.

This ensures, for example, that “No extra money paid” is interpreted as a positive result when its JSON configuration defines that value as positive.

## Privacy and safety

Public feedback is designed to be anonymous. The public form should not ask for a beneficiary's name, phone number, Aadhaar number, or unnecessary medical information. Location and device-related controls must be configured and operated proportionately, only for response validation and service improvement.

Administrators see aggregated and scoped feedback. They should use it to improve services, not to identify or retaliate against respondents.

## Phase 1 scope

The current Phase 1 focus is:

- QR-led department feedback.
- Multilingual, JSON-configured surveys.
- Anonymous response submission and validation.
- Scope-based Home, Analytics, and Reports.
- QR poster generation.
- Survey version filtering and historical interpretation.
- CAPA support for low-performing indicators.
- Secure administrator login, profile updates, and password change.

Features such as mobile offline synchronisation, notifications, voice recording, AI analysis, advanced integrations, and multi-tenant package administration are planned future enhancements rather than assumed current capabilities.

## Related documentation

- [User guide](../user/user_guide.md)
- [Technical architecture](technical_architecture.md)
- [Survey version publishing](../survey-version-publishing.md)
- [Survey question types](../survey-question-types-reference.md)
- [Security guide](../security.md)
- [Digital Public Good readiness](../dpg-readiness.md)

# Data redistribution approval

## Default rule

Abhipraya's standard reports and exports must not redistribute personal data, raw IP addresses, device identifiers, precise coordinates, session data, credentials, or unredacted free-text feedback. Use aggregated or appropriately redacted data by default.

## Approval required before sharing data outside the authorised operating scope

The deployment owner must document all of the following before any redistribution:

| Required decision | Record |
| --- | --- |
| Purpose and recipient | Why the data is needed, recipient organisation, and intended use. |
| Legal basis and policy | Applicable law, programme policy, agreement, and restrictions. |
| Data minimisation | Exact fields, aggregation/redaction method, and minimum-cell threshold. |
| Access and transfer | Approved recipient access route, encryption, retention period, and deletion/return method. |
| Approval | Named data owner, legal/privacy reviewer, date, and expiry/review date. |
| Audit evidence | Export event, filters/scope, file checksum or identifier, and confirmation of secure transfer. |

## Prohibited release content

Never commit production exports, database backups, identity data, sensitive incident records, or approval records containing personal information to this public repository.

## Approval record template

Keep this completed record in the deployment owner's restricted evidence store, not in the public repository:

```text
Dataset and schema version:
Source environment and extraction date:
Purpose and recipient:
Fields included and fields removed:
Aggregation/redaction and minimum-cell rule:
Legal/policy basis and transfer restrictions:
Retention, deletion/return method, and transfer protection:
Data owner approval / privacy review / expiry date:
Audit-event reference and export identifier:
```

Use this record with the [non-PII data export and import guide](non_pii_data_export_import.md), [privacy and data protection guide](privacy_data_protection.md), and [public data audit](public_data_audit.md).

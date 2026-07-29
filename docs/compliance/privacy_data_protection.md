# Privacy and data protection

## Privacy objective

Abhipraya is designed for anonymous public feedback. The platform should collect only what is necessary to validate feedback and improve services.

## Public feedback rules

- Do not ask for name, phone number, Aadhaar number, medical record number, or unnecessary health details.
- Do not use free-text fields to request identity or personal health information.
- Configure location and device controls only for stated validation and duplicate-prevention purposes.
- Limit administrative access to aggregated and permitted operational information.
- Do not use feedback to identify, retaliate against, or disadvantage respondents.

## Administrator data

Administrator accounts require credentials and may hold protected profile details. Passwords must be stored as modern hashes; profile-sensitive fields belong in protected storage. Role scope must be enforced by the backend.

## Retention and exports

The Bihar deployment uses the following approved retention schedule:

| Record type | Retention period | End-of-period handling |
| --- | --- | --- |
| Feedback responses | 2 years | Archive or securely dispose of under the approved operational procedure. |
| Audit logs | 90 days | Securely dispose of under the approved operational procedure. |
| Export files | 2 years | Archive after the retention period. |
| Backups | 30 days | Rotate out through the approved backup process. |

Privacy and grievance contact: [abhipraya@piramalswasthya.org](mailto:abhipraya@piramalswasthya.org). Before sharing an export, remove or aggregate data that could reasonably identify a respondent.

## Incident response

If unauthorised access, accidental disclosure, or improper use is suspected: restrict access, preserve logs, notify the responsible security/privacy contact, assess impact, and follow the organisation’s approved incident process.

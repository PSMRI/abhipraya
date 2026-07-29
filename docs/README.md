# Abhipraya documentation

## Anonymous feedback for better healthcare services

Abhipraya is an open-source, QR-based public-feedback platform for public-service locations and operating units. The current implementation is configured for healthcare facilities. It helps beneficiaries share feedback anonymously and helps authorised officials identify gaps, monitor trends, and document improvement actions.

## Terminology used across this documentation

Abhipraya can be configured for many public-service domains. In general documentation, **service location** or **operating unit** means the place where feedback is collected and acted on. A healthcare **facility** is the current configuration example. Existing technical fields, APIs, file names, database columns, QR references, and current user-interface labels such as `facilityNIN`, `facility_nin`, and `facilityCodes.json` remain unchanged for compatibility.

## Start here

Source code: [PSMRI/abhipraya](https://github.com/PSMRI/abhipraya)

| Audience | Recommended guide |
| --- | --- |
| Health officials and facility teams | [User guide](user/user_guide.md) |
| Developers and implementers | [Abhipraya overview](architecture/project_overview.md) |
| New developers | [First 30 minutes for a new developer](developer_first_30_minutes.md) |
| API integrators | [API specifications](api/api_specifications.md) |
| API security reviewers | [API access control](api/access_control.md) |
| Security and privacy teams | [Encryption and cryptographic protection](api/encryption.md) |
| Programme data and scope owners | [Boundary and administrative scope](api/boundary.md) |
| Programme sponsors and partners | [Why Abhipraya](architecture/why_abhipraya.md) |
| Solution architects and technical reviewers | [Technical architecture overview](architecture/technical_architecture.md) |
| Technology and deployment teams | [Technology architecture and open-source tools](architecture/technology_architecture.md) |
| Master-data owners and survey managers | [Master data management](architecture/master_data_management.md) |
| Infrastructure and operations teams | [Infrastructure architecture](architecture/infrastructure_architecture.md) |
| Release and DevOps teams | [Deployment architecture](architecture/deployment_architecture.md) |
| Survey authors | [Survey version publishing](survey-version-publishing.md) |
| Security and operations teams | [Security guide](security.md) |

## Documentation sections

- **Product and architecture** — [overview](architecture/project_overview.md), [use cases](architecture/use_cases.md), [technical architecture](architecture/technical_architecture.md), [service map](architecture/service_map.md), [configuration formats](architecture/configuration_formats.md), and [coding standards](architecture/coding_standards.md).
- **User guide** — [administrator and public feedback guide](user/user_guide.md).
- **Developer guide** — [database](database/data_dictionary_erd.md), [API](api/README.md), and [developer guide](developer-guide.md).
- **Security and operations** — [security](security.md), [deployment](deployment/deployment_guide.md), [backup and restore](deployment/backup_restore_guide.md), [troubleshooting](deployment/troubleshooting_faq.md), [testing](testing/test_plan.md), and [accessibility](testing/wcag_web_platform_compliance.md).
- **Open source and DPG** — [privacy](compliance/privacy_data_protection.md), [governance](compliance/governance_and_ownership.md), and [open-source/DPG readiness](compliance/open_source_dpg_release_status.md).
- **Release readiness** — [open-source/DPG release status](compliance/open_source_dpg_release_status.md) and [DPG readiness](dpg-readiness.md).


## DPG readiness evidence

The [DPG readiness](dpg-readiness.md) page links the current evidence set: [SDG mapping](compliance/sdg_mapping.md), [evidence register](compliance/dpg_evidence_register.md), [non-PII export](compliance/non_pii_data_export_import.md), [open standards](compliance/open_standards_mapping.md), and [privacy confirmation](compliance/legal_privacy_confirmation.md).

### SDG relevance and public-benefit evidence

Read the full [SDG relevance and public-benefit evidence](compliance/sdg_mapping.md) record. It explains why Abhipraya maps primarily to SDG 3, supports SDG 10, and treats SDG 16/17 as candidate mappings requiring additional evidence.

Run `php tools/dpg_readiness_check.php` to verify the evidence set and report declared SDG categories plus heuristic SDG candidates. Candidate mappings require human review and deployment evidence.

Run `php tools/open_source_readiness_check.php` to verify the open-source release documentation baseline. The result is a maintainer review aid, not release approval.

## Licence and open-source governance

Abhipraya is licensed under [GPL-3.0-or-later](../LICENSE). See [licence consistency and attribution](compliance/license_consistency.md), [public data audit](compliance/public_data_audit.md), and the [data privacy policy baseline](compliance/data_privacy_policy.md).

## Mobile documentation access

The documentation site is designed for phone and tablet use. On small screens, use the menu button in the header to open the slide-out documentation sidebar; select a section to expand it and open a document. Tables can scroll horizontally so that no evidence or report column is hidden.

For the best experience on a phone, open the documentation home page first, choose the required section from the navigation, and then read the selected page below it.

Use the [GitBook table of contents](SUMMARY.md) to browse the available pages. For HTML deployment instructions, see [GitBook publishing](gitbook.md).

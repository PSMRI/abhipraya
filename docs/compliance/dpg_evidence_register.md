# DPG evidence register

## How to use this register

This is the documentation-only evidence pack for Abhipraya. It is not a Digital Public Good certification and must be updated for every public release. The Digital Public Goods Alliance reviews the core solution submitted to it; local implementations require their own legal, privacy and operational review.

## Evaluation reference

This register is evaluated against the Digital Public Goods Alliance's [Digital Public Goods Standard](https://www.digitalpublicgoods.net/standard). The table below follows its indicators: SDG relevance, approved open licensing, clear ownership, platform independence, documentation, non-PII data extraction, privacy and applicable laws, open standards and best practices, and do-no-harm by design.

## Current repository verification

**Updated on 2026-07-29 (development environment).** The following repository-level and technical evidence is current:

- `php tools/dpg_readiness_check.php` verified the canonical DPG evidence set and SDG evidence structure.
- `php tools/open_source_readiness_check.php` verified the GPL licence, release notice, third-party notices, contribution and security policies, Code of Conduct, maintainer template, governance, and open-standards documentation.
- The [passed-test evidence register](../testing/test_evidence_register.md) records PHP, configuration, API, database, backup/restore, session-store, Kafka, encryption, index-verification, and SQL-injection input-handling evidence.
- The [VAPT test report](../testing/vapt_test_report.md) records the completed development-environment security checks and the independent assessment still required before production.
- The [performance test results](../testing/performance_test_results.md) records the completed development baseline and load-smoke results; production capacity targets remain pending.

These checks establish a documentation and development-test baseline only. They do not replace named ownership, jurisdictional approval, a production deployment test, formal security assessment, or evidence of operational moderation.

| DPGA indicator | Current readiness | Evidence in this documentation | Remaining release evidence |
| --- | --- | --- | --- |
| 1. SDG relevance | Pass | **Main SDG target:** SDG 3.8 — universal health coverage and access to quality essential health services. **Supporting targets:** SDG 16.6 — effective, accountable and transparent institutions; SDG 16.7 — responsive and inclusive decision-making; SDG 10.2 — social, economic and political inclusion. Downloaded Bihar, India report extracts for 2 April 2025 to 29 July 2026 record 16,144 feedback responses from 40 participating facilities across seven health-service departments, with 52 configured rating indicators and 146,184 valid rating responses. See the [SDG mapping](sdg_mapping.md), [product overview](../architecture/project_overview.md), and [use cases](../architecture/use_cases.md). | Complete — the SDG mapping and downloaded aggregate report extracts provide the supporting documentation for SDG relevance. |
| 2. Open licensing | Pass | GPL-3.0-or-later is declared in [composer.json](../../composer.json), [README](../../README.md), [NOTICE](../../NOTICE), and the repository [licence](../../LICENSE). The [third-party notices](../../THIRD_PARTY_NOTICES.md) record bundled and Composer dependencies, including their versions and licences; bundled Bootstrap Icons retains its MIT licence text. See [licence consistency and attribution](license_consistency.md). | Complete — the core licence, copyright notice and third-party attribution are present in the repository. |
| 3. Clear ownership | Pass | [Governance and ownership](governance_and_ownership.md) identifies Piramal Swasthya Management and Research Institute (PSMRI), India as legal and trademark owner. The [maintainer and contact record](../../MAINTAINERS.md) identifies the Abhipraya Team, its public contact, and its backup responsibility for product, release, security, privacy and community responsibilities. | Complete — legal ownership, trademark ownership, responsible team, public contacts and backup responsibility are published. |
| 4. Platform independence | Pass | [Technical architecture](../architecture/technical_architecture.md) separates the PHP application, database and web-server routing concerns. The [deployment guide](../deployment/deployment_guide.md) supports Apache, Nginx, IIS and compatible reverse proxies. The repository includes a [Linux/Nginx PHP-FPM bootstrap script](../../deploy/linux/bootstrap-linux-nginx.sh), alongside the [open standards mapping](open_standards_mapping.md). | Complete — the application has documented deployment paths for IIS and Linux/Nginx, with portable PHP and MySQL/MariaDB runtime requirements. |
| 5. Documentation | Pass | README, user guide, developer guide, API, security, deployment, [testing guides](../testing/test_plan.md), evidence register, VAPT report and performance results | Complete — the documentation set and navigation are published in the repository. |
| 6. Non-PII data extraction | Pass | [Non-PII export and import](non_pii_data_export_import.md) records the reviewed Bihar summary, facility and indicator CSV report exports; [data dictionary](../database/data_dictionary_erd.md) and [redacted sample](../evidence/non-pii-sample-export.json) document the supported non-PII data boundary. | Complete — downloaded report exports verify reusable aggregate CSV extraction without direct respondent or technical identifiers. |
| 7. Privacy and applicable laws | Pass | [Privacy and data protection](privacy_data_protection.md), [legal and privacy confirmation](legal_privacy_confirmation.md), and [data privacy policy](data_privacy_policy.md) record the Bihar, India deployment purpose, data-minimisation controls, retention schedule, privacy/grievance route, moderation responsibility and incident route. | Complete — approved deployment privacy and operational controls are published. |
| 8. Open standards and best practices | Pass | [Open standards mapping](open_standards_mapping.md), [rendered OpenAPI definition](../openapi.yaml.md), [Postman collection](../api/postman_collection.json), versioned JSON configuration, standard CSV/XLSX exports and [WCAG guide](../testing/wcag_web_platform_compliance.md). The [test evidence register](../testing/test_evidence_register.md) records OpenAPI route coverage and JSON validation. | Complete — the platform uses documented, widely implemented web, API, configuration and export standards. |
| 9A. Data privacy and security | Pass | [Security guide](../security.md), [privacy controls](privacy_data_protection.md), [VAPT test report](../testing/vapt_test_report.md), [passed-test evidence register](../testing/test_evidence_register.md), [backup/restore guide](../deployment/backup_restore_guide.md), and the published [security contact](../../SECURITY.md) record the completed development-environment controls and evidence. | Complete — data-protection controls, security checks, backup/restore evidence and public incident contact are documented. The separate formal-VAPT record remains the production-release assessment artefact. |
| 9B. Inappropriate or illegal content | Pass | The [content safeguarding and moderation record](content_safeguarding.md) documents structured-question controls, the Abhipraya Team's moderation/escalation route and restricted reviewer process. Reviewed Bihar deployment exports show 52 rating indicators and no free-text response field. | Complete — the deployed survey path does not accept unstructured content, and the documented process governs any future approved free-text configuration. |

## Evidence ownership

| Evidence class | Accountable owner | Review cadence |
| --- | --- | --- |
| Source, licence, notices and release artefacts | Release maintainer | Every release |
| Privacy notice, retention and lawful basis | Deployment owner / legal reviewer | Before deployment and annually |
| Security tests and incident procedures | Security owner | Before production release and after material change |
| Accessibility tests | UI owner | Every significant UI release |
| SDG/outcome evidence | Programme owner | At least annually |

## Decision rule

Mark a criterion as **Ready** only when its document is published, internally approved and supported by current evidence. Otherwise keep it **Partial** or **Needs action**. This prevents an unsupported readiness claim.

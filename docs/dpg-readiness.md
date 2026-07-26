# Digital Public Good readiness

## Purpose and status

This page is Abhipraya's self-assessment against the [Digital Public Goods Standard](https://www.digitalpublicgoods.net/standard). The Standard has nine indicators, including SDG relevance, open licensing, ownership, platform independence, documentation, non-PII data extraction, privacy, open standards, and data privacy/security with safeguards against inappropriate or illegal content.

**Current status: documentation foundation in place; not yet DPGA-certified.** Only the Digital Public Goods Alliance can determine whether a submitted core solution qualifies for the DPG Registry.

## Readiness matrix

| Indicator | Status | Documentation and evidence | Required closure before nomination |
| --- | --- | --- | --- |
| SDG relevance | Partial | [SDG mapping](compliance/sdg_mapping.md) | Publish measurable, implementation-neutral public-benefit evidence. |
| Open licensing | Foundation | [Open-source release status](compliance/open_source_dpg_release_status.md) | Confirm licence, notices and third-party attribution in the release repository. |
| Clear ownership | Partial | [Governance and ownership](compliance/governance_and_ownership.md) | Name maintainers, owner, release contacts and trademark position. |
| Platform independence | Partial | [Technical architecture](architecture/technical_architecture.md), [open standards mapping](compliance/open_standards_mapping.md) | Document a tested open deployment path and all mandatory dependencies. |
| Documentation | Ready foundation | README, user guide, developer guide, API, deployment, security and test documentation | Keep all evidence current for the submitted release. |
| Non-PII data extraction | Partial | [Non-PII export/import guide](compliance/non_pii_data_export_import.md) | Retain a tested, redacted sample export and scope-control test evidence. |
| Privacy and applicable laws | Partial | [Privacy guide](compliance/privacy_data_protection.md), [legal/privacy template](compliance/legal_privacy_confirmation.md) | Complete jurisdiction-specific legal review, notice, retention and grievance route. |
| Open standards and best practices | Partial | [Open standards mapping](compliance/open_standards_mapping.md), API and WCAG guides | Publish OpenAPI and JSON Schema; retain accessibility test evidence. |
| Data privacy, security and do no harm | Partial | [Security guide](security.md), [test plan](testing/test_plan.md), [release checklist](compliance/release_checklist.md) | Complete security retest, incident response, free-text moderation and remediation evidence. |

## Evidence pack

Use the following documents together when preparing a review:

- [DPG evidence register](compliance/dpg_evidence_register.md)
- [SDG mapping](compliance/sdg_mapping.md)
- [Open-source and DPG release status](compliance/open_source_dpg_release_status.md)
- [Non-PII data export and import](compliance/non_pii_data_export_import.md)
- [Open standards mapping](compliance/open_standards_mapping.md)
- [Legal and privacy confirmation template](compliance/legal_privacy_confirmation.md)
- [Open-source and DPG release checklist](compliance/release_checklist.md)
- [Open-source readiness checklist](compliance/open_source_readiness_checklist.md)
- [Licence consistency and attribution](compliance/license_consistency.md)
- [Public data audit](compliance/public_data_audit.md)
- [Data privacy policy baseline](compliance/data_privacy_policy.md)
- [GPL-3.0-or-later licence](../LICENSE), [NOTICE](../NOTICE), and [third-party notices](../THIRD_PARTY_NOTICES.md)
- [Code of Conduct](../CODE_OF_CONDUCT.md) and [maintainer/release-contact template](../MAINTAINERS.md)

## Nomination guardrails

1. Submit the **core open-source solution**, not confidential local deployment data.
2. Do not call Abhipraya a certified Digital Public Good until the DPGA has completed its review.
3. Keep local legal, health-record and data-protection obligations with the deploying organisation.
4. Reassess the evidence pack before each public release and after any material privacy, security or architectural change.

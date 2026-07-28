# Digital Public Good readiness

## Purpose and status

This page is Abhipraya's self-assessment against the [Digital Public Goods Standard](https://www.digitalpublicgoods.net/standard). The Standard has nine indicators, including SDG relevance, open licensing, ownership, platform independence, documentation, non-PII data extraction, privacy, open standards, and data privacy/security with safeguards against inappropriate or illegal content.

**Current status: documentation foundation in place; not yet DPGA-certified.** Only the Digital Public Goods Alliance can determine whether a submitted core solution qualifies for the DPG Registry.

## Readiness matrix

| Indicator | Status | Documentation and evidence | Required closure before nomination |
| --- | --- | --- | --- |
| SDG relevance | Partial | [SDG relevance and public-benefit evidence](sdg-mapping.md) | Publish measurable, implementation-neutral public-benefit evidence. |
| Open licensing | Foundation | [Open-source release status](compliance/open_source_dpg_release_status.md) | Confirm licence, notices and third-party attribution in the release repository. |
| Clear ownership | Partial | [Governance and ownership](governance.md) | Name maintainers, owner, release contacts and trademark position. |
| Platform independence | Partial | [Technical architecture](technical-architecture.md), [open standards mapping](open-standards.md) | Document a tested open deployment path and all mandatory dependencies. |
| Documentation | Ready foundation | README, user guide, developer guide, API, deployment, security and test documentation | Keep all evidence current for the submitted release. |
| Non-PII data extraction | Evidence foundation | [Non-PII export/import guide](non-pii-data.md), [redacted sample export](evidence/non-pii-sample-export.json) | Attach deployment scope-control test results and approved minimum-cell threshold. |
| Privacy and applicable laws | Implementation baseline | [Privacy guide](privacy.md), [legal/privacy template](legal-privacy.md) | Fill jurisdiction, contacts and retention periods; obtain formal legal approval. |
| Open standards and best practices | Partial | [Open standards mapping](open-standards.md), API and WCAG guides | Publish OpenAPI and JSON Schema; retain accessibility test evidence. |
| Data privacy, security and do no harm | Partial | [Security guide](security.md), [test plan](test-plan.md), [release checklist](release-checklist.md) | Complete security retest, incident response, free-text moderation and remediation evidence. |

## Evidence pack

Use the following documents together when preparing a review:

- [SDG relevance and public-benefit evidence](sdg-mapping.md)
- [Open-source and DPG release status](open-source-dpg.md)
- [Governance and ownership](governance.md)
- [Technical architecture](technical-architecture.md)
- [Non-PII data export and import](non-pii-data.md)
- [Redacted sample export](evidence/non-pii-sample-export.json)
- [Open standards mapping](open-standards.md)
- [Privacy guide](privacy.md)
- [Legal and privacy confirmation template](legal-privacy.md)
- [Test plan](test-plan.md)
- [Release checklist](release-checklist.md)
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
## Automated documentation checks

Run the baseline evidence check from the project root:

```text
php tools/dpg_readiness_check.php
```

The command verifies that the DPG evidence documents are present. It does not replace jurisdictional legal review, security retesting, accessibility testing, or publication of measurable impact evidence.

The check also reads `docs/sdg-mapping.md` and reports the declared SDG categories (currently SDG 3, Good Health and Well-being, and SDG 10, Reduced Inequalities), along with whether measurable indicators, implementation evidence, non-identifying reporting, and evidence limitations are documented. Abhipraya is mapped as SDG-supporting evidence infrastructure; it is not itself an SDG certification or a claim that national targets have been achieved.

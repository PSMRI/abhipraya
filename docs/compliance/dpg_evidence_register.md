# DPG evidence register

## How to use this register

This is the documentation-only evidence pack for Abhipraya. It is not a Digital Public Good certification and must be updated for every public release. The Digital Public Goods Alliance reviews the core solution submitted to it; local implementations require their own legal, privacy and operational review.

| DPGA indicator | Current readiness | Evidence in this documentation | Remaining release evidence |
| --- | --- | --- | --- |
| 1. SDG relevance | Partial | [SDG mapping](sdg_mapping.md), product overview and use cases | Deployment-neutral public-benefit statement plus measured implementation evidence. |
| 2. Open licensing | Foundation | [Open-source and DPG release status](open_source_dpg_release_status.md), repository licence | Confirm the released repository contains the licence, notices and third-party attribution. |
| 3. Clear ownership | Partial | [Governance and ownership](governance_and_ownership.md) | Publish named legal owner, maintainers, trademark position and release contacts. |
| 4. Platform independence | Partial | Technical architecture, deployment guide and open standards mapping | Test and document a non-IIS/Linux deployment path or clearly state deployment dependencies and alternatives. |
| 5. Documentation | Ready foundation | README, user guide, developer guide, API, security, deployment and testing guides | Keep links accurate and publish documentation with every tagged release. |
| 6. Non-PII data extraction | Partial | [Non-PII export and import](non_pii_data_export_import.md), data dictionary | Demonstrate exports from a test deployment and retain a redacted sample. |
| 7. Privacy and applicable laws | Partial | [Privacy and data protection](privacy_data_protection.md), legal/privacy confirmation | Complete jurisdiction-specific review, notice, retention schedule and grievance contact. |
| 8. Open standards and best practices | Partial | [Open standards mapping](open_standards_mapping.md), API and WCAG guides | Publish OpenAPI and JSON Schema with the release; document supported browsers. |
| 9A. Data privacy and security | Partial | Security guide, test plan, backup and restore guide | Record production security testing, remediation and incident-response contacts. |
| 9B. Inappropriate or illegal content | Partial | Anonymous feedback design and moderation guidance in privacy/security documentation | Publish moderation, escalation and retention procedures for free-text feedback. |

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

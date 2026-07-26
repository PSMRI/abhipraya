# Open-source and Digital Public Good release status

## Current position

Abhipraya has an open-source release foundation: a GPL-3.0-or-later licence, public documentation, versioned configuration guidance and a security/deployment baseline. This is a **self-assessed readiness position**, not DPGA recognition.

## Release evidence status

| Area | Position | Release requirement |
| --- | --- | --- |
| Open licence | Foundation available | Retain GPL-3.0-or-later, copyright notices and third-party attribution. |
| Documentation | Foundation available | Publish the README, user, developer, API, deployment, privacy, security and DPG evidence pages with every release. |
| Privacy | Partial | Deployment owner completes the jurisdiction-specific notice, retention schedule and contact route. |
| Security | Partial | Complete current security testing, track findings and publish an incident-response contact. |
| Governance | Partial | Publish named maintainers, owner and release process. |
| Interoperability | Partial | Publish API and configuration schemas, supported export formats and compatibility commitments. |
| DPG evidence | Partial | Close the items in the [DPG evidence register](dpg_evidence_register.md). |

## Required public release artefacts

1. Source archive or repository tag, release notes and version number.
2. [GPL-3.0-or-later licence](../../LICENSE), [NOTICE](../../NOTICE), and [third-party notices](../../THIRD_PARTY_NOTICES.md).
3. [Code of Conduct](../../CODE_OF_CONDUCT.md), [Contributing guide](../../CONTRIBUTING.md), [Security policy](../../SECURITY.md), and [maintainer/release-contact template](../../MAINTAINERS.md).
4. Deployment-safe sample configuration with no credentials or production data, verified using the [public data audit](public_data_audit.md).
5. Public documentation and a working, scrollable documentation index.
6. [Licence consistency and attribution](license_consistency.md), [data privacy policy baseline](data_privacy_policy.md), and [open-source readiness checklist](open_source_readiness_checklist.md).
7. A completed [DPG release checklist](release_checklist.md).

## Licence boundary

GPL-3.0-or-later applies to the Abhipraya core source. Third-party components keep their own licences and attribution requirements. The release owner must verify that every bundled dependency is present in [THIRD_PARTY_NOTICES.md](../../THIRD_PARTY_NOTICES.md) and that its licence is compatible with the distributed release.

The GPL does not grant trademark rights to the Abhipraya name or logos. Publish the appropriate trademark owner/contact before a public branded release.

## SDG relevance

The first healthcare package is intended to support **SDG 3: Good Health and Well-being** by helping authorised officials identify service gaps from aggregated feedback and follow corrective actions. It can also support transparent and accountable public service delivery under SDG 16. See the [SDG mapping](sdg_mapping.md) for limitations and evidence requirements.

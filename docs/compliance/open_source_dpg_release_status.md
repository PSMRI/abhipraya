# Open-source and Digital Public Good release status

## Current position

Abhipraya has documented open-source and Digital Public Good release readiness: GPL-3.0-or-later licensing, named ownership, public documentation, versioned configuration guidance, privacy controls, security evidence, open standards and deployment guidance. The detailed evidence is maintained in the [DPG evidence register](dpg_evidence_register.md).

## Release evidence status

| Area | Position | Release requirement |
| --- | --- | --- |
| Open licence | Pass | GPL-3.0-or-later, copyright notice and third-party attribution are present in the repository. |
| Documentation | Pass | The README, user, developer, API, deployment, privacy, security, DPG evidence, VAPT and performance-report pages are published in the repository. |
| Privacy | Pass | Published Bihar privacy controls specify data minimisation, retention schedule, moderation responsibility and the public privacy/grievance contact. |
| Security | Pass | Completed development-environment evidence is recorded in the [passed-test evidence register](../testing/test_evidence_register.md) and [VAPT test report](../testing/vapt_test_report.md): protected-file checks, dependency audit, authorization, CSRF, SQL-injection input handling, encryption, session-store, Kafka, database backup/restore and index verification. The public incident-response contact is published in [SECURITY.md](../../SECURITY.md). The formal VAPT record remains the production-release assessment artefact. |
| Performance | Partial | The [performance test results](../testing/performance_test_results.md) record development baseline and load-smoke evidence. Approve targets and repeat in a representative environment before release. |
| Governance | Pass | PSMRI, India is the legal and trademark owner; the Abhipraya Team, public contacts and backup responsibilities are published in the [governance record](governance_and_ownership.md) and [maintainer record](../../MAINTAINERS.md). |
| Platform independence | Pass | The deployment guide supports Apache, Nginx, IIS and compatible reverse proxies; the repository includes a Linux/Nginx PHP-FPM bootstrap script and uses portable PHP and MySQL/MariaDB runtime requirements. |
| Interoperability | Pass | Published OpenAPI and Postman artefacts, versioned JSON configuration, standard CSV/XLSX exports and documented compatibility commitments provide the interoperable core path. |
| DPG evidence | Pass | All indicators in the [DPG evidence register](dpg_evidence_register.md) are recorded as Pass with linked supporting documentation. |

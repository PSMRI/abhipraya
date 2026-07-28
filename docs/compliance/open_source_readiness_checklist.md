# Open-source readiness checklist

Run `php tools/open_source_readiness_check.php` before each release. The command verifies that the minimum release-governance documents exist; the unchecked items below still require maintainer review.

- [ ] GPL-3.0-or-later `LICENSE` is present and linked from the README.
- [ ] `NOTICE`, `THIRD_PARTY_NOTICES.md`, `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md` and `MAINTAINERS.md` are current.
- [ ] Third-party components are inventoried and licences are compatible.
- [ ] No secrets, production data or personal data appear in the release.
- [ ] Sample configuration and surveys are safe to publish.
- [ ] Documentation links work from the HTML documentation sidebar.
- [ ] Version, changelog and release notes are present.
- [ ] Security, privacy, accessibility and export checks have been completed.
- [ ] DPG evidence register and release checklist are reviewed.

## Current repository baseline

The repository currently contains the GPL licence, security and contribution guidance, governance and open-standards documentation, DPG evidence documents, and this checklist. The automated check does not prove that third-party attribution, secrets scanning, dependency licensing, release checksums, or production security/accessibility testing are complete. Those items must be signed off for each published release.

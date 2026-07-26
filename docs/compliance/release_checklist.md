# Open-source and DPG release checklist

Use this checklist for each public Abhipraya release.

## Source and legal

- [ ] Version tag and release notes are prepared.
- [ ] GPL-3.0-or-later licence and copyright notices are present.
- [ ] Third-party components and licences are inventoried.
- [ ] No credentials, internal hostnames, production databases or personal data are included.
- [ ] Maintainers, security contact and governance information are current.

## Documentation and interoperability

- [ ] README links work from the HTML documentation page.
- [ ] User, developer, API, deployment and security guides match the release.
- [ ] Survey JSON Schema and sample packages are included or linked.
- [ ] API fields, error responses and export schemas are versioned.
- [ ] CSV/XLSX export is tested with multilingual data.

## Privacy and safety

- [ ] Privacy notice, retention schedule and contact route are approved for the deployment jurisdiction.
- [ ] Standard exports exclude IP addresses, device identifiers and precise coordinates.
- [ ] Free-text feedback moderation and escalation process is documented.
- [ ] Access scope checks are tested for state, district and facility users.
- [ ] Backup, restore and incident-response procedures are current.

## Quality and accessibility

- [ ] PHP lint and route smoke tests pass.
- [ ] Dashboard, Analytics and Reports return consistent ratings for the same filters.
- [ ] Keyboard, focus, screen-reader labels and colour contrast are tested.
- [ ] Public survey works on supported mobile browsers and weak-network conditions.
- [ ] Security findings are tracked to remediation or accepted risk.

## DPGA evidence pack

- [ ] SDG mapping is updated.
- [ ] DPG evidence register reflects the release status.
- [ ] Evidence links are accessible to a reviewer without internal access.
- [ ] Any claims are labelled as self-assessment until DPGA review is complete.

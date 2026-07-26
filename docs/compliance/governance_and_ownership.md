# Governance and ownership

## Maintainer responsibilities

The designated Abhipraya maintainer group is responsible for:

- Reviewing contributions and releases.
- Approving survey configuration and version publication.
- Maintaining security and privacy documentation.
- Coordinating production releases, migrations, and rollback decisions.
- Publishing ownership, trademark, support, and release-contact details before an external open-source release.

## Change governance

| Change type | Required control |
| --- | --- |
| Survey wording, options, report type, or scale | New version, validation, review, and schema hash. |
| Database migration | Backup, UAT validation, approved release window, and rollback plan. |
| Security control | Code review, testing, and documented deployment verification. |
| Role/scope behaviour | Server-side authorization test for each impacted role. |
| Public documentation | Update README, relevant guide, and GitBook navigation in the same release. |

## Community standards

See the root [Contributing guide](../../CONTRIBUTING.md), [Security policy](../../SECURITY.md), and [License](../../LICENSE). Before public release, publish a maintainers and release-contacts page with the current responsible organisation and contact method.

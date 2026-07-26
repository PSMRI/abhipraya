# Third-party notices

This file records third-party software distributed with, or loaded by, the Abhipraya core release. It must be reviewed whenever a dependency is added, upgraded or removed.

| Component | Use in Abhipraya | Licence | Notice/action |
| --- | --- | --- | --- |
| Bootstrap Icons | Bundled icon font and CSS under `ui/assets/vendor/bootstrap-icons/` | MIT | Full MIT text is included in `LICENSES/BOOTSTRAP_ICONS-MIT.txt`; retain it with the distributed files. |
| PHP | Server runtime | PHP License | Runtime supplied by the deployment environment; not redistributed as Abhipraya source. |
| MySQL/MariaDB | Optional data store | Deployment-provided | Confirm the selected server's licence and notices separately. |

## Review rules

1. Do not add a library until its licence is recorded here and is compatible with GPL-3.0-or-later.
2. Preserve bundled copyright, licence and attribution files.
3. Record the installed version, source URL and any modified third-party file in the release record.
4. Do not include development-only folders such as `node_modules` in a public source release unless their licences have been inventoried for that distribution.

## Bootstrap Icons licence

Bootstrap Icons is distributed under the MIT License. The MIT text is bundled in `LICENSES/BOOTSTRAP_ICONS-MIT.txt`. Confirm the version and upstream attribution before producing a release archive.

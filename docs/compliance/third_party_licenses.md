# Third-party licences

## Authoritative inventory

The release inventory is maintained in the root [THIRD_PARTY_NOTICES.md](../../THIRD_PARTY_NOTICES.md). It is the authoritative list of bundled and Composer-managed third-party components, their licences, and required attribution.

## Current Abhipraya baseline

The current source release uses locally bundled Bootstrap Icons and Composer-managed QR-code dependencies. Their versions, licences, and attribution are recorded in the authoritative inventory. Deployment-provided services such as PHP, MySQL/MariaDB, Memurai/Redis, web servers, and optional Kafka brokers are not bundled with the source release; the deployment owner must review their selected distribution licences separately.

## Release review

Before publishing an Abhipraya release:

1. Compare `composer.lock` and bundled assets with the inventory.
2. Add every new, upgraded, copied, or modified component with version, source, licence, and attribution requirement.
3. Confirm that required upstream licence texts are included in the release archive.
4. Verify compatibility with the core GPL-3.0-or-later licence.
5. Record the reviewer, release tag, and any licence decision in the release evidence record.

Do not treat infrastructure services such as PHP, MySQL/MariaDB, reverse proxies, or managed hosting as bundled software unless the release archive actually redistributes them. Their deployment licences remain the deploying organisation's responsibility.

See [licence consistency and attribution](license_consistency.md) and [licence consistency before and after release](license_consistency_before_after.md).

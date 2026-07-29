# Licence consistency before and after release

## Purpose

Use this review to ensure that the public Abhipraya source release remains consistently licensed before and after a change, dependency update, or release tag.

| Check | Before change or release | After change or release |
| --- | --- | --- |
| Core licence | Confirm [LICENSE](../../LICENSE) is the unmodified GPL-3.0-or-later text. | Confirm the released archive contains the same licence file. |
| Package metadata | Confirm `composer.json` declares `GPL-3.0-or-later`. | Run Composer validation and confirm the declared licence is unchanged. |
| Public description | Confirm README links to the licence, [NOTICE](../../NOTICE), and [third-party notices](../../THIRD_PARTY_NOTICES.md). | Check links in the tagged documentation site and release archive. |
| Dependencies | Record every bundled or Composer dependency and its licence. | Update [third-party licences](third_party_licenses.md) and retain required upstream notices. |
| Contributions | Confirm contribution terms are stated in [CONTRIBUTING.md](../../CONTRIBUTING.md). | Confirm external contributions have been reviewed under those terms. |
| Name and logos | Confirm trademark ownership/contact is not implied by the GPL. | Publish the approved trademark position with the release contacts. |

## Evidence record

For each release, retain the tag, reviewer, review date, changed dependencies, attribution changes, and a link to the release archive. A licence review does not replace legal advice for a local deployment.

See [licence consistency and attribution](license_consistency.md) for the authoritative requirements.

# Open-source release and DPG evidence

Abhipraya is released under **GPL-3.0-only**. Every release must include the licence, copyright notices, source code, dependency notices, security contact and a versioned changelog.

## Release evidence checklist

- [ ] `LICENSE` and copyright notices present;
- [ ] third-party assets and licences listed;
- [ ] source, migrations and configuration examples included;
- [ ] release tag, checksum and changelog published;
- [ ] security reporting contact and support window published;
- [ ] deployment can be reproduced from documented open dependencies.
## Automated open-source release check

Before tagging a release, run:

```text
php tools/open_source_readiness_check.php
```

The check confirms that the license, security, contribution, governance, standards, and readiness documents are present. Maintainers must still review third-party notices, dependency licenses, release artifacts, and the final repository contents.

The open-source readiness decision is therefore **documentation baseline passed; release approval pending maintainer evidence**. Before publication, attach the release tag, changelog, dependency/third-party inventory, secret scan result, production-data review, checksum, and security/accessibility test evidence to the release record.

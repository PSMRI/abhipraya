# Public data audit

## Objective

Ensure that a public Abhipraya release, documentation site and sample data package contain no credentials, production records or personally identifiable information.

## Audit scope

- Source code, configuration samples and `.env.example` files.
- Documentation, images, screenshots, CSV/XLSX samples and API examples.
- Git history and release archives.
- Bundled survey JSON packages and QR examples.

## Required checks

1. No usernames/passwords, API keys, access tokens, database dumps or internal hostnames.
2. No beneficiary names, phone numbers, Aadhaar identifiers, medical information, IP addresses, device identifiers or precise GPS coordinates.
3. No QR links that point to a production facility unless that organisation has authorised publication.
4. No screenshots containing user profiles, browser tokens or real feedback text with identifying details.
5. Sample data is synthetic, labelled as such and reviewed before publication.

## Sign-off record

For each release, retain the reviewer, date, repository tag, audit scope, findings and remediation status in the release record. Do not mark the DPG evidence criterion as ready until this audit is complete.

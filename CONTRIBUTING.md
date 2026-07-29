# Contributing to Abhipraya

Thank you for improving Abhipraya. Open an issue before significant changes, keep pull requests focused, and include tests or validation notes. Do not include credentials, production data, or personal data in issues, commits, screenshots, or pull requests.

## Before you start

- Read [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) and [SECURITY.md](SECURITY.md).
- Use `.env.example` or deployment-safe configuration; never commit a real `.env` file.
- Review the relevant developer, API, database, deployment, and compliance documentation before changing a public workflow.

## Change workflow

1. Create a focused branch and keep one pull request to one related purpose.
2. Describe the user-facing impact, API/database/configuration impact, and validation performed.
3. Use existing services, repositories, response handling, and UI components where they fit; do not introduce raw request-built SQL.
4. For database changes, add an idempotent migration where appropriate and document backup, rollback, and fresh-setup impact.
5. For API changes, update the OpenAPI definition and Postman collection. For public documentation, update `docs/SUMMARY.md`.

## Testing and review

- Run PHP lint for changed PHP files and the relevant documented smoke tests.
- Test changed role/scope, CSRF/session, validation, error-handling, and accessibility behaviour where applicable.
- Update test results when a change affects documented behaviour.
- Update third-party attribution before adding, changing, or bundling a dependency.
- Changes affecting authentication, encryption, migrations, public exports, or privacy-sensitive data require focused maintainer review.

All contributions are licensed under GPL-3.0-or-later.

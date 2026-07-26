# Backup and restore guide

## Backup scope

Back up all of the following:

- MySQL database, including responses, CAPA, users, profile-security, and login-attempt records.
- `api/masters/` survey manifests and versioned survey JSON packages.
- Environment-specific configuration stored securely outside the repository.
- Generated QR poster or export storage when operationally required.
- IIS site configuration and TLS certificate-management records.

## Backup schedule

Use an approved schedule appropriate to programme needs. At minimum, take a verified backup before database migrations, configuration publication, or production release.

## Restore process

1. Declare an incident and stop write operations if necessary.
2. Identify the approved recovery point.
3. Restore the database to an isolated verification environment first.
4. Restore matching versioned survey JSON configuration.
5. Validate response count, survey versions, administrator sign-in, and QR survey resolution.
6. Obtain approval before switching production traffic.
7. Record the incident, recovery point, actions, and verification results.

Never restore only a newer database with an older survey configuration without checking survey version and schema hash compatibility.

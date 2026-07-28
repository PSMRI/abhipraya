# Encryption and cryptographic protection

## Overview

Abhipraya uses several layers of cryptographic protection. These controls protect data in transit, administrator credentials, sensitive administrator-profile fields, sessions, and application secrets. Encryption does not replace access control: APIs must still enforce authentication, role permission, facility scope, CSRF protection, and input validation.

| Protection area | Current control | Purpose |
| --- | --- | --- |
| Data in transit | HTTPS/TLS in production | Protect browser-to-server API traffic from interception or modification. |
| Administrator passwords | PHP password hashing and verification | Store password verifiers instead of reversible password values. |
| Administrator profile fields | AES-256-GCM field encryption | Protect stored profile names, email, mobile, and job-title values. |
| Login password transport | RSA 2048-bit OAEP support | Supports encrypted login-password transport where the client integration uses it. |
| Session and CSRF protection | Secure session cookies and random CSRF tokens | Protect authenticated browser requests from session/forgery attacks. |
| Secret management | Environment-provided encryption key | Keeps the field-encryption key outside application source and public routes. |

## Data in transit

All production browser and API traffic must use HTTPS. TLS termination may occur at the selected web server, reverse proxy, or load balancer, but plaintext traffic must not be exposed to public clients. Configure HTTP-to-HTTPS redirection, valid certificates, and current TLS policies at the deployment edge.

API clients should send JSON only over HTTPS. Never place passwords, session tokens, encryption keys, or sensitive personal information in URLs, browser logs, or unprotected client storage.

## Password protection

Administrator passwords are protected with PHP's password hashing functions. During login or password change, the server verifies the supplied password against the stored hash rather than decrypting a stored password value.

Passwords must not be included in API responses, logs, exports, audit-event payloads, or documentation examples.

## Encrypted administrator profile fields

The secure profile store uses encrypted columns for first name, middle name, last name, email, mobile number, and job title. Abhipraya's `Crypto` helper uses AES-256-GCM with a new random 12-byte IV and a 16-byte authentication tag for each encrypted value.

Encrypted values use a versioned `enc:v1:` format. The authentication tag detects tampering; a value that cannot be authenticated must not be trusted as a valid decrypted value.

The production field-encryption key is supplied through `ABHIPRAYA_FIELD_ENCRYPTION_KEY`. Keep this key in the protected deployment environment, separate from source code and database backups. Rotate it only through a planned migration/re-encryption procedure; changing the key without re-encrypting existing values makes those values unreadable.

> **Production requirement:** OpenSSL support must be available for AES-256-GCM. The application contains a compatibility fallback for legacy/runtime resilience, but it must not be selected as the production cryptographic standard.

### Verify OpenSSL before deployment

Developers and deployers should run this check from the same PHP installation that will serve Abhipraya:

```text
php -m | findstr openssl
```

On Linux or macOS, use:

```text
php -m | grep -i openssl
```

Expected result:

```text
openssl
```

If no result is returned, enable the PHP OpenSSL extension in the active PHP configuration, restart or reload the selected PHP/web-server process, and run the check again. Do not deploy encrypted profile functionality to production until this check passes.

The following command can also confirm that the OpenSSL functions required by the application are available:

```text
php -r "echo function_exists('openssl_encrypt') && function_exists('openssl_decrypt') ? 'OpenSSL ready' : 'OpenSSL missing';"
```

## Login password transport

Abhipraya contains RSA 2048-bit OAEP password-transport support. The server can generate and protect a key pair, expose only the public key to a client, and decrypt an encrypted password server-side before standard password verification.

This mechanism is supplementary to HTTPS, not a replacement for it. Deployments using it must protect the private key directory from web access, backups, and unauthorised operating-system users.

## Key and secret management

- Store `ABHIPRAYA_FIELD_ENCRYPTION_KEY`, database credentials, and private keys outside the repository and public web root.
- Restrict read access to the application process identity and authorised operations staff.
- Never commit `.env`, private-key files, backup archives, or decrypted production data.
- Use separate secrets for development, UAT, and production.
- Record key ownership, rotation procedure, recovery process, and access reviews in deployment operations documentation.
- Treat key loss or unauthorised key disclosure as a security incident.

## API implementation rules

1. Require HTTPS for production API access.
2. Accept sensitive values only in protected request bodies, never query strings.
3. Avoid returning encrypted ciphertext unless a documented administrative export need has been approved.
4. Decrypt sensitive fields only inside server-side code that has already passed authentication and scope checks.
5. Log metadata and safe error identifiers, not plaintext personal data, passwords, tokens, or keys.
6. Review cryptographic code and dependency/runtime updates before release.

## Related documentation

- [API access control](access_control.md)
- [API specifications](api_specifications.md)
- [Security guide](../security.md)
- [Privacy and data protection](../compliance/privacy_data_protection.md)

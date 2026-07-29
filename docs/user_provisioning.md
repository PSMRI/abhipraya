# Administrator and service-location user provisioning

## Before creating an account

Abhipraya currently provisions administrator accounts directly in `user_master`. Only authorised database administrators should do this. Confirm the target role in `api/masters/rolecode.json` and, for a service-location user, confirm the `facilityNIN` exists in `api/masters/facilityCodes.json`.

Never store a plaintext password in SQL, a migration file, a ticket, or source control. Generate a PHP password hash with the same PHP runtime used by the application:

```text
php -r "echo password_hash('<temporary-password>', PASSWORD_DEFAULT), PHP_EOL;"
```

Treat the generated value as a secret. Replace `<temporary-password>` before executing the command, avoid sharing shell history, and require the user to change it on first sign-in through the approved operational process.

## Create a system administrator

Use role ID `1` for the current **Super Administrator** role. A system administrator is not bound to one service location, so `NIN_fk` is `NULL` and `u_identity` is `0`.

```sql
INSERT INTO user_master
    (u_name, u_pass, active, u_role, NIN_fk, u_identity)
VALUES
    ('<admin-username>', '<password-hash>', 1, 1, NULL, 0);
```

## Create a service-location user

Use role ID `2` for the current **Facility Administrator** role. Set `NIN_fk` to the approved service-location identifier. In the current healthcare configuration, this is the `facilityNIN`. Set `u_identity` to the approved department/service-area identifier, or `0` when no single department restriction applies.

```sql
INSERT INTO user_master
    (u_name, u_pass, active, u_role, NIN_fk, u_identity)
VALUES
    ('<location-admin-username>', '<password-hash>', 1, 2, '<facilityNIN>', 0);
```

Example scope verification before creating the user:

```text
Search api/masters/facilityCodes.json for the approved facilityNIN.
Search api/masters/rolecode.json for role_id 2.
```

## Verify and hand over

1. Confirm the account record without selecting or displaying `u_pass`.
2. Sign in with the new account and verify its displayed role and permitted service-location data.
3. Confirm it cannot access another service location or a system-administrator-only function.
4. Record the account owner, approver, role, scope, creation time, and secure handover method in the organisation's access register.
5. Disable an account by setting `active = 0`; do not delete an account that may be needed for audit history.

```sql
UPDATE user_master
SET active = 0
WHERE u_name = '<username>';
```

## Security rules

- Do not create accounts with a shared username or shared password.
- Do not use role IDs from memory; read the active `rolecode.json` configuration.
- Assign only the minimum role and service-location scope required.
- Review accounts regularly and disable access promptly when responsibilities change.
- Do not add personal profile values directly to legacy user fields. Use the authenticated profile workflow, which stores protected values in `user_profile_secure`.

## Related documentation

- [Access control](api/access_control.md)
- [User session management](api/user_session_management.md)
- [Data dictionary and ER overview](database/data_dictionary_erd.md)
- [First 30 minutes for a new developer](developer_first_30_minutes.md)

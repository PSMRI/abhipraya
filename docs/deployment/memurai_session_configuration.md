# Memurai session configuration

## Purpose

Use Memurai as the shared, Redis-compatible store for authenticated Abhipraya administrator sessions. It lets multiple PHP/web-server instances validate the same user session. Public survey submissions do not require an administrator session.

The browser stores only the opaque `ABHIPRAYA_SESSION` cookie. Session identity, role, service-location scope, CSRF token, and expiry state stay in Memurai.

## Development setup

### 1. Start a development Memurai instance

Install and start Memurai using the organisation-approved Windows installation method. Keep the development instance on the developer machine. Do not use a shared production Memurai instance from a development workstation.

Verify that the service responds:

```powershell
memurai-cli.exe ping
```

Expected output:

```text
PONG
```

If authentication is configured, use the protected password mechanism provided by your installed Memurai CLI rather than saving a password in command history.

### 2. Enable the PHP Redis extension

Enable the Redis extension in the `php.ini` used by the web server. The exact extension line depends on the installed PHP build.

```ini
extension=redis
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?database=0"
```

For a password-protected development instance, obtain the value from development secret management and use:

```ini
session.save_path = "tcp://127.0.0.1:6379?auth=<memurai-password>&database=0"
```

Reload the PHP/web-server process, then verify the runtime:

```powershell
php -m | findstr redis
php --ri redis
```

### 3. Configure Abhipraya

Add this to the development-environment `.env` file. Do not commit `.env`.

```ini
ABHIPRAYA_SESSION_HANDLER=redis
```

Sign in to Abhipraya, open an authenticated page, and sign out. This confirms session creation, reuse, and deletion. See [User session management](../api/user_session_management.md) for expected browser/API behaviour.

For a development or single-server setup without Memurai, explicitly set `ABHIPRAYA_SESSION_HANDLER=files`.

## Production setup

### 1. Network and host security

- Run Memurai on a protected application-data network; never expose its port to the public internet.
- Permit connections only from the approved PHP/web-server hosts.
- Use a strong secret from the deployment secret store; do not place it in the repository, browser code, or screenshots.
- Use TLS where Memurai traffic crosses a network boundary; a loopback-only local connection can use the host's local protection model.
- Run the supported production edition and arrange monitoring, patching, backups, and incident ownership.

### 2. Configure Memurai

Use the organisation-approved Memurai configuration file. The following are reference settings; review them with the infrastructure owner before applying them:

```text
bind <private-memurai-address>
protected-mode yes
port 6379
requirepass <secret-from-secret-store>
maxmemory <approved-memory-limit>
maxmemory-policy noeviction
```

`noeviction` is appropriate for sessions: an eviction should not silently remove an active administrator session. Capacity alerts should be raised before the memory limit is reached. Configure the selected persistence, replica, Sentinel, or cluster design according to the deployment availability requirement.

### 3. Configure every PHP/web-server node

Every application node must use the same Memurai endpoint and compatible PHP Redis extension. Configure each node's protected PHP configuration:

```ini
extension=redis
session.save_handler = redis
session.save_path = "tcp://<memurai-private-host>:6379?auth=<secret>&database=0"
```

Then set the same environment value on each node:

```ini
ABHIPRAYA_SESSION_HANDLER=redis
```

Reload PHP/web-server processes one node at a time when using a load-balanced service. Preserve at least one healthy application node during the rollout.

### 4. Production verification

1. Confirm the Memurai endpoint is reachable only from application hosts.
2. Confirm `php -m` reports `redis` in the web-server PHP runtime.
3. Sign in through node A, then make a protected request through node B.
4. Confirm the request remains authenticated and scope-restricted.
5. Confirm logout invalidates the session across nodes.
6. Confirm a session expires after 30 minutes of inactivity.
7. Test the documented Memurai outage and recovery procedure before go-live.

## Operations and rollback

Monitor Memurai availability, used memory, rejected connections, authentication failures, and the PHP session error log. An unavailable shared session store can prevent administrators from signing in or continuing a session; treat it as an authentication dependency.

To roll back to file sessions for a single-node emergency configuration, set `ABHIPRAYA_SESSION_HANDLER=files` and reload PHP. Existing Memurai sessions will no longer be recognised, so administrators must sign in again. Do not use this rollback for a multi-node deployment because each node would hold different sessions.

## Related documentation

- [Developer guide](../developer-guide.md)
- [User session management](../api/user_session_management.md)
- [Deployment guide](deployment_guide.md)
- [Access control](../api/access_control.md)

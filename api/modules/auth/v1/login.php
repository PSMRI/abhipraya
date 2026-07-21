<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';

Security::requireMethod('POST');

const LOGIN_MAX_FAILED_ATTEMPTS = 5;
const LOGIN_LOCK_MINUTES = 15;

function loginAttemptKeys(string $username): array
{
    return [
        hash('sha256', strtolower(trim($username))),
        hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
    ];
}

function enforceLoginLock(mysqli $con, string $username): void
{
    $sessionFailures = (int) ($_SESSION['abhipraya_login_failed_attempts'] ?? 0);
    $sessionLockUntil = (int) ($_SESSION['abhipraya_login_locked_until'] ?? 0);
    if ($sessionFailures >= LOGIN_MAX_FAILED_ATTEMPTS && $sessionLockUntil > time()) {
        Response::error('Too many failed sign-in attempts. Try again in 15 minutes.', null, 429);
    }
    [$usernameHash, $ipHash] = loginAttemptKeys($username);
    $statement = $con->prepare(
        'SELECT locked_until
         FROM auth_login_attempts
         WHERE (username_hash = ? OR ip_hash = ?)
           AND locked_until IS NOT NULL
         ORDER BY locked_until DESC
         LIMIT 1'
    );
    if ($statement === false) {
        Response::serverError('Login security controls are unavailable. Apply the login rate-limit migration.');
    }
    $statement->bind_param('ss', $usernameHash, $ipHash);
    $statement->execute();
    $attempt = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();

    if ($attempt !== null && !empty($attempt['locked_until']) && strtotime((string) $attempt['locked_until']) > time()) {
        Response::error('Too many failed sign-in attempts. Try again in 15 minutes.', null, 429);
    }
}

function recordFailedLogin(mysqli $con, string $username): void
{
    $sessionFailures = (int) ($_SESSION['abhipraya_login_failed_attempts'] ?? 0) + 1;
    $_SESSION['abhipraya_login_failed_attempts'] = $sessionFailures;
    if ($sessionFailures >= LOGIN_MAX_FAILED_ATTEMPTS) {
        $_SESSION['abhipraya_login_locked_until'] = time() + (LOGIN_LOCK_MINUTES * 60);
    }
    [$usernameHash, $ipHash] = loginAttemptKeys($username);
    $statement = $con->prepare(
        'INSERT INTO auth_login_attempts (username_hash, ip_hash, failed_attempts, locked_until, last_attempt_at)
         VALUES (?, ?, 1, NULL, NOW())
         ON DUPLICATE KEY UPDATE
           failed_attempts = failed_attempts + 1,
           locked_until = IF(failed_attempts >= ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), locked_until),
           last_attempt_at = NOW()'
    );
    if ($statement === false) {
        Response::serverError('Login security controls are unavailable. Apply the login rate-limit migration.');
    }
    $maxAttempts = LOGIN_MAX_FAILED_ATTEMPTS;
    $statement->bind_param('ssi', $usernameHash, $ipHash, $maxAttempts);
    if (!$statement->execute()) {
        $message = $statement->error;
        $statement->close();
        Response::serverError('Login security controls are unavailable: ' . $message);
    }
    $statement->close();

    /* Do the threshold update explicitly; this is reliable across MySQL
       versions whose ON DUPLICATE KEY assignment ordering differs. */
    $check = $con->prepare('SELECT failed_attempts FROM auth_login_attempts WHERE username_hash = ? AND ip_hash = ? LIMIT 1');
    if ($check !== false) {
        $check->bind_param('ss', $usernameHash, $ipHash);
        $check->execute();
        $row = $check->get_result()->fetch_assoc() ?: [];
        $check->close();
        if ((int) ($row['failed_attempts'] ?? 0) >= LOGIN_MAX_FAILED_ATTEMPTS) {
            $lock = $con->prepare('UPDATE auth_login_attempts SET locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE username_hash = ? AND ip_hash = ?');
            if ($lock !== false) {
                $lock->bind_param('ss', $usernameHash, $ipHash);
                $lock->execute();
                $lock->close();
            }
        }
    }

    /* Enforce a username-wide threshold as well, so changing client/IP
       metadata cannot avoid the account lockout. */
    $total = $con->prepare('SELECT COALESCE(SUM(failed_attempts), 0) AS total_attempts FROM auth_login_attempts WHERE username_hash = ?');
    if ($total !== false) {
        $total->bind_param('s', $usernameHash);
        $total->execute();
        $sum = $total->get_result()->fetch_assoc() ?: [];
        $total->close();
        if ((int) ($sum['total_attempts'] ?? 0) >= LOGIN_MAX_FAILED_ATTEMPTS) {
            $lockUser = $con->prepare('UPDATE auth_login_attempts SET locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE username_hash = ?');
            if ($lockUser !== false) {
                $lockUser->bind_param('s', $usernameHash);
                $lockUser->execute();
                $lockUser->close();
            }
        }
    }
}

function clearFailedLogins(mysqli $con, string $username): void
{
    unset($_SESSION['abhipraya_login_failed_attempts'], $_SESSION['abhipraya_login_locked_until']);
    [$usernameHash, $ipHash] = loginAttemptKeys($username);
    $statement = $con->prepare('DELETE FROM auth_login_attempts WHERE username_hash = ? AND ip_hash = ?');
    if ($statement !== false) {
        $statement->bind_param('ss', $usernameHash, $ipHash);
        $statement->execute();
        $statement->close();
    }
}

try {
    $request = Security::jsonInput();
    Security::requireFields($request, ['username', 'password', 'captcha']);

    $captcha = trim((string) $request['captcha']);
    $expectedCaptcha = (string) ($_SESSION['abhipraya_login_captcha'] ?? '');
    $captchaExpires = (int) ($_SESSION['abhipraya_login_captcha_expires'] ?? 0);
    unset($_SESSION['abhipraya_login_captcha'], $_SESSION['abhipraya_login_captcha_expires']);

    if ($expectedCaptcha === '' || $captchaExpires < time() || !hash_equals($expectedCaptcha, $captcha)) {
        Response::validation(['captcha' => 'Invalid or expired verification answer.']);
    }

    $username = trim((string) $request['username']);
    $password = (string) $request['password'];
    enforceLoginLock($con, $username);
    $statement = $con->prepare(
        'SELECT u_id, u_name, u_pass, NIN_fk, active, u_role, u_identity
         FROM user_master
         WHERE u_name = ?
         LIMIT 1'
    );

    if ($statement === false) {
        throw new RuntimeException('Unable to prepare login query.');
    }

    $statement->bind_param('s', $username);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();

    $storedPassword = (string) ($user['u_pass'] ?? '');
    $isHash = password_get_info($storedPassword)['algo'] !== null;
    $passwordValid = $user !== null && ($isHash ? password_verify($password, $storedPassword) : hash_equals($storedPassword, $password));

    if (!$passwordValid || (int) ($user['active'] ?? 0) !== 1) {
        recordFailedLogin($con, $username);
        usleep(random_int(150000, 250000));
        /* Return the lock response on the threshold attempt itself. */
        enforceLoginLock($con, $username);
        Response::error('Invalid username or password.', null, 401);
    }

    clearFailedLogins($con, $username);

    /* Transparently upgrade legacy plaintext passwords after a valid login. */
    if (!$isHash) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upgrade = $con->prepare('UPDATE user_master SET u_pass = ? WHERE u_id = ?');
        $userId = (int) $user['u_id'];
        $upgrade->bind_param('si', $hash, $userId);
        $upgrade->execute();
        $upgrade->close();
    }

    SessionManager::login([
        'u_id' => (int) $user['u_id'],
        'u_name' => (string) $user['u_name'],
        'role_id' => (int) $user['u_role'],
        'fac_id' => (int) $user['NIN_fk'],
        'dept_id' => (int) $user['u_identity'],
    ]);

    Response::success('Login successful', [
        'user' => SessionManager::user(),
        'csrf_token' => Csrf::regenerate(),
    ]);
} catch (Throwable $exception) {
    Response::serverError($exception->getMessage());
}

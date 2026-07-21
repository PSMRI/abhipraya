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
    [$usernameHash, $ipHash] = loginAttemptKeys($username);
    $statement = $con->prepare('SELECT locked_until FROM auth_login_attempts WHERE username_hash = ? AND ip_hash = ? LIMIT 1');
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
    [$usernameHash, $ipHash] = loginAttemptKeys($username);
    $statement = $con->prepare(
        'INSERT INTO auth_login_attempts (username_hash, ip_hash, failed_attempts, locked_until, last_attempt_at)
         VALUES (?, ?, 1, NULL, NOW())
         ON DUPLICATE KEY UPDATE
           failed_attempts = failed_attempts + 1,
           locked_until = IF(failed_attempts + 1 >= ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL),
           last_attempt_at = NOW()'
    );
    if ($statement === false) {
        Response::serverError('Login security controls are unavailable. Apply the login rate-limit migration.');
    }
    $maxAttempts = LOGIN_MAX_FAILED_ATTEMPTS;
    $statement->bind_param('ssi', $usernameHash, $ipHash, $maxAttempts);
    $statement->execute();
    $statement->close();
}

function clearFailedLogins(mysqli $con, string $username): void
{
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

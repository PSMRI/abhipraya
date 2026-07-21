<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';

Security::requireMethod('POST');

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
        usleep(random_int(150000, 250000));
        Response::error('Invalid username or password.', null, 401);
    }

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

<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';

Security::requireMethod('POST');
SessionManager::requireLogin();
Csrf::validate();

$payload = Security::jsonInput();
Security::requireFields($payload, ['current_password', 'new_password', 'confirm_password']);

if ((string) $payload['new_password'] !== (string) $payload['confirm_password']) {
    Response::validation(['confirm_password' => 'New passwords do not match.']);
}
if (!preg_match('/^(?=.{8,128}$)(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9\s])\S+$/', (string) $payload['new_password'])) {
    Response::validation(['new_password' => 'Use 8–128 characters with an uppercase letter, lowercase letter, number and special character. Spaces are not allowed.']);
}
if (hash_equals((string) $payload['current_password'], (string) $payload['new_password'])) {
    Response::validation(['new_password' => 'Choose a password different from your current password.']);
}

try {
    $statement = $con->prepare('SELECT u_pass FROM user_master WHERE u_id = ? LIMIT 1');
    $userId = SessionManager::userId();
    $statement->bind_param('i', $userId);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc() ?: null;
    $statement->close();

    $stored = (string) ($user['u_pass'] ?? '');
    $current = (string) $payload['current_password'];
    $valid = password_get_info($stored)['algo'] !== null ? password_verify($current, $stored) : hash_equals($stored, $current);
    if (!$valid) {
        Response::error('Your current password is incorrect.', null, 401);
    }

    $hash = password_hash((string) $payload['new_password'], PASSWORD_DEFAULT);
    $statement = $con->prepare('UPDATE user_master SET u_pass = ? WHERE u_id = ?');
    $statement->bind_param('si', $hash, $userId);
    $statement->execute();
    $statement->close();
    Response::success('Password changed successfully.');
} catch (Throwable $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'auth.change-password', 'user_id' => SessionManager::userId()]);
    Response::serverError();
}

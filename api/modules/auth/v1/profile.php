<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';
require_once dirname(__DIR__, 3) . '/core/Crypto.php';

SessionManager::requireLogin();

function profileValue(array $payload, string $key, int $maxLength): string
{
    $value = trim((string) ($payload[$key] ?? ''));
    if (mb_strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
        Response::validation([$key => 'Enter a valid value.']);
    }
    return $value;
}

function loadSecureProfile(mysqli $con, int $userId): array
{
    $statement = $con->prepare(
        'SELECT u.u_name, p.first_name_encrypted, p.middle_name_encrypted,
                p.last_name_encrypted, p.email_encrypted, p.mobile_encrypted,
                p.job_title_encrypted
         FROM user_master u
         LEFT JOIN user_profile_secure p ON p.user_id = u.u_id
         WHERE u.u_id = ? LIMIT 1'
    );
    if ($statement === false) {
        Response::error('Profile storage is not initialized. Apply the secure-profile migration first.', null, 503);
    }
    $statement->bind_param('i', $userId);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc() ?: [];
    $statement->close();

    return [
        'username' => (string) ($row['u_name'] ?? ''),
        'first_name' => Crypto::decrypt($row['first_name_encrypted'] ?? ''),
        'middle_name' => Crypto::decrypt($row['middle_name_encrypted'] ?? ''),
        'last_name' => Crypto::decrypt($row['last_name_encrypted'] ?? ''),
        'email' => Crypto::decrypt($row['email_encrypted'] ?? ''),
        'mobile' => Crypto::decrypt($row['mobile_encrypted'] ?? ''),
        'job_title' => Crypto::decrypt($row['job_title_encrypted'] ?? ''),
    ];
}

try {
    $userId = SessionManager::userId();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        Response::success('Profile loaded.', ['profile' => loadSecureProfile($con, $userId)]);
    }

    Security::requireMethod('POST');
    $payload = Security::jsonInput();
    $profile = [
        'first_name' => profileValue($payload, 'first_name', 80),
        'middle_name' => profileValue($payload, 'middle_name', 80),
        'last_name' => profileValue($payload, 'last_name', 80),
        'email' => profileValue($payload, 'email', 150),
        'mobile' => profileValue($payload, 'mobile', 20),
        'job_title' => profileValue($payload, 'job_title', 120),
    ];

    if ($profile['email'] !== '' && filter_var($profile['email'], FILTER_VALIDATE_EMAIL) === false) {
        Response::validation(['email' => 'Enter a valid email address.']);
    }
    if ($profile['mobile'] !== '' && !preg_match('/^[0-9+() -]{7,20}$/', $profile['mobile'])) {
        Response::validation(['mobile' => 'Enter a valid mobile number.']);
    }

    $statement = $con->prepare(
        'INSERT INTO user_profile_secure
            (user_id, first_name_encrypted, middle_name_encrypted, last_name_encrypted,
             email_encrypted, mobile_encrypted, job_title_encrypted)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            first_name_encrypted = VALUES(first_name_encrypted),
            middle_name_encrypted = VALUES(middle_name_encrypted),
            last_name_encrypted = VALUES(last_name_encrypted),
            email_encrypted = VALUES(email_encrypted),
            mobile_encrypted = VALUES(mobile_encrypted),
            job_title_encrypted = VALUES(job_title_encrypted)'
    );
    if ($statement === false) {
        Response::error('Profile storage is not initialized. Apply the secure-profile migration first.', null, 503);
    }
    $firstName = Crypto::encrypt($profile['first_name']);
    $middleName = Crypto::encrypt($profile['middle_name']);
    $lastName = Crypto::encrypt($profile['last_name']);
    $email = Crypto::encrypt($profile['email']);
    $mobile = Crypto::encrypt($profile['mobile']);
    $jobTitle = Crypto::encrypt($profile['job_title']);
    $statement->bind_param('issssss', $userId, $firstName, $middleName, $lastName, $email, $mobile, $jobTitle);
    $statement->execute();
    $statement->close();

    /* Remove old clear-text profile fields after successful encrypted storage. */
    $clearLegacy = $con->prepare(
        'UPDATE user_master SET u_fname = NULL, u_mname = NULL, u_lname = NULL,
         u_email_id = NULL, u_mob_no = NULL WHERE u_id = ?'
    );
    $clearLegacy->bind_param('i', $userId);
    $clearLegacy->execute();
    $clearLegacy->close();

    Response::success('Profile saved securely.', ['profile' => $profile]);
} catch (Throwable $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'auth.profile', 'user_id' => SessionManager::userId()]);
    Response::serverError();
}

<?php
declare(strict_types=1);

/* Run after 20260720_secure_user_profile.php.
 * It transfers existing clear-text profile fields to encrypted storage and
 * clears those legacy columns. Execute once from the API host only. */
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/assets/conn/db.php';
require_once dirname(__DIR__, 2) . '/core/Crypto.php';

$users = $con->query(
    'SELECT u_id, u_fname, u_mname, u_lname, u_email_id, u_mob_no
     FROM user_master
     WHERE COALESCE(u_fname, u_mname, u_lname, u_email_id, u_mob_no) IS NOT NULL'
);

$save = $con->prepare(
    'INSERT INTO user_profile_secure
        (user_id, first_name_encrypted, middle_name_encrypted, last_name_encrypted,
         email_encrypted, mobile_encrypted, job_title_encrypted)
     VALUES (?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        first_name_encrypted = VALUES(first_name_encrypted),
        middle_name_encrypted = VALUES(middle_name_encrypted),
        last_name_encrypted = VALUES(last_name_encrypted),
        email_encrypted = VALUES(email_encrypted),
        mobile_encrypted = VALUES(mobile_encrypted)'
);
$clear = $con->prepare(
    'UPDATE user_master SET u_fname = NULL, u_mname = NULL, u_lname = NULL,
     u_email_id = NULL, u_mob_no = NULL WHERE u_id = ?'
);

$count = 0;
while ($user = $users->fetch_assoc()) {
    $userId = (int) $user['u_id'];
    $first = Crypto::encrypt((string) ($user['u_fname'] ?? ''));
    $middle = Crypto::encrypt((string) ($user['u_mname'] ?? ''));
    $last = Crypto::encrypt((string) ($user['u_lname'] ?? ''));
    $email = Crypto::encrypt((string) ($user['u_email_id'] ?? ''));
    $mobile = Crypto::encrypt((string) ($user['u_mob_no'] ?? ''));
    $jobTitle = '';
    $save->bind_param('issssss', $userId, $first, $middle, $last, $email, $mobile, $jobTitle);
    $save->execute();
    $clear->bind_param('i', $userId);
    $clear->execute();
    $count++;
}

$save->close();
$clear->close();
echo "Encrypted and cleared {$count} user profile record(s)." . PHP_EOL;

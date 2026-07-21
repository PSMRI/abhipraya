<?php
declare(strict_types=1);

/* Execute with: php api/database/migrations/20260720_secure_user_profile.php */
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/assets/conn/db.php';

$statements = [
    'ALTER TABLE user_master MODIFY u_pass VARCHAR(255) NOT NULL',
    'CREATE TABLE IF NOT EXISTS user_profile_secure (
        user_id INT NOT NULL,
        first_name_encrypted TEXT NULL,
        middle_name_encrypted TEXT NULL,
        last_name_encrypted TEXT NULL,
        email_encrypted TEXT NULL,
        mobile_encrypted TEXT NULL,
        job_title_encrypted TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id),
        CONSTRAINT fk_user_profile_secure_user FOREIGN KEY (user_id)
            REFERENCES user_master(u_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];

foreach ($statements as $statement) {
    if (!$con->query($statement)) {
        fwrite(STDERR, "Migration failed: " . $con->error . PHP_EOL);
        exit(1);
    }
}

echo "Secure profile migration complete." . PHP_EOL;

CREATE TABLE IF NOT EXISTS auth_login_attempts (
    username_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (username_hash, ip_hash),
    KEY idx_auth_login_attempts_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

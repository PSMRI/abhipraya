-- Run once for Abhipraya account-profile privacy support.
ALTER TABLE user_master MODIFY u_pass VARCHAR(255) NOT NULL;

CREATE TABLE IF NOT EXISTS user_profile_secure (
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
    CONSTRAINT fk_user_profile_secure_user
        FOREIGN KEY (user_id) REFERENCES user_master(u_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

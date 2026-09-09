-- Abhipraya core schema for a new, empty MySQL 8+ or compatible MariaDB database.
-- This script is non-destructive: it creates tables only when absent.
-- Do not use it to replace the controlled migration process for an existing deployment.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS user_master (
    u_id INT NOT NULL AUTO_INCREMENT,
    u_name VARCHAR(100) NOT NULL,
    u_pass VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    u_role INT UNSIGNED NOT NULL DEFAULT 0,
    NIN_fk VARCHAR(20) NULL,
    u_identity INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (u_id),
    UNIQUE KEY uq_user_master_username (u_name),
    KEY idx_user_master_scope (NIN_fk, u_role, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
        FOREIGN KEY (user_id) REFERENCES user_master(u_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_login_attempts (
    username_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (username_hash, ip_hash),
    KEY idx_auth_login_attempts_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS srvy_responses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id CHAR(48) NOT NULL,
    hospital_nin VARCHAR(20) NOT NULL,
    department_id VARCHAR(20) NOT NULL,
    survey_code VARCHAR(100) NULL,
    survey_version VARCHAR(20) NULL,
    survey_schema_hash CHAR(64) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    ip_address VARCHAR(45) NULL,
    device_id VARCHAR(250) NOT NULL,
    srvy_Q0 TINYINT UNSIGNED NULL, srvy_Q1 TINYINT UNSIGNED NULL,
    srvy_Q2 TINYINT UNSIGNED NULL, srvy_Q3 TINYINT UNSIGNED NULL,
    srvy_Q4 TINYINT UNSIGNED NULL, srvy_Q5 TINYINT UNSIGNED NULL,
    srvy_Q6 TINYINT UNSIGNED NULL, srvy_Q7 TINYINT UNSIGNED NULL,
    srvy_Q8 TINYINT UNSIGNED NULL, srvy_Q9 TINYINT UNSIGNED NULL,
    srvy_Q10 TINYINT UNSIGNED NULL, srvy_Q11 TINYINT UNSIGNED NULL,
    srvy_Q12 TINYINT UNSIGNED NULL, srvy_Q13 TINYINT UNSIGNED NULL,
    srvy_Q14 TINYINT UNSIGNED NULL, srvy_Q15 TINYINT UNSIGNED NULL,
    srvy_Q16 TINYINT UNSIGNED NULL, srvy_Q17 TINYINT UNSIGNED NULL,
    srvy_Q18 TINYINT UNSIGNED NULL, srvy_Q19 TINYINT UNSIGNED NULL,
    srvy_Q20 TINYINT UNSIGNED NULL, srvy_Q21 TINYINT UNSIGNED NULL,
    srvy_Q22 TINYINT UNSIGNED NULL, srvy_Q23 TINYINT UNSIGNED NULL,
    srvy_Q24 TINYINT UNSIGNED NULL, srvy_Q25 TINYINT UNSIGNED NULL,
    srvy_Q26 TINYINT UNSIGNED NULL, srvy_Q27 TINYINT UNSIGNED NULL,
    srvy_Q28 TINYINT UNSIGNED NULL, srvy_Q29 TINYINT UNSIGNED NULL,
    srvy_Q30 TINYINT UNSIGNED NULL,
    srvy_rpl_dt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_srvy_responses_submission_id (submission_id),
    KEY idx_srvy_responses_scope_time (hospital_nin, department_id, srvy_rpl_dt),
    KEY idx_srvy_responses_survey (survey_code, survey_version, survey_schema_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capa_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hospital_nin VARCHAR(20) NOT NULL,
    month CHAR(7) NOT NULL,
    question_key VARCHAR(32) NOT NULL,
    root_cause TEXT NOT NULL,
    action_plan TEXT NOT NULL,
    responsible VARCHAR(255) NOT NULL,
    timeline VARCHAR(255) NOT NULL,
    remarks TEXT NULL,
    dept_id INT UNSIGNED NOT NULL,
    survey_code VARCHAR(100) NOT NULL,
    survey_version VARCHAR(20) NOT NULL,
    survey_schema_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_capa_scope (hospital_nin, dept_id, month, survey_version, question_key),
    KEY idx_capa_survey (survey_code, survey_version, survey_schema_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_audit_tokens (
    token CHAR(32) NOT NULL,
    facility_nin VARCHAR(20) NOT NULL,
    survey_date DATE NOT NULL,
    valid_until DATETIME NOT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (token),
    UNIQUE KEY uq_social_audit_scope (facility_nin, survey_date),
    KEY idx_social_audit_valid_until (valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_audit_answers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token CHAR(32) NOT NULL,
    facility_nin VARCHAR(20) NOT NULL,
    survey_date DATE NOT NULL,
    question_id VARCHAR(20) NOT NULL,
    answer_value VARCHAR(32) NOT NULL,
    language CHAR(2) NOT NULL,
    respondent_key CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_social_audit_response (token, respondent_key, question_id),
    KEY idx_social_audit_scope (facility_nin, survey_date, question_id),
    CONSTRAINT fk_social_audit_answer_token FOREIGN KEY (token)
      REFERENCES social_audit_tokens(token) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS social_audit_submissions (
    token CHAR(32) NOT NULL,
    respondent_key CHAR(64) NOT NULL,
    facility_nin VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45) NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (token, respondent_key),
    KEY idx_social_audit_submissions_facility (facility_nin, submitted_at),
    CONSTRAINT fk_social_audit_submission_token FOREIGN KEY (token)
      REFERENCES social_audit_tokens(token) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_social_audit_response (token, respondent_key, question_id),
    KEY idx_social_audit_scope (facility_nin, survey_date, question_id),
    CONSTRAINT fk_social_audit_answer_token FOREIGN KEY (token)
      REFERENCES social_audit_tokens(token) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

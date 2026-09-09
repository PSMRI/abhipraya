CREATE TABLE IF NOT EXISTS social_audit_submissions (
    token CHAR(32) NOT NULL,
    respondent_key CHAR(64) NOT NULL,
    facility_nin VARCHAR(20) NOT NULL,
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (token, respondent_key),
    KEY idx_social_audit_submissions_facility (facility_nin, submitted_at),
    CONSTRAINT fk_social_audit_submission_token FOREIGN KEY (token)
      REFERENCES social_audit_tokens(token) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

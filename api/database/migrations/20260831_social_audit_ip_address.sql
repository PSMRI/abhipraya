ALTER TABLE social_audit_answers
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER respondent_key;

ALTER TABLE social_audit_submissions
    ADD COLUMN ip_address VARCHAR(45) NULL AFTER facility_nin;

-- Abhipraya survey-version identity for historical response interpretation.
ALTER TABLE srvy_responses
    ADD COLUMN survey_code VARCHAR(100) NULL AFTER department_id,
    ADD COLUMN survey_version VARCHAR(20) NULL AFTER survey_code,
    ADD COLUMN survey_schema_hash CHAR(64) NULL AFTER survey_version,
    ADD INDEX idx_srvy_version_scope
        (hospital_nin, department_id, survey_version, srvy_rpl_dt);

UPDATE srvy_responses
SET survey_code = CONCAT('DEPARTMENT_', department_id, '_FEEDBACK')
WHERE survey_code IS NULL OR survey_code = '';

UPDATE srvy_responses
SET survey_version = '1.0'
WHERE survey_version IS NULL OR survey_version = '';

UPDATE srvy_responses
SET survey_code = 'LEGACY_UNASSIGNED',
    survey_schema_hash = SHA2('LEGACY_UNASSIGNED_SCHEMA', 256)
WHERE department_id IS NULL OR TRIM(department_id) = '';

-- Use the PHP migration to backfill the correct per-department schema hash.

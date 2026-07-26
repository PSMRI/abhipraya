-- Version CAPA against the immutable survey definition that produced its indicator.
ALTER TABLE capa_actions
    DROP INDEX uq_capa_scope,
    ADD COLUMN survey_code VARCHAR(100) NULL AFTER dept_id,
    ADD COLUMN survey_version VARCHAR(20) NULL AFTER survey_code,
    ADD COLUMN survey_schema_hash CHAR(64) NULL AFTER survey_version;

UPDATE capa_actions
SET survey_code = CASE
        WHEN dept_id BETWEEN 1 AND 9 THEN CONCAT('DEPARTMENT_', dept_id, '_FEEDBACK')
        ELSE 'LEGACY_UNASSIGNED'
    END,
    survey_version = '1.0',
    survey_schema_hash = CASE dept_id
        WHEN 1 THEN 'f3dd29ee3597268f257cc4173b69332d4893fa0407443fb93415f805fa8a654f'
        WHEN 2 THEN '2b610ff69e9f972ec4faad30353ada43aa3a8e194773ef671b6df9145fe0e2a7'
        WHEN 3 THEN '9a629963fb5f8f25ca7a5a7844af812674ac99b81d08d6a762d21cbdd8e6ee0b'
        WHEN 4 THEN 'bbd2cde3dff689f874b156cb12b88ac3984b6cd28dc50bbb1e6196e5fa168209'
        WHEN 5 THEN '29f4b93f91d616ecd9a87f886f8e3242151f200bfa7795c041235196cd0dc114'
        WHEN 6 THEN '2c9e54fafb072f4f703f1caae5f6f3e5588e01b6522bd639172d614688c23b12'
        WHEN 7 THEN 'b1b2adf655fd3501e8c8834e9dea992022da2f181fd964434b27b241619444d4'
        WHEN 8 THEN '62278d33921a7dfbb7f99905458d526e31188e41ffdec9b4d67acd64857b4636'
        WHEN 9 THEN 'a5f7ef6769adaf800f6895d32cc9c8f7544bc552f105a899b96f5927b2e14085'
        ELSE SHA2('LEGACY_UNASSIGNED_SCHEMA', 256)
    END
WHERE survey_version IS NULL OR survey_version = '';

ALTER TABLE capa_actions
    MODIFY survey_code VARCHAR(100) NOT NULL,
    MODIFY survey_version VARCHAR(20) NOT NULL,
    MODIFY survey_schema_hash CHAR(64) NOT NULL,
    ADD UNIQUE KEY uq_capa_scope
        (hospital_nin, dept_id, month, survey_version, question_key),
    ADD INDEX idx_capa_survey
        (survey_code, survey_version, survey_schema_hash);

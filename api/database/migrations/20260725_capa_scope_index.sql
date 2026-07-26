-- CAPA is scoped by facility, department, month, and question indicator.
-- The legacy idx_unique omitted dept_id and incorrectly prevented separate
-- department action plans for the same question column.
ALTER TABLE capa_actions
    DROP INDEX idx_unique,
    ADD UNIQUE KEY uq_capa_scope (hospital_nin, dept_id, month, question_key);

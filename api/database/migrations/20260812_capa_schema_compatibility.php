<?php
declare(strict_types=1);

/*
 * Safely upgrades CAPA tables created before survey-version support.
 * Execute with: php api/database/migrations/20260812_capa_schema_compatibility.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/assets/conn/db.php';

function capaColumnExists(mysqli $connection, string $column): bool
{
    $statement = $connection->prepare(
        'SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "capa_actions" AND COLUMN_NAME = ?'
    );
    $statement->bind_param('s', $column);
    $statement->execute();
    $exists = (int) ($statement->get_result()->fetch_assoc()['total'] ?? 0) > 0;
    $statement->close();
    return $exists;
}

function capaIndexColumns(mysqli $connection, string $index): ?string
{
    $statement = $connection->prepare(
        'SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ",") AS columns_list
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "capa_actions" AND INDEX_NAME = ?'
    );
    $statement->bind_param('s', $index);
    $statement->execute();
    $columns = $statement->get_result()->fetch_assoc()['columns_list'] ?? null;
    $statement->close();
    return is_string($columns) && $columns !== '' ? $columns : null;
}

if (!$con->query("SHOW TABLES LIKE 'capa_actions'")->num_rows) {
    throw new RuntimeException('The capa_actions table does not exist. Apply the core schema first.');
}

$columns = [
    'survey_code' => 'ALTER TABLE capa_actions ADD COLUMN survey_code VARCHAR(100) NULL AFTER dept_id',
    'survey_version' => 'ALTER TABLE capa_actions ADD COLUMN survey_version VARCHAR(20) NULL AFTER survey_code',
    'survey_schema_hash' => 'ALTER TABLE capa_actions ADD COLUMN survey_schema_hash CHAR(64) NULL AFTER survey_version',
];
foreach ($columns as $column => $sql) {
    if (!capaColumnExists($con, $column)) {
        $con->query($sql);
        echo 'Added ', $column, PHP_EOL;
    }
}

$con->query(
    "UPDATE capa_actions
     SET survey_code = CASE WHEN dept_id BETWEEN 1 AND 9 THEN CONCAT('DEPARTMENT_', dept_id, '_FEEDBACK') ELSE 'LEGACY_UNASSIGNED' END,
         survey_version = '1.0',
         survey_schema_hash = SHA2(CONCAT('CAPA_LEGACY_SCHEMA_', COALESCE(dept_id, 0)), 256)
     WHERE survey_code IS NULL OR survey_code = ''
        OR survey_version IS NULL OR survey_version = ''
        OR survey_schema_hash IS NULL OR survey_schema_hash = ''"
);

$con->query('ALTER TABLE capa_actions MODIFY survey_code VARCHAR(100) NOT NULL, MODIFY survey_version VARCHAR(20) NOT NULL, MODIFY survey_schema_hash CHAR(64) NOT NULL');

$scope = 'hospital_nin,dept_id,month,survey_version,question_key';
foreach (['idx_unique', 'uq_capa_scope'] as $index) {
    $existing = capaIndexColumns($con, $index);
    if ($existing !== null && $existing !== $scope) {
        $con->query('ALTER TABLE capa_actions DROP INDEX ' . $index);
        echo 'Dropped legacy ', $index, PHP_EOL;
    }
}
if (capaIndexColumns($con, 'uq_capa_scope') === null) {
    $con->query('ALTER TABLE capa_actions ADD UNIQUE KEY uq_capa_scope (hospital_nin, dept_id, month, survey_version, question_key)');
    echo 'Added uq_capa_scope', PHP_EOL;
}
if (capaIndexColumns($con, 'idx_capa_survey') === null) {
    $con->query('ALTER TABLE capa_actions ADD INDEX idx_capa_survey (survey_code, survey_version, survey_schema_hash)');
    echo 'Added idx_capa_survey', PHP_EOL;
}

echo 'CAPA schema compatibility migration complete.', PHP_EOL;

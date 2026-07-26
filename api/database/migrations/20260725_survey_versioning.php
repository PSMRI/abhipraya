<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/assets/conn/db.php';

function migrationColumnExists(mysqli $connection, string $column): bool
{
    $statement = $connection->prepare(
        'SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "srvy_responses"
           AND COLUMN_NAME = ?'
    );
    $statement->bind_param('s', $column);
    $statement->execute();
    $exists = (int) ($statement->get_result()->fetch_assoc()['total'] ?? 0) > 0;
    $statement->close();
    return $exists;
}

function migrationIndexExists(mysqli $connection, string $index): bool
{
    $statement = $connection->prepare(
        'SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "srvy_responses"
           AND INDEX_NAME = ?'
    );
    $statement->bind_param('s', $index);
    $statement->execute();
    $exists = (int) ($statement->get_result()->fetch_assoc()['total'] ?? 0) > 0;
    $statement->close();
    return $exists;
}

$columns = [
    'survey_code' => 'ALTER TABLE srvy_responses ADD COLUMN survey_code VARCHAR(100) NULL AFTER department_id',
    'survey_version' => 'ALTER TABLE srvy_responses ADD COLUMN survey_version VARCHAR(20) NULL AFTER survey_code',
    'survey_schema_hash' => 'ALTER TABLE srvy_responses ADD COLUMN survey_schema_hash CHAR(64) NULL AFTER survey_version',
];

foreach ($columns as $column => $sql) {
    if (!migrationColumnExists($con, $column)) {
        $con->query($sql);
        echo 'Added ', $column, PHP_EOL;
    }
}

$con->query(
    "UPDATE srvy_responses
     SET survey_code = CONCAT('DEPARTMENT_', department_id, '_FEEDBACK')
     WHERE survey_code IS NULL OR survey_code = ''"
);
$con->query(
    "UPDATE srvy_responses
     SET survey_version = '1.0'
     WHERE survey_version IS NULL OR survey_version = ''"
);
$con->query(
    "UPDATE srvy_responses
     SET survey_code = 'LEGACY_UNASSIGNED',
         survey_schema_hash = SHA2('LEGACY_UNASSIGNED_SCHEMA', 256)
     WHERE department_id IS NULL OR TRIM(department_id) = ''"
);

$masterDirectory = dirname(__DIR__, 2) . '/masters';
for ($departmentId = 1; $departmentId <= 9; $departmentId++) {
    $surveyFile = $masterDirectory . '/surveys/department_' . $departmentId . '/v1.0/survey.json';
    if (!is_file($surveyFile)) {
        continue;
    }
    $schemaHash = hash_file('sha256', $surveyFile);
    $statement = $con->prepare(
        "UPDATE srvy_responses
         SET survey_schema_hash = ?
         WHERE department_id = ?
           AND (survey_schema_hash IS NULL OR survey_schema_hash = '')"
    );
    $statement->bind_param('si', $schemaHash, $departmentId);
    $statement->execute();
    $statement->close();
}

if (!migrationIndexExists($con, 'idx_srvy_version_scope')) {
    $con->query(
        'ALTER TABLE srvy_responses
         ADD INDEX idx_srvy_version_scope
            (hospital_nin, department_id, survey_version, srvy_rpl_dt)'
    );
    echo 'Added idx_srvy_version_scope', PHP_EOL;
}

echo 'Survey version migration complete.', PHP_EOL;

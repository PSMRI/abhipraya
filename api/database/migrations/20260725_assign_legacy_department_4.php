<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once 'D:/RAM_sir_app/abhipraya/api/bootstrap.php';
require_once 'D:/RAM_sir_app/abhipraya/api/assets/conn/db.php';

$surveyFile = 'D:/RAM_sir_app/abhipraya/api/masters/surveys/department_4/v1.0/survey.json';
if (!is_file($surveyFile)) {
    throw new RuntimeException('Department 4 survey version 1.0 is unavailable.');
}

$schemaHash = hash_file('sha256', $surveyFile);
if (!is_string($schemaHash) || strlen($schemaHash) !== 64) {
    throw new RuntimeException('Unable to calculate the department 4 schema hash.');
}

$con->begin_transaction();
try {
    $countResult = $con->query(
        "SELECT COUNT(*) AS total
         FROM srvy_responses
         WHERE (department_id IS NULL OR TRIM(department_id) = '')
           AND survey_code = 'LEGACY_UNASSIGNED'"
    );
    $eligible = (int) ($countResult->fetch_assoc()['total'] ?? 0);
    if ($eligible !== 15) {
        throw new RuntimeException(
            'Expected exactly 15 legacy unassigned rows; found ' . $eligible . '. No rows were changed.'
        );
    }

    $statement = $con->prepare(
        "UPDATE srvy_responses
         SET department_id = '4',
             survey_code = 'DEPARTMENT_4_FEEDBACK',
             survey_version = '1.0',
             survey_schema_hash = ?
         WHERE (department_id IS NULL OR TRIM(department_id) = '')
           AND survey_code = 'LEGACY_UNASSIGNED'"
    );
    $statement->bind_param('s', $schemaHash);
    $statement->execute();
    $updated = $statement->affected_rows;
    $statement->close();

    if ($updated !== 15) {
        throw new RuntimeException(
            'Expected to update exactly 15 rows; updated ' . $updated . '. No changes were committed.'
        );
    }

    $con->commit();
    echo 'Assigned ', $updated, ' legacy responses to department 4.', PHP_EOL;
} catch (Throwable $exception) {
    $con->rollback();
    throw $exception;
}

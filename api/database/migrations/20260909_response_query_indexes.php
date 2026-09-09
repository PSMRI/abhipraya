<?php
declare(strict_types=1);

/* Execute with: php api/database/migrations/20260909_response_query_indexes.php */
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/assets/conn/db.php';

/** @param array<int, string> $columns */
function responseIndexExists(mysqli $connection, array $columns): bool
{
    $result = $connection->query('SHOW INDEX FROM `srvy_responses`');
    if ($result === false) {
        throw new RuntimeException('Unable to inspect response indexes: ' . $connection->error);
    }

    $indexes = [];
    while ($row = $result->fetch_assoc()) {
        $indexes[(string) $row['Key_name']][(int) $row['Seq_in_index']] = (string) $row['Column_name'];
    }
    $result->close();

    foreach ($indexes as $indexColumns) {
        ksort($indexColumns);
        if (array_values($indexColumns) === $columns) return true;
    }
    return false;
}

$indexes = [
    [
        'idx_srvy_responses_nin_dept_version_time',
        ['hospital_nin', 'department_id', 'survey_version', 'srvy_rpl_dt'],
        'ADD INDEX idx_srvy_responses_nin_dept_version_time (hospital_nin, department_id, survey_version, srvy_rpl_dt)',
    ],
    [
        'idx_srvy_responses_dept_time_nin',
        ['department_id', 'srvy_rpl_dt', 'hospital_nin'],
        'ADD INDEX idx_srvy_responses_dept_time_nin (department_id, srvy_rpl_dt, hospital_nin)',
    ],
];

try {
    foreach ($indexes as [$name, $columns, $definition]) {
        if (responseIndexExists($con, $columns)) {
            echo "Already present: srvy_responses.{$name}" . PHP_EOL;
            continue;
        }
        if (!$con->query('ALTER TABLE `srvy_responses` ' . $definition)) {
            throw new RuntimeException("Unable to add {$name}: " . $con->error);
        }
        echo "Added: srvy_responses.{$name}" . PHP_EOL;
    }
    $con->query('ANALYZE TABLE `srvy_responses`');
    echo 'Response query index migration complete.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

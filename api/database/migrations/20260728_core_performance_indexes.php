<?php
declare(strict_types=1);

/* Execute with: php api/database/migrations/20260728_core_performance_indexes.php */
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/assets/conn/db.php';

/** @return array<string, bool> */
function migrationIndexes(mysqli $connection, string $table): array
{
    $allowed = ['user_master', 'srvy_responses', 'capa_actions'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Unexpected table name.');
    }

    $result = $connection->query('SHOW INDEX FROM `' . $table . '`');
    if ($result === false) {
        throw new RuntimeException('Unable to inspect indexes for ' . $table . '.');
    }

    $indexes = [];
    while ($row = $result->fetch_assoc()) {
        $indexes[(string) $row['Key_name']] = true;
    }
    $result->close();
    return $indexes;
}

$definitions = [
    'user_master' => [
        'idx_user_master_scope' => 'ADD INDEX idx_user_master_scope (NIN_fk, u_role, active)',
    ],
    'srvy_responses' => [
        'idx_srvy_responses_scope_time' => 'ADD INDEX idx_srvy_responses_scope_time (hospital_nin, department_id, srvy_rpl_dt)',
        'idx_srvy_responses_survey' => 'ADD INDEX idx_srvy_responses_survey (survey_code, survey_version, survey_schema_hash)',
    ],
    'capa_actions' => [
        'idx_capa_scope' => 'ADD INDEX idx_capa_scope (hospital_nin, month, question_key)',
    ],
];

try {
    foreach ($definitions as $table => $indexes) {
        $existing = migrationIndexes($con, $table);
        foreach ($indexes as $name => $definition) {
            if (isset($existing[$name])) {
                echo "Already present: {$table}.{$name}" . PHP_EOL;
                continue;
            }
            if (!$con->query('ALTER TABLE `' . $table . '` ' . $definition)) {
                throw new RuntimeException('Unable to add ' . $table . '.' . $name . ': ' . $con->error);
            }
            echo "Added: {$table}.{$name}" . PHP_EOL;
        }
    }
    echo "Core performance index migration complete." . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

<?php
declare(strict_types=1);

/* Execute with: php api/database/migrations/20260904_dashboard_performance_indexes.php */
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/assets/conn/db.php';

/** @param array<int, string> $columns */
function dashboardIndexExists(mysqli $connection, string $table, array $columns): bool
{
    $result = $connection->query('SHOW INDEX FROM `' . $table . '`');
    $indexes = [];
    while ($row = $result->fetch_assoc()) {
        $indexes[(string) $row['Key_name']][(int) $row['Seq_in_index']] = (string) $row['Column_name'];
    }
    foreach ($indexes as $indexColumns) {
        ksort($indexColumns);
        if (array_values($indexColumns) === $columns) {
            return true;
        }
    }
    return false;
}

$indexes = [
    ['fac_master', 'idx_fac_master_nin', ['NIN_no'], 'ADD INDEX idx_fac_master_nin (NIN_no)'],
    ['fac_master', 'idx_fac_master_lookup', ['active_status', 'fac_name'], 'ADD INDEX idx_fac_master_lookup (active_status, fac_name)'],
    ['srvy_responses', 'idx_srvy_responses_time', ['srvy_rpl_dt', 'id'], 'ADD INDEX idx_srvy_responses_time (srvy_rpl_dt, id)'],
];

try {
    foreach ($indexes as [$table, $name, $columns, $definition]) {
        if (dashboardIndexExists($con, $table, $columns)) {
            echo "Already present: {$table}.{$name}" . PHP_EOL;
            continue;
        }
        if (!$con->query("ALTER TABLE `{$table}` {$definition}")) {
            throw new RuntimeException("Unable to add {$table}.{$name}: {$con->error}");
        }
        echo "Added: {$table}.{$name}" . PHP_EOL;
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

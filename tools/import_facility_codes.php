<?php
declare(strict_types=1);

/*
 * Imports api/masters/facilityCodes.json into fac_master.
 *
 * Usage:
 *   php tools/import_facility_codes.php --dry-run
 *   php tools/import_facility_codes.php --import
 *
 * Database settings are loaded from the repository .env via api/assets/conn/db.php.
 * Existing NIN_no rows are deliberately left unchanged.
 */

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/api/assets/conn/db.php';

$mode = $argv[1] ?? '--dry-run';
if (!in_array($mode, ['--dry-run', '--import'], true)) {
    fwrite(STDERR, "Usage: php tools/import_facility_codes.php [--dry-run|--import]\n");
    exit(2);
}

$jsonPath = dirname(__DIR__) . '/api/masters/facilityCodes.json';
try {
    $rows = json_decode((string) file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "Cannot read facility JSON: {$exception->getMessage()}\n");
    exit(1);
}

if (!is_array($rows)) {
    fwrite(STDERR, "Facility JSON must contain an array.\n");
    exit(1);
}

/* Each NIN is available in English and Hindi. Prefer English, otherwise keep
 * the first valid record so one database row represents each facility. */
$facilities = [];
foreach ($rows as $row) {
    if (!is_array($row) || empty($row['facilityNIN']) || empty($row['facilityName'])) {
        continue;
    }

    $nin = trim((string) $row['facilityNIN']);
    // NIN is an identifier. The completed source also contains valid
    // alphanumeric identifiers such as NIN1141553543.
    if (!isset($facilities[$nin]) || (int) ($row['langCode'] ?? 0) === 1) {
        $facilities[$nin] = $row;
    }
}

$existing = [];
$result = $con->query('SELECT NIN_no FROM fac_master');
while ($existingRow = $result->fetch_assoc()) {
    $existing[(string) $existingRow['NIN_no']] = true;
}

$toInsert = array_filter(
    $facilities,
    static fn (array $facility, string $nin): bool => !isset($existing[$nin]),
    ARRAY_FILTER_USE_BOTH
);

if ($mode === '--dry-run') {
    echo 'source_rows=' . count($rows) . PHP_EOL;
    echo 'unique_facilities=' . count($facilities) . PHP_EOL;
    echo 'existing_facilities=' . (count($facilities) - count($toInsert)) . PHP_EOL;
    echo 'would_insert=' . count($toInsert) . PHP_EOL;
    exit(0);
}

$inserted = 0;
$con->begin_transaction();
try {
    foreach (array_chunk($toInsert, 250, true) as $batch) {
        $values = [];
        foreach ($batch as $nin => $facility) {
        $name = mb_substr(trim((string) $facility['facilityName']), 0, 150);
        $address = mb_substr(trim((string) ($facility['facilityAddress'] ?? '')), 0, 255);
        $type = mb_substr(trim((string) ($facility['facilityType'] ?? '')), 0, 45);
        $district = mb_substr(trim((string) ($facility['districtCode'] ?? '')), 0, 45);
        $block = mb_substr(trim((string) ($facility['blockCode'] ?? '')), 0, 45);
        $division = mb_substr(trim((string) ($facility['stateCode'] ?? '')), 0, 45);
        $latitude = trim((string) ($facility['facilityLat'] ?? ''));
        $longitude = trim((string) ($facility['facilityLong'] ?? ''));
        $latitude = preg_match('/^-?\d{1,3}(?:\.\d+)?$/', $latitude) ? $latitude : 'NULL';
        $longitude = preg_match('/^-?\d{1,3}(?:\.\d+)?$/', $longitude) ? $longitude : 'NULL';

            $escape = static fn (string $value): string => "'" . $con->real_escape_string($value) . "'";
            $values[] = sprintf(
                '(%s, %s, %s, %s, %s, %s, %s, %s, %s, 1, NOW(), NOW())',
                $escape((string) $nin), $escape($name), $escape($address), $escape($type),
                $escape($district), $escape($block), $escape($division), $latitude, $longitude
            );
        }

        $sql = 'INSERT INTO fac_master '
            . '(NIN_no, fac_name, fac_address, fac_type, fac_dist_id, fac_block_id, '
            . 'fac_div_id, fac_lat, fac_long, active_status, created_at, updated_at) VALUES '
            . implode(', ', $values);
        if (!$con->query($sql)) {
            throw new RuntimeException($con->error);
        }
        $inserted += count($batch);
    }
    $con->commit();
} catch (Throwable $exception) {
    $con->rollback();
    fwrite(STDERR, "Import rolled back: {$exception->getMessage()}\n");
    exit(1);
}

echo 'inserted=' . $inserted . PHP_EOL;
echo 'skipped_existing=' . (count($facilities) - count($toInsert)) . PHP_EOL;

<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
Security::requireMethod('GET');

try {
    $facilityNin = trim((string) ($_GET['facility_nin'] ?? $_GET['facility_id'] ?? ''));
    $departmentId = trim((string) ($_GET['department_id'] ?? ''));
    $from = trim((string) ($_GET['from'] ?? $_GET['date_from'] ?? ''));
    $where = ['1=1']; $types = ''; $values = [];
    if ($facilityNin !== '') { $where[] = 'hospital_nin = ?'; $types .= 's'; $values[] = $facilityNin; }
    if ($departmentId !== '') { $where[] = 'department_id = ?'; $types .= 's'; $values[] = $departmentId; }
    if ($from !== '') { $where[] = 'DATE(srvy_rpl_dt) >= ?'; $types .= 's'; $values[] = $from; }

    $stmt = $con->prepare('SELECT * FROM srvy_responses WHERE ' . implode(' AND ', $where) . ' ORDER BY srvy_rpl_dt DESC, id DESC LIMIT 10000');
    if ($types !== '') $stmt->bind_param($types, ...$values);
    $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    $facilityMap = feedbackFacilityMap(); $departmentMap = feedbackDepartmentMap();

    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="abhipraya_feedback_' . date('Ymd_His') . '.csv"');
    }
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Submission ID', 'Submitted At', 'Facility NIN', 'Facility', 'Department', 'Rating / 5', 'Sentiment', 'Location Verified']);
    foreach ($rows as $row) {
        $item = feedbackPublicRow($row, $facilityMap, $departmentMap);
        fputcsv($out, [$item['submission_id'], $item['submitted_at'], $item['facility_nin'], $item['facility_name'], $item['department_name'], $item['rating'], $item['sentiment'], $item['location_verified'] ? 'Yes' : 'No']);
    }
    fclose($out); exit;
} catch (Throwable $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'responses.export', 'user_id' => SessionManager::userId()]);
    Response::serverError();
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
Security::requireMethod('GET');

try {
    $facilityNin = trim((string) ($_GET['facility_nin'] ?? $_GET['facility_id'] ?? ''));
    $departmentId = trim((string) ($_GET['department_id'] ?? ''));
    $from = trim((string) ($_GET['from'] ?? $_GET['date_from'] ?? ''));
    $to = trim((string) ($_GET['to'] ?? $_GET['date_to'] ?? ''));
    if (SessionManager::roleId() === 2) {
        $assignedFacilityNin = (string) SessionManager::facilityId();
        if ($assignedFacilityNin === '') {
            Response::forbidden('Your account does not have an assigned facility.');
        }
        if ($facilityNin !== '' && !hash_equals($assignedFacilityNin, $facilityNin)) {
            Response::forbidden('You can export feedback only for your assigned facility.');
        }
        $facilityNin = $assignedFacilityNin;
    }
    $where = ['1=1']; $types = ''; $values = [];
    $allowedNins = AccessScope::facilityNins();
    if ($allowedNins !== null) {
        if ($allowedNins === []) $where[] = '1 = 0';
        else { $where[] = 'hospital_nin IN (' . implode(',', array_fill(0, count($allowedNins), '?')) . ')'; $types .= str_repeat('s', count($allowedNins)); array_push($values, ...$allowedNins); }
    }
    if ($facilityNin !== '') { $where[] = 'hospital_nin = ?'; $types .= 's'; $values[] = $facilityNin; }
    if ($departmentId !== '') { $where[] = 'department_id = ?'; $types .= 's'; $values[] = $departmentId; }
    if ($from !== '') { $where[] = 'srvy_rpl_dt >= ?'; $types .= 's'; $values[] = $from . ' 00:00:00'; }
    if ($to !== '') { $where[] = 'srvy_rpl_dt < DATE_ADD(?, INTERVAL 1 DAY)'; $types .= 's'; $values[] = $to; }

    // Export every response in the permitted, selected scope. This intentionally
    // has no page-size or 10,000-row cap; the Feedback page exports in the
    // background so the administrator can keep using the page while it runs.
    $stmt = $con->prepare('SELECT * FROM srvy_responses WHERE ' . implode(' AND ', $where) . ' ORDER BY srvy_rpl_dt DESC, id DESC');
    if ($types !== '') $stmt->bind_param($types, ...$values);
    $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    $facilityMap = feedbackFacilityMap(); $departmentMap = feedbackDepartmentMap();

    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="abhipraya_feedback_' . date('Ymd_His') . '.csv"');
    }
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Submission ID', 'Submitted At', 'Facility NIN', 'Facility', 'Department', 'Rating / 5', 'Sentiment', 'Location Verified'], ',', '"', '');
    foreach ($rows as $row) {
        $item = feedbackPublicRow($row, $facilityMap, $departmentMap);
        fputcsv($out, [$item['submission_id'], $item['submitted_at'], $item['facility_nin'], $item['facility_name'], $item['department_name'], $item['rating'], $item['sentiment'], $item['location_verified'] ? 'Yes' : 'No'], ',', '"', '');
    }
    fclose($out); exit;
} catch (Throwable $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'responses.export', 'user_id' => SessionManager::userId()]);
    Response::serverError();
}

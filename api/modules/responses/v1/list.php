<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
Security::requireMethod('GET');

try {
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = max(10, min(100, (int) ($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $facilityNin = trim((string) ($_GET['facility_nin'] ?? $_GET['facility_id'] ?? ''));
    $departmentId = trim((string) ($_GET['department_id'] ?? ''));
    $surveyVersion = trim((string) ($_GET['survey_version'] ?? ''));
    $sentiment = strtolower(trim((string) ($_GET['status'] ?? '')));
    $search = trim((string) ($_GET['search'] ?? ''));
    $from = trim((string) ($_GET['from'] ?? $_GET['date_from'] ?? ''));
    $to = trim((string) ($_GET['to'] ?? $_GET['date_to'] ?? ''));

    $facilityMap = feedbackFacilityMap();
    $where = ['1=1']; $types = ''; $values = [];
    if ($surveyVersion !== '' && !preg_match('/^\d+\.\d+(?:\.\d+)?$/', $surveyVersion)) {
        Response::validation(['survey_version' => 'Select a valid survey version.']);
    }
    /* A Facility Administrator is permanently bound to the NIN assigned at
       login. Never rely on the browser's selected value for this boundary. */
    if (SessionManager::roleId() === 2) {
        $assignedFacilityNin = (string) SessionManager::facilityId();
        if ($assignedFacilityNin === '') {
            Response::forbidden('Your account does not have an assigned facility.');
        }
        if ($facilityNin !== '' && !hash_equals($assignedFacilityNin, $facilityNin)) {
            Response::forbidden('You can view feedback only for your assigned facility.');
        }
        $facilityNin = $assignedFacilityNin;
    }
    $allowedNins = AccessScope::facilityNins();
    if ($allowedNins !== null) {
        if ($allowedNins === []) $where[] = '1 = 0';
        else { $where[] = 'hospital_nin IN (' . implode(',', array_fill(0, count($allowedNins), '?')) . ')'; $types .= str_repeat('s', count($allowedNins)); array_push($values, ...$allowedNins); }
    }
    if ($facilityNin !== '') { $where[] = 'hospital_nin = ?'; $types .= 's'; $values[] = $facilityNin; }
    if ($departmentId !== '') { $where[] = 'department_id = ?'; $types .= 's'; $values[] = $departmentId; }
    if ($surveyVersion !== '') {
        $where[] = "COALESCE(NULLIF(survey_version, ''), '1.0') = ?";
        $types .= 's';
        $values[] = $surveyVersion;
    }
    if ($from !== '') { $where[] = 'srvy_rpl_dt >= ?'; $types .= 's'; $values[] = $from . ' 00:00:00'; }
    if ($to !== '') { $where[] = 'srvy_rpl_dt < DATE_ADD(?, INTERVAL 1 DAY)'; $types .= 's'; $values[] = $to; }
    if ($search !== '') {
        $matchingNins = [];
        foreach ($facilityMap as $nin => $facilityName) {
            if (stripos((string) $nin, $search) !== false || stripos($facilityName, $search) !== false) {
                $matchingNins[] = (string) $nin;
            }
        }
        $searchWhere = ['submission_id LIKE ?', 'hospital_nin LIKE ?', 'CAST(id AS CHAR) LIKE ?'];
        $like = '%' . $search . '%';
        $types .= 'sss';
        array_push($values, $like, $like, $like);
        if ($matchingNins !== []) {
            $searchWhere[] = 'hospital_nin IN (' . implode(',', array_fill(0, count($matchingNins), '?')) . ')';
            $types .= str_repeat('s', count($matchingNins));
            array_push($values, ...$matchingNins);
        }
        $where[] = '(' . implode(' OR ', $searchWhere) . ')';
    }

    $sqlWhere = implode(' AND ', $where);
    $countStmt = $con->prepare('SELECT COUNT(*) AS total FROM srvy_responses WHERE ' . $sqlWhere);
    if ($types !== '') $countStmt->bind_param($types, ...$values);
    $countStmt->execute();
    $rawTotal = (int) ($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    // Pull extra rows when a computed sentiment filter is active.
    $queryLimit = $sentiment === '' ? $limit : min(500, max($limit * 8, 100));
    $queryOffset = $sentiment === '' ? $offset : 0;
    $stmt = $con->prepare('SELECT * FROM srvy_responses WHERE ' . $sqlWhere . ' ORDER BY srvy_rpl_dt DESC, id DESC LIMIT ? OFFSET ?');
    $queryTypes = $types . 'ii'; $queryValues = array_merge($values, [$queryLimit, $queryOffset]);
    $stmt->bind_param($queryTypes, ...$queryValues);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $departmentMap = feedbackDepartmentMap();
    $items = array_map(static fn(array $row): array => feedbackPublicRow($row, $facilityMap, $departmentMap), $rows);
    if (in_array($sentiment, ['positive', 'negative', 'neutral'], true)) {
        $items = array_values(array_filter($items, static fn(array $item): bool => $item['sentiment'] === $sentiment));
        $total = count($items);
        $items = array_slice($items, $offset, $limit);
    } else {
        $total = $rawTotal;
    }

    Response::success('Feedback responses loaded.', [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $limit)),
        ],
        'filters' => [
            'facilities' => array_map(static fn(string $nin, string $name): array => ['facility_nin' => $nin, 'facility_name' => $name], array_keys($facilityMap), array_values($facilityMap)),
            'departments' => array_map(static fn(string $id, string $name): array => ['department_id' => (int) $id, 'department_name' => $name], array_keys($departmentMap), array_values($departmentMap)),
        ],
    ]);
} catch (Throwable $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'responses.list', 'user_id' => SessionManager::userId()]);
    Response::serverError();
}

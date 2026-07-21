<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';

Security::requireMethod('GET');
SessionManager::requireLogin();

if (!in_array(SessionManager::roleId(), [1, 2, 3], true)) {
    Response::forbidden('Your role is not allowed to view feedback analytics.');
}

function analyticsFilters(): array
{
    $facilityNin = trim((string) ($_GET['facility_nin'] ?? ''));
    $departmentId = trim((string) ($_GET['department_id'] ?? ''));
    $from = trim((string) ($_GET['from'] ?? ''));
    $to = trim((string) ($_GET['to'] ?? ''));

    if ($facilityNin !== '' && !preg_match('/^\d{6,20}$/', $facilityNin)) {
        Response::validation(['facility_nin' => 'Select a valid facility.']);
    }
    if ($departmentId !== '' && (!ctype_digit($departmentId) || (int) $departmentId < 1 || (int) $departmentId > 30)) {
        Response::validation(['department_id' => 'Select a valid department.']);
    }
    foreach (['from' => $from, 'to' => $to] as $field => $value) {
        if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            Response::validation([$field => 'Use YYYY-MM-DD.']);
        }
    }
    if (SessionManager::roleId() === 2) {
        $assigned = (string) SessionManager::facilityId();
        if ($facilityNin !== '' && !hash_equals($assigned, $facilityNin)) {
            Response::forbidden('You can view analytics only for your assigned facility.');
        }
        $facilityNin = $assigned;
    }
    return [$facilityNin, $departmentId, $from, $to];
}

function analyticsWhere(string $facilityNin, string $departmentId, string $from, string $to): array
{
    $where = [];
    $types = '';
    $values = [];
    if ($facilityNin !== '') { $where[] = 'hospital_nin = ?'; $types .= 's'; $values[] = $facilityNin; }
    if ($departmentId !== '') { $where[] = 'department_id = ?'; $types .= 's'; $values[] = $departmentId; }
    if ($from !== '') { $where[] = 'srvy_rpl_dt >= ?'; $types .= 's'; $values[] = $from . ' 00:00:00'; }
    if ($to !== '') { $where[] = 'srvy_rpl_dt < DATE_ADD(?, INTERVAL 1 DAY)'; $types .= 's'; $values[] = $to; }
    return [$where === [] ? '1=1' : implode(' AND ', $where), $types, $values];
}

function analyticsBind(mysqli_stmt $statement, string $types, array $values): void
{
    if ($types === '') return;
    $params = [$types];
    foreach ($values as $index => $value) $params[] = &$values[$index];
    call_user_func_array([$statement, 'bind_param'], $params);
}

function analyticsReportType(array $question): string
{
    return strtolower(trim((string) ($question['report_type'] ?? 'rating'))) === 'category'
        ? 'category'
        : 'rating';
}

try {
    [$facilityNin, $departmentId, $from, $to] = analyticsFilters();
    [$where, $types, $values] = analyticsWhere($facilityNin, $departmentId, $from, $to);
    /* Demographic breakdowns are facility-specific, avoiding expensive
       category scans across every facility on dashboard/report load. */
    $includeCategories = $facilityNin !== '';

    $summarySql = 'SELECT hospital_nin, department_id, COUNT(*) AS response_count, MAX(srvy_rpl_dt) AS last_response
                   FROM srvy_responses WHERE ' . $where . '
                   GROUP BY hospital_nin, department_id ORDER BY response_count DESC';
    $summaryStmt = $con->prepare($summarySql);
    analyticsBind($summaryStmt, $types, $values);
    $summaryStmt->execute();
    $groups = $summaryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $summaryStmt->close();

    $facilityNames = [];
    foreach (SurveyConfig::facilities() as $facility) $facilityNames[(string) $facility['facilityNIN']] = (string) $facility['facilityName'];
    $departmentNames = [];
    foreach (SurveyConfig::departments() as $department) $departmentNames[(string) $department['departmentId']] = (string) $department['departmentName'];

    if ((string) ($_GET['summary_only'] ?? '') === '1') {
        $facilityCounts = [];
        foreach ($groups as $group) {
            $nin = trim((string) ($group['hospital_nin'] ?? ''));
            if ($nin === '') continue;
            if (!isset($facilityCounts[$nin])) {
                $facilityCounts[$nin] = [
                    'facility_nin' => $nin,
                    'facility_name' => $facilityNames[$nin] ?? $nin,
                    'responses' => 0,
                    'score' => null,
                ];
            }
            $facilityCounts[$nin]['responses'] += (int) ($group['response_count'] ?? 0);
        }
        $facilities = array_values($facilityCounts);
        usort($facilities, static fn(array $left, array $right): int => $right['responses'] <=> $left['responses']);
        $totalResponses = array_sum(array_map(static fn(array $row): int => (int) $row['responses'], $facilities));

        $trendMonths = (int) ($_GET['trend_months'] ?? 0);
        $monthlyTrend = [];
        if ($trendMonths > 0) {
            $trendMonths = max(1, min(24, $trendMonths));
            $trendSql = "SELECT DATE_FORMAT(srvy_rpl_dt, '%Y-%m') AS month_key, COUNT(*) AS response_count
                         FROM srvy_responses WHERE " . $where . "
                         GROUP BY DATE_FORMAT(srvy_rpl_dt, '%Y-%m')
                         ORDER BY month_key DESC LIMIT " . $trendMonths;
            $trendStmt = $con->prepare($trendSql);
            analyticsBind($trendStmt, $types, $values);
            $trendStmt->execute();
            $monthlyTrend = array_reverse($trendStmt->get_result()->fetch_all(MYSQLI_ASSOC));
            $trendStmt->close();
        }

        Response::success('Facility response summary loaded.', [
            'summary' => ['total_responses' => $totalResponses, 'score' => null, 'facility_count' => count($facilities)],
            'facilities' => $facilities,
            'monthly_trend' => $monthlyTrend,
            'departments' => [],
            'indicators' => [],
            'categories' => [],
            'ignored_legacy_groups' => 0,
            'filters' => ['facility_nin' => $facilityNin, 'department_id' => $departmentId, 'from' => $from, 'to' => $to],
        ]);
    }

    $facilitySummary = [];
    $departmentSummary = [];
    $indicators = [];
    $categories = [];
    $totalResponses = 0;
    $weightedScore = 0.0;
    $ignoredLegacyGroups = 0;

    foreach ($groups as $group) {
        $nin = trim((string) $group['hospital_nin']);
        $dept = trim((string) $group['department_id']);
        $responseCount = (int) $group['response_count'];
        $reference = $nin . '_' . $dept;

        /*
         * Historical rows can predate department QR mapping, or can contain
         * free-text department values. They must not prevent valid current
         * QR feedback from being displayed.
         */
        if (!preg_match('/^\d{6,20}_\d{1,2}$/', $reference)) {
            $ignoredLegacyGroups++;
            continue;
        }

        try {
            $context = SurveyConfig::resolveReference($reference);
        } catch (InvalidArgumentException|RuntimeException) {
            $ignoredLegacyGroups++;
            continue;
        }
        $questions = SurveyConfig::questions($context, 1);
        $fields = [];
        foreach ($questions as $question) {
            $questionNumber = (int) $question['qn'];
            if (analyticsReportType($question) === 'rating' && $questionNumber >= 1 && $questionNumber <= 30) {
                $columnIndex = $questionNumber - 1;
                $fields[] = 'AVG(CAST(srvy_Q' . $columnIndex . ' AS DECIMAL(8,2))) AS q' . $questionNumber;
            }
        }
        [$scoreWhere, $scoreTypes, $scoreValues] = analyticsWhere($nin, $dept, $from, $to);
        $scores = [];
        if ($fields !== []) {
            $scoreSql = 'SELECT ' . implode(', ', $fields) . ' FROM srvy_responses WHERE ' . $scoreWhere;
            $scoreStmt = $con->prepare($scoreSql);
            analyticsBind($scoreStmt, $scoreTypes, $scoreValues);
            $scoreStmt->execute();
            $scores = $scoreStmt->get_result()->fetch_assoc() ?: [];
            $scoreStmt->close();
        }

        $departmentScoreTotal = 0.0;
        $departmentScoreCount = 0;
        foreach ($questions as $question) {
            if (analyticsReportType($question) !== 'rating') continue;
            $questionNumber = (int) $question['qn'];
            $optionCount = count($question['options'] ?? []);
            $scoreKey = 'q' . $questionNumber;
            if ($optionCount < 2 || !array_key_exists($scoreKey, $scores) || $scores[$scoreKey] === null) continue;
            $average = (float) $scores[$scoreKey];
            /*
             * Public-survey answers store a one-based option value. A
             * five-option response therefore maps directly: 1 = 1/5 and
             * 5 = 5/5. Other option counts are normalized to /5.
             */
            $scoreOutOfFive = round(($average / $optionCount) * 5, 1);
            $indicators[] = [
                'facility_nin' => $nin,
                'facility_name' => $facilityNames[$nin] ?? $nin,
                'department_id' => (int) $dept,
                'department_name' => $departmentNames[$dept] ?? ('Department ' . $dept),
                'indicator_id' => 'Q' . $questionNumber,
                'indicator_name' => (string) ($question['ques'] ?? ('Question ' . $questionNumber)),
                'responses' => $responseCount,
                'score' => $scoreOutOfFive,
                'max_score' => 5,
            ];
            $departmentScoreTotal += $scoreOutOfFive;
            $departmentScoreCount++;
        }

        foreach ($questions as $question) {
            if (!$includeCategories || analyticsReportType($question) !== 'category') continue;
            $questionNumber = (int) $question['qn'];
            if ($questionNumber < 1 || $questionNumber > 30) continue;
            $column = 'srvy_Q' . ($questionNumber - 1);
            $categorySql = 'SELECT ' . $column . ' AS answer_value, COUNT(*) AS response_count
                            FROM srvy_responses WHERE ' . $scoreWhere . ' AND ' . $column . ' IS NOT NULL
                            GROUP BY ' . $column . ' ORDER BY ' . $column;
            $categoryStmt = $con->prepare($categorySql);
            analyticsBind($categoryStmt, $scoreTypes, $scoreValues);
            $categoryStmt->execute();
            $rows = $categoryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $categoryStmt->close();
            $total = array_sum(array_map(static fn(array $row): int => (int) $row['response_count'], $rows));
            $options = $question['options'] ?? [];
            $items = [];
            foreach ($rows as $row) {
                $answerValue = (int) $row['answer_value'];
                $option = $options[$answerValue - 1] ?? null;
                $items[] = [
                    'value' => $answerValue,
                    'label' => is_array($option) ? (string) ($option['text'] ?? ('Option ' . $answerValue)) : ('Option ' . $answerValue),
                    'count' => (int) $row['response_count'],
                    'percentage' => $total > 0 ? round(((int) $row['response_count'] / $total) * 100, 1) : 0.0,
                ];
            }
            $categories[] = [
                'facility_nin' => $nin,
                'facility_name' => $facilityNames[$nin] ?? $nin,
                'department_id' => (int) $dept,
                'department_name' => $departmentNames[$dept] ?? ('Department ' . $dept),
                'question_id' => 'Q' . $questionNumber,
                'question_name' => (string) ($question['ques'] ?? ('Question ' . $questionNumber)),
                'total_responses' => $total,
                'items' => $items,
            ];
        }
        $departmentScore = $departmentScoreCount > 0 ? round($departmentScoreTotal / $departmentScoreCount, 1) : null;
        $departmentRow = [
            'facility_nin' => $nin,
            'facility_name' => $facilityNames[$nin] ?? $nin,
            'department_id' => (int) $dept,
            'department_name' => $departmentNames[$dept] ?? ('Department ' . $dept),
            'responses' => $responseCount,
            'score' => $departmentScore,
            'last_response' => $group['last_response'],
        ];
        $departmentSummary[] = $departmentRow;
        if (!isset($facilitySummary[$nin])) $facilitySummary[$nin] = ['facility_nin' => $nin, 'facility_name' => $facilityNames[$nin] ?? $nin, 'responses' => 0, 'score_total' => 0.0, 'score_count' => 0];
        $facilitySummary[$nin]['responses'] += $responseCount;
        if ($departmentScore !== null) { $facilitySummary[$nin]['score_total'] += $departmentScore * $responseCount; $facilitySummary[$nin]['score_count'] += $responseCount; }
        $totalResponses += $responseCount;
        if ($departmentScore !== null) $weightedScore += $departmentScore * $responseCount;
    }

    $facilities = array_values(array_map(static function (array $row): array {
        $row['score'] = $row['score_count'] > 0 ? round($row['score_total'] / $row['score_count'], 1) : null;
        unset($row['score_total'], $row['score_count']);
        return $row;
    }, $facilitySummary));

    Response::success('Feedback analytics loaded.', [
        'summary' => ['total_responses' => $totalResponses, 'score' => $totalResponses > 0 ? round($weightedScore / $totalResponses, 1) : null, 'facility_count' => count($facilities)],
        'facilities' => $facilities,
        'departments' => $departmentSummary,
        'indicators' => $indicators,
        'categories' => $categories,
        'ignored_legacy_groups' => $ignoredLegacyGroups,
        'filters' => ['facility_nin' => $facilityNin, 'department_id' => $departmentId, 'from' => $from, 'to' => $to],
    ]);
} catch (Throwable $exception) {
    Event::dispatch('analytics.summary.failed', [
        'exception_class' => get_class($exception),
        'exception_message' => $exception->getMessage(),
        'exception_file' => basename($exception->getFile()),
        'exception_line' => $exception->getLine(),
    ]);
    ErrorHandler::log($exception, ['endpoint' => 'analytics.summary', 'user_id' => SessionManager::userId()]);
    Response::serverError();
}

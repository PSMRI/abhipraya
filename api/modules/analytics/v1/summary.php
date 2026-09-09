<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';
require_once dirname(__DIR__, 3) . '/helpers/RatingScale.php';
require_once dirname(__DIR__, 3) . '/helpers/AccessScope.php';

Security::requireMethod('GET');
SessionManager::requireLogin();

if (!in_array(SessionManager::roleId(), [1, 2, 3, 7, 8], true)) {
    Response::forbidden('Your role is not allowed to view feedback analytics.');
}

function analyticsFilters(): array
{
    $facilityNin = trim((string) ($_GET['facility_nin'] ?? ''));
    $departmentId = trim((string) ($_GET['department_id'] ?? ''));
    $from = trim((string) ($_GET['from'] ?? ''));
    $to = trim((string) ($_GET['to'] ?? ''));
    $surveyVersion = trim((string) ($_GET['survey_version'] ?? ''));
    $districtId = trim((string) ($_GET['district_id'] ?? ''));

    if ($facilityNin !== '' && !preg_match('/^\d{6,20}$/', $facilityNin)) {
        Response::validation(['facility_nin' => 'Select a valid facility.']);
    }
    if ($departmentId !== '' && (!ctype_digit($departmentId) || (int) $departmentId < 1 || (int) $departmentId > 30)) {
        Response::validation(['department_id' => 'Select a valid department.']);
    }
    if ($surveyVersion !== '' && !preg_match('/^\d+\.\d+(?:\.\d+)?$/', $surveyVersion)) {
        Response::validation(['survey_version' => 'Select a valid survey version.']);
    }
    if ($districtId !== '' && !preg_match('/^[A-Za-z0-9_-]{1,30}$/', $districtId)) {
        Response::validation(['district_id' => 'Select a valid district.']);
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
    return [$facilityNin, $departmentId, $from, $to, $surveyVersion, $districtId];
}

function analyticsLanguage(): int
{
    $language = (int) ($_GET['lang'] ?? 1);
    return in_array($language, [1, 2], true) ? $language : 1;
}

function analyticsWhere(string $facilityNin, string $departmentId, string $from, string $to, string $districtId = ''): array
{
    $where = [];
    $types = '';
    $values = [];
    if ($facilityNin !== '') { $where[] = 'hospital_nin = ?'; $types .= 's'; $values[] = $facilityNin; }
    if ($departmentId !== '') { $where[] = 'department_id = ?'; $types .= 's'; $values[] = $departmentId; }
    if ($from !== '') { $where[] = 'srvy_rpl_dt >= ?'; $types .= 's'; $values[] = $from . ' 00:00:00'; }
    if ($to !== '') { $where[] = 'srvy_rpl_dt < DATE_ADD(?, INTERVAL 1 DAY)'; $types .= 's'; $values[] = $to; }
    if ($districtId !== '') {
        $districtFacilities = [];
        foreach (SurveyConfig::facilities() as $facility) {
            if ((string) ($facility['districtCode'] ?? '') === $districtId) $districtFacilities[] = (string) ($facility['facilityNIN'] ?? '');
        }
        if ($districtFacilities === []) {
            $where[] = '1 = 0';
        } else {
            $where[] = 'hospital_nin IN (' . implode(',', array_fill(0, count($districtFacilities), '?')) . ')';
            $types .= str_repeat('s', count($districtFacilities));
            foreach ($districtFacilities as $districtFacility) $values[] = $districtFacility;
        }
    }
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
    $type = strtolower(trim((string) ($question['report_type'] ?? 'rating')));
    return in_array($type, ['rating', 'category', 'binary', 'multi_category', 'availability', 'severity', 'demographic', 'consent', 'numeric', 'duration', 'date', 'text', 'ranking', 'matrix_rating', 'file'], true) ? $type : 'rating';
}

/** @return array{type:string,value:string} */
function analyticsIcon(array $question): array
{
    $icon = trim((string) ($question['icon'] ?? ''));
    if (
        $icon !== ''
        && !str_contains($icon, '..')
        && preg_match('#^assets/img/[A-Za-z0-9_./-]+\.(?:png|svg|webp)$#i', $icon)
    ) {
        return ['type' => 'image', 'value' => '/api/' . ltrim($icon, '/')];
    }
    if ($icon !== '' && mb_strlen($icon) <= 12 && !preg_match('/[\x00-\x1F\x7F]/u', $icon)) {
        return ['type' => 'text', 'value' => $icon];
    }

    $fallbacks = [
        'rating' => 'bi-star-fill',
        'binary' => 'bi-toggle-on',
        'category' => 'bi-bar-chart-fill',
        'multi_category' => 'bi-list-check',
        'numeric' => 'bi-123',
        'text' => 'bi-chat-left-text-fill',
        'duration' => 'bi-clock-history',
        'date' => 'bi-calendar3',
        'availability' => 'bi-box-seam-fill',
        'severity' => 'bi-exclamation-triangle-fill',
        'demographic' => 'bi-people-fill',
        'consent' => 'bi-shield-check',
        'ranking' => 'bi-sort-numeric-down',
        'matrix_rating' => 'bi-grid-3x3-gap-fill',
        'file' => 'bi-paperclip',
    ];
    $type = analyticsReportType($question);
    return ['type' => 'bootstrap', 'value' => $fallbacks[$type] ?? 'bi-ui-checks'];
}

/** @return array{visual:string,primary_metric:string} */
function analyticsPresentation(array $question): array
{
    $type = analyticsReportType($question);
    $defaults = [
        'rating' => ['visual' => 'score_stars', 'primary_metric' => 'average_score'],
        'binary' => ['visual' => 'stacked_bar', 'primary_metric' => 'positive_percentage'],
        'category' => ['visual' => 'distribution_bar', 'primary_metric' => 'category_distribution'],
        'multi_category' => ['visual' => 'distribution_bar', 'primary_metric' => 'option_percentage'],
        'numeric' => ['visual' => 'statistics', 'primary_metric' => 'average'],
        'text' => ['visual' => 'response_count', 'primary_metric' => 'response_count'],
        'duration' => ['visual' => 'duration_bands', 'primary_metric' => 'median_duration'],
        'date' => ['visual' => 'date_distribution', 'primary_metric' => 'response_count'],
        'availability' => ['visual' => 'stacked_bar', 'primary_metric' => 'available_percentage'],
        'severity' => ['visual' => 'severity_distribution', 'primary_metric' => 'priority_percentage'],
        'demographic' => ['visual' => 'distribution_bar', 'primary_metric' => 'category_distribution'],
        'consent' => ['visual' => 'stacked_bar', 'primary_metric' => 'consent_percentage'],
        'ranking' => ['visual' => 'ranking_bar', 'primary_metric' => 'average_rank'],
        'matrix_rating' => ['visual' => 'heatmap', 'primary_metric' => 'item_average'],
        'file' => ['visual' => 'review_queue', 'primary_metric' => 'submission_count'],
    ];
    return $defaults[$type];
}

function analyticsSurveyVersionColumnAvailable(mysqli $connection): bool
{
    $result = $connection->query(
        "SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'srvy_responses'
           AND COLUMN_NAME = 'survey_version'"
    );
    return (int) ($result->fetch_assoc()['total'] ?? 0) === 1;
}

try {
    [$facilityNin, $departmentId, $from, $to, $surveyVersionFilter, $districtId] = analyticsFilters();
    $analyticsLanguage = analyticsLanguage();
    [$where, $types, $values] = analyticsWhere($facilityNin, $departmentId, $from, $to, $districtId);
    $allowedNins = AccessScope::facilityNins();
    if ($allowedNins !== null) {
        if ($allowedNins === []) {
            $where .= ' AND 1 = 0';
        } else {
            $where .= ' AND hospital_nin IN (' . implode(',', array_fill(0, count($allowedNins), '?')) . ')';
            $types .= str_repeat('s', count($allowedNins));
            array_push($values, ...$allowedNins);
        }
    }
    $versioningEnabled = analyticsSurveyVersionColumnAvailable($con);
    $availableVersions = SurveyConfig::surveyVersions(
        $departmentId !== '' ? (int) $departmentId : null
    );
    if ($versioningEnabled) {
        $versionSql = "SELECT DISTINCT COALESCE(NULLIF(survey_version, ''), '1.0') AS survey_version
                       FROM srvy_responses
                       WHERE " . $where . "
                       ORDER BY survey_version DESC";
        $versionStmt = $con->prepare($versionSql);
        analyticsBind($versionStmt, $types, $values);
        $versionStmt->execute();
        foreach ($versionStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $versionRow) {
            $storedVersion = trim((string) ($versionRow['survey_version'] ?? ''));
            if (preg_match('/^\d+\.\d+(?:\.\d+)?$/', $storedVersion)) {
                $availableVersions[] = $storedVersion;
            }
        }
        $versionStmt->close();
    }
    $availableVersions = array_values(array_unique($availableVersions));
    usort(
        $availableVersions,
        static fn(string $left, string $right): int => version_compare($right, $left)
    );
    if ($surveyVersionFilter !== '') {
        if ($versioningEnabled) {
            $where .= " AND COALESCE(NULLIF(survey_version, ''), '1.0') = ?";
            $types .= 's';
            $values[] = $surveyVersionFilter;
        } elseif ($surveyVersionFilter !== '1.0') {
            $where .= ' AND 1 = 0';
        }
    }
    /*
     * Type-specific distributions are normally facility-scoped to keep the
     * interactive dashboard fast. Reports can explicitly request an
     * all-scope calculation when an official generates a distribution report.
     */
    $includeCategories = $facilityNin !== ''
        || (string) ($_GET['include_distributions'] ?? '') === '1';

    $versionSelect = $versioningEnabled
        ? "COALESCE(NULLIF(survey_version, ''), '1.0')"
        : "'1.0'";
    $summarySql = "SELECT hospital_nin,
                          department_id,
                          " . $versionSelect . " AS survey_version,
                          COUNT(*) AS response_count,
                          MAX(srvy_rpl_dt) AS last_response
                   FROM srvy_responses WHERE " . $where . "
                   GROUP BY hospital_nin, department_id, " . $versionSelect . "
                   ORDER BY response_count DESC";
    $summaryStmt = $con->prepare($summarySql);
    analyticsBind($summaryStmt, $types, $values);
    $summaryStmt->execute();
    $groups = $summaryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $summaryStmt->close();

    $facilityNames = [];
    $facilityDistricts = [];
    $facilityTypes = [];
    foreach (SurveyConfig::facilities() as $facility) {
        $nin = (string) ($facility['facilityNIN'] ?? '');
        $facilityNames[$nin] = (string) ($facility['facilityName'] ?? $nin);
        $districtCode = trim((string) ($facility['districtCode'] ?? ''));
        $districtName = '';
        $address = (string) ($facility['facilityAddress'] ?? '');
        if (preg_match('/^(.*)\\s+DISTRICT\\b/i', $address, $matches)) {
            $parts = explode(',', trim($matches[1]));
            $districtName = trim((string) end($parts));
        }
        $facilityDistricts[$nin] = ['district_code' => $districtCode !== '' ? $districtCode : 'unknown', 'district_name' => $districtName !== '' ? $districtName : ($districtCode !== '' ? 'District ' . $districtCode : 'District not specified')];
        $facilityTypes[$nin] = trim((string) ($facility['facilityType'] ?? '')) ?: 'Other';
    }
    $departmentNames = [];
    foreach (SurveyConfig::departments($analyticsLanguage) as $department) $departmentNames[(string) $department['departmentId']] = (string) $department['departmentName'];

    if ((string) ($_GET['summary_only'] ?? '') === '1') {
        $facilityCounts = [];
        foreach ($groups as $group) {
            $nin = trim((string) ($group['hospital_nin'] ?? ''));
            if ($nin === '') continue;
            if (!isset($facilityCounts[$nin])) {
                $facilityCounts[$nin] = [
                    'facility_nin' => $nin,
                    'facility_name' => $facilityNames[$nin] ?? $nin,
                    'district_code' => $facilityDistricts[$nin]['district_code'] ?? 'unknown',
                    'district_name' => $facilityDistricts[$nin]['district_name'] ?? 'District not specified',
                    'responses' => 0,
                    'score' => null,
                ];
            }
            $facilityCounts[$nin]['responses'] += (int) ($group['response_count'] ?? 0);
        }
        $facilities = array_values($facilityCounts);
        usort($facilities, static fn(array $left, array $right): int => $right['responses'] <=> $left['responses']);
        $totalResponses = array_sum(array_map(static fn(array $row): int => (int) $row['responses'], $facilities));
        $facilityTypeCounts = [];
        foreach ($facilityTypes as $type) {
            $facilityTypeCounts[$type] = $facilityTypeCounts[$type] ?? ['type' => $type, 'configured' => 0, 'reporting' => 0];
            $facilityTypeCounts[$type]['configured']++;
        }
        foreach ($facilityCounts as $nin => $_) {
            $type = $facilityTypes[$nin] ?? 'Other';
            $facilityTypeCounts[$type] = $facilityTypeCounts[$type] ?? ['type' => $type, 'configured' => 0, 'reporting' => 0];
            $facilityTypeCounts[$type]['reporting']++;
        }
        $facilityTypeCounts = array_values($facilityTypeCounts);
        usort($facilityTypeCounts, static fn(array $left, array $right): int => [$right['reporting'], $right['configured'], $left['type']] <=> [$left['reporting'], $left['configured'], $right['type']]);

        $trendMonths = (int) ($_GET['trend_months'] ?? 0);
        $monthlyTrend = [];
        if ($trendMonths > 0) {
            $trendMonths = max(1, min(24, $trendMonths));
            $trendSql = "SELECT DATE_FORMAT(srvy_rpl_dt, '%Y-%m') AS month_key,
                                COUNT(*) AS response_count,
                                COUNT(DISTINCT hospital_nin) AS facility_count
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
            'summary' => ['total_responses' => $totalResponses, 'score' => null, 'facility_count' => count($facilities), 'configured_facility_count' => count(SurveyConfig::facilities()), 'facility_type_counts' => $facilityTypeCounts],
            'facilities' => $facilities,
            'monthly_trend' => $monthlyTrend,
            'departments' => [],
            'indicators' => [],
            'categories' => [],
            'available_versions' => $availableVersions,
            'ignored_legacy_groups' => 0,
            'filters' => ['facility_nin' => $facilityNin, 'district_id' => $districtId, 'department_id' => $departmentId, 'survey_version' => $surveyVersionFilter, 'from' => $from, 'to' => $to],
        ]);
    }

    $facilitySummary = [];
    $departmentSummary = [];
    $indicators = [];
    $categories = [];
    $totalResponses = 0;
    $weightedScore = 0.0;
    $ignoredLegacyGroups = 0;

    /*
     * Load survey metadata once, then calculate every facility score for a
     * department in one grouped query. This avoids one database round trip
     * per facility/department pair on state-level dashboards.
     */
    $questionsByReference = [];
    $ratingQuestionsByVersion = [];
    foreach ($groups as $group) {
        $nin = trim((string) ($group['hospital_nin'] ?? ''));
        $dept = trim((string) ($group['department_id'] ?? ''));
        $surveyVersion = trim((string) ($group['survey_version'] ?? '1.0')) ?: '1.0';
        $reference = $nin . '_' . $dept;
        $versionReference = $reference . '@' . $surveyVersion;
        if (!preg_match('/^\d{6,20}_\d{1,2}$/', $reference)) continue;
        try {
            $context = SurveyConfig::resolveReference($reference, $surveyVersion);
            $questions = SurveyConfig::questions($context, $analyticsLanguage);
            $questionsByReference[$versionReference] = $questions;
            $ratingQuestionsByVersion[$dept . '@' . $surveyVersion] = $questions;
        } catch (InvalidArgumentException|RuntimeException) {
            continue;
        }
    }

    $scoresByReference = [];
    foreach ($ratingQuestionsByVersion as $departmentVersion => $questions) {
        [$dept, $surveyVersion] = explode('@', $departmentVersion, 2);
        $fields = [];
        foreach ($questions as $question) {
            $questionNumber = (int) ($question['qn'] ?? 0);
            if (analyticsReportType($question) !== 'rating' || $questionNumber < 1 || $questionNumber > 31) continue;
            $column = 'srvy_Q' . ($questionNumber - 1);
            $scoreExpression = RatingScale::sqlCase($column, $question);
            $ratedValues = array_keys(array_filter(
                RatingScale::optionScores($question),
                static fn(?float $score): bool => $score !== null
            ));
            if ($ratedValues === []) continue;
            $fields[] = 'AVG(' . $scoreExpression . ') AS q' . $questionNumber;
            $fields[] = 'SUM(' . $column . ' IN (' . implode(',', array_map('intval', $ratedValues))
                . ')) AS q' . $questionNumber . '_count';
        }
        if ($fields === []) continue;

        [$batchWhere, $batchTypes, $batchValues] = analyticsWhere($facilityNin, (string) $dept, $from, $to);
        if ($versioningEnabled) {
            $batchWhere .= " AND COALESCE(NULLIF(survey_version, ''), '1.0') = ?";
            $batchTypes .= 's';
            $batchValues[] = $surveyVersion;
        }
        $batchSql = 'SELECT hospital_nin, department_id, ' . implode(', ', $fields)
            . ' FROM srvy_responses WHERE ' . $batchWhere
            . ' GROUP BY hospital_nin, department_id';
        $batchStmt = $con->prepare($batchSql);
        analyticsBind($batchStmt, $batchTypes, $batchValues);
        $batchStmt->execute();
        foreach ($batchStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $scoreRow) {
            $scoreReference = trim((string) $scoreRow['hospital_nin'])
                . '_' . trim((string) $scoreRow['department_id'])
                . '@' . $surveyVersion;
            $scoresByReference[$scoreReference] = $scoreRow;
        }
        $batchStmt->close();
    }

    foreach ($groups as $group) {
        $nin = trim((string) $group['hospital_nin']);
        $dept = trim((string) $group['department_id']);
        $surveyVersion = trim((string) ($group['survey_version'] ?? '1.0')) ?: '1.0';
        $responseCount = (int) $group['response_count'];
        $reference = $nin . '_' . $dept;
        $versionReference = $reference . '@' . $surveyVersion;

        /*
         * Historical rows can predate department QR mapping, or can contain
         * free-text department values. They must not prevent valid current
         * QR feedback from being displayed.
         */
        if (!preg_match('/^\d{6,20}_\d{1,2}$/', $reference)) {
            $ignoredLegacyGroups++;
            continue;
        }

        if (!isset($questionsByReference[$versionReference])) {
            $ignoredLegacyGroups++;
            continue;
        }
        $questions = $questionsByReference[$versionReference];
        [$scoreWhere, $scoreTypes, $scoreValues] = analyticsWhere($nin, $dept, $from, $to);
        if ($versioningEnabled) {
            $scoreWhere .= " AND COALESCE(NULLIF(survey_version, ''), '1.0') = ?";
            $scoreTypes .= 's';
            $scoreValues[] = $surveyVersion;
        }
        $scores = $scoresByReference[$versionReference] ?? [];

        $departmentScoreTotal = 0.0;
        $departmentScoreCount = 0;
        foreach ($questions as $question) {
            if (analyticsReportType($question) !== 'rating') continue;
            $questionNumber = (int) $question['qn'];
            $optionCount = count($question['options'] ?? []);
            $scoreKey = 'q' . $questionNumber;
            if ($optionCount < 2 || !array_key_exists($scoreKey, $scores) || $scores[$scoreKey] === null) continue;
            $rawScoreOutOfFive = (float) $scores[$scoreKey];
            $scoreOutOfFive = round($rawScoreOutOfFive, 1);
            $indicators[] = [
                'facility_nin' => $nin,
                'facility_name' => $facilityNames[$nin] ?? $nin,
                'department_id' => (int) $dept,
                'department_name' => $departmentNames[$dept] ?? ('Department ' . $dept),
                'indicator_id' => (string) ($question['indicator_key'] ?? ('DEPT_' . $dept . '_Q' . $questionNumber)),
                'question_id' => (string) ($question['question_id'] ?? ('Q' . $questionNumber)),
                'question_key' => 'srvy_Q' . ($questionNumber - 1),
                'indicator_name' => (string) ($question['ques'] ?? ('Question ' . $questionNumber)),
                'report_type' => analyticsReportType($question),
                'icon' => analyticsIcon($question),
                'presentation' => analyticsPresentation($question),
                'survey_version' => $surveyVersion,
                'responses' => (int) ($scores[$scoreKey . '_count'] ?? $responseCount),
                'score' => $scoreOutOfFive,
                'score_raw' => round($rawScoreOutOfFive, 4),
                'max_score' => 5,
            ];
            $departmentScoreTotal += $rawScoreOutOfFive;
            $departmentScoreCount++;
        }

        foreach ($questions as $question) {
            $configuredType = analyticsReportType($question);
            $hasOptions = is_array($question['options'] ?? null) && count($question['options']) > 0;
            if (!$includeCategories || !in_array($configuredType, ['category', 'binary', 'availability', 'severity', 'demographic', 'consent', 'duration'], true)) continue;
            /* A duration with no configured bands is processed as a numeric
               duration below; option-based durations remain distributions. */
            if ($configuredType === 'duration' && !$hasOptions) continue;
            $questionNumber = (int) $question['qn'];
            if ($questionNumber < 1 || $questionNumber > 31) continue;
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
            $positiveValues = array_map(
                'strval',
                array_filter(
                    (array) ($question['positive_values'] ?? [$question['positive_value'] ?? null]),
                    static fn(mixed $value): bool => $value !== null && $value !== ''
                )
            );
            $negativeValues = array_map(
                'strval',
                array_filter(
                    (array) ($question['negative_values'] ?? [$question['negative_value'] ?? null]),
                    static fn(mixed $value): bool => $value !== null && $value !== ''
                )
            );
            $items = [];
            foreach ($rows as $row) {
                $rawAnswer = trim((string) ($row['answer_value'] ?? ''));
                $answerValue = ctype_digit($rawAnswer) ? (int) $rawAnswer : $rawAnswer;
                $option = null;
                foreach ($options as $index => $candidate) {
                    $candidateValue = is_array($candidate) && array_key_exists('value', $candidate)
                        ? (string) $candidate['value']
                        : (string) ($index + 1);
                    if ($candidateValue === $rawAnswer) {
                        $option = $candidate;
                        break;
                    }
                }
                $optionSentiment = is_array($option) ? (string) ($option['sentiment'] ?? '') : '';
                if ($optionSentiment === '' && in_array($rawAnswer, $positiveValues, true)) {
                    $optionSentiment = 'positive';
                }
                if ($optionSentiment === '' && in_array($rawAnswer, $negativeValues, true)) {
                    $optionSentiment = 'negative';
                }
                $items[] = [
                    'value' => $answerValue,
                    'label' => is_array($option) ? (string) ($option['text'] ?? $rawAnswer) : $rawAnswer,
                    'count' => (int) $row['response_count'],
                    'percentage' => $total > 0 ? round(((int) $row['response_count'] / $total) * 100, 1) : 0.0,
                    'sentiment' => $optionSentiment,
                    'status' => is_array($option) ? (string) ($option['status'] ?? '') : '',
                    'consent' => is_array($option) && array_key_exists('consent', $option)
                        ? (bool) $option['consent']
                        : null,
                ];
            }
            $categories[] = [
                'facility_nin' => $nin,
                'facility_name' => $facilityNames[$nin] ?? $nin,
                'department_id' => (int) $dept,
                'department_name' => $departmentNames[$dept] ?? ('Department ' . $dept),
                'question_id' => (string) ($question['question_id'] ?? ('Q' . $questionNumber)),
                'indicator_key' => (string) ($question['indicator_key'] ?? ('DEPT_' . $dept . '_Q' . $questionNumber)),
                'question_key' => 'srvy_Q' . ($questionNumber - 1),
                'question_name' => (string) ($question['ques'] ?? ('Question ' . $questionNumber)),
                'report_type' => analyticsReportType($question),
                'icon' => analyticsIcon($question),
                'presentation' => analyticsPresentation($question),
                'survey_version' => $surveyVersion,
                'total_responses' => $total,
                'items' => $items,
            ];
        }

        foreach ($questions as $question) {
            $reportType = analyticsReportType($question);
            $hasOptions = is_array($question['options'] ?? null) && count($question['options']) > 0;
            if (!$includeCategories || !in_array($reportType, ['numeric', 'text', 'date', 'multi_category', 'ranking', 'matrix_rating', 'file', 'duration'], true)) {
                continue;
            }
            $questionNumber = (int) $question['qn'];
            if ($questionNumber < 1 || $questionNumber > 31) continue;
            $column = 'srvy_Q' . ($questionNumber - 1);
            $analysis = [
                'facility_nin' => $nin,
                'facility_name' => $facilityNames[$nin] ?? $nin,
                'department_id' => (int) $dept,
                'department_name' => $departmentNames[$dept] ?? ('Department ' . $dept),
                'question_id' => (string) ($question['question_id'] ?? ('Q' . $questionNumber)),
                'indicator_key' => (string) ($question['indicator_key'] ?? ('DEPT_' . $dept . '_Q' . $questionNumber)),
                'question_key' => $column,
                'question_name' => (string) ($question['ques'] ?? ('Question ' . $questionNumber)),
                'report_type' => $reportType,
                'icon' => analyticsIcon($question),
                'presentation' => analyticsPresentation($question),
                'survey_version' => $surveyVersion,
                'unit' => (string) ($question['unit'] ?? ''),
                'total_responses' => 0,
                'items' => [],
                'statistics' => null,
            ];

            if ($reportType === 'duration' && $hasOptions) {
                continue;
            }

            if (in_array($reportType, ['numeric', 'duration'], true)) {
                $numericSql = "SELECT COUNT(*) AS response_count,
                                      AVG(CAST(REPLACE(" . $column . ", ',', '') AS DECIMAL(18,2))) AS average_value,
                                      MIN(CAST(REPLACE(" . $column . ", ',', '') AS DECIMAL(18,2))) AS minimum_value,
                                      MAX(CAST(REPLACE(" . $column . ", ',', '') AS DECIMAL(18,2))) AS maximum_value,
                                      SUM(CAST(REPLACE(" . $column . ", ',', '') AS DECIMAL(18,2))) AS total_value
                               FROM srvy_responses
                               WHERE " . $scoreWhere . "
                                 AND TRIM(" . $column . ") REGEXP '^-?[0-9]+([.][0-9]+)?$'";
                $numericStmt = $con->prepare($numericSql);
                analyticsBind($numericStmt, $scoreTypes, $scoreValues);
                $numericStmt->execute();
                $statistics = $numericStmt->get_result()->fetch_assoc() ?: [];
                $numericStmt->close();
                $analysis['total_responses'] = (int) ($statistics['response_count'] ?? 0);
                $analysis['statistics'] = [
                    'count' => (int) ($statistics['response_count'] ?? 0),
                    'average' => isset($statistics['average_value']) ? round((float) $statistics['average_value'], 2) : null,
                    'minimum' => isset($statistics['minimum_value']) ? (float) $statistics['minimum_value'] : null,
                    'maximum' => isset($statistics['maximum_value']) ? (float) $statistics['maximum_value'] : null,
                    'total' => isset($statistics['total_value']) ? round((float) $statistics['total_value'], 2) : null,
                ];
                if ($reportType === 'duration' && $analysis['total_responses'] > 0) {
                    $medianSql = "SELECT CAST(REPLACE(" . $column . ", ',', '') AS DECIMAL(18,2)) AS duration_value
                                  FROM srvy_responses
                                  WHERE " . $scoreWhere . "
                                    AND TRIM(" . $column . ") REGEXP '^-?[0-9]+([.][0-9]+)?$'
                                  ORDER BY duration_value";
                    $medianStmt = $con->prepare($medianSql);
                    analyticsBind($medianStmt, $scoreTypes, $scoreValues);
                    $medianStmt->execute();
                    $durationValues = array_map(
                        static fn(array $row): float => (float) $row['duration_value'],
                        $medianStmt->get_result()->fetch_all(MYSQLI_ASSOC)
                    );
                    $medianStmt->close();
                    $middle = intdiv(count($durationValues), 2);
                    $analysis['statistics']['median'] = count($durationValues) % 2 === 0
                        ? round(($durationValues[$middle - 1] + $durationValues[$middle]) / 2, 2)
                        : $durationValues[$middle];
                }
                $categories[] = $analysis;
                continue;
            }

            if (in_array($reportType, ['text', 'file'], true)) {
                $countSql = 'SELECT COUNT(*) AS response_count
                             FROM srvy_responses WHERE ' . $scoreWhere
                    . ' AND ' . $column . " IS NOT NULL AND TRIM(" . $column . ") <> ''";
                $countStmt = $con->prepare($countSql);
                analyticsBind($countStmt, $scoreTypes, $scoreValues);
                $countStmt->execute();
                $count = (int) ($countStmt->get_result()->fetch_assoc()['response_count'] ?? 0);
                $countStmt->close();
                $analysis['total_responses'] = $count;
                $analysis['statistics'] = ['count' => $count];
                $categories[] = $analysis;
                continue;
            }

            if ($reportType === 'date') {
                $dateSql = 'SELECT LEFT(' . $column . ', 7) AS answer_value, COUNT(*) AS response_count
                            FROM srvy_responses WHERE ' . $scoreWhere
                    . " AND " . $column . " REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                            GROUP BY LEFT(" . $column . ', 7) ORDER BY answer_value';
                $dateStmt = $con->prepare($dateSql);
                analyticsBind($dateStmt, $scoreTypes, $scoreValues);
                $dateStmt->execute();
                $dateRows = $dateStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $dateStmt->close();
                $total = array_sum(array_map(static fn(array $row): int => (int) $row['response_count'], $dateRows));
                $analysis['total_responses'] = $total;
                $analysis['items'] = array_map(static fn(array $row): array => [
                    'value' => (string) $row['answer_value'],
                    'label' => (string) $row['answer_value'],
                    'count' => (int) $row['response_count'],
                    'percentage' => $total > 0 ? round(((int) $row['response_count'] / $total) * 100, 1) : 0.0,
                ], $dateRows);
                $categories[] = $analysis;
                continue;
            }

            $rawSql = 'SELECT ' . $column . ' AS answer_value
                       FROM srvy_responses WHERE ' . $scoreWhere
                . ' AND ' . $column . " IS NOT NULL AND TRIM(" . $column . ") <> ''";
            $rawStmt = $con->prepare($rawSql);
            analyticsBind($rawStmt, $scoreTypes, $scoreValues);
            $rawStmt->execute();
            $rawRows = $rawStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $rawStmt->close();
            $options = $question['options'] ?? [];
            $labels = [];
            foreach ($options as $index => $option) {
                $value = is_array($option) ? ($option['value'] ?? ($index + 1)) : ($index + 1);
                $labels[(string) $value] = is_array($option) ? (string) ($option['text'] ?? $value) : (string) $value;
            }
            $counts = [];
            $rankTotals = [];
            $rankCounts = [];
            $matrixTotals = [];
            $matrixCounts = [];
            foreach ($rawRows as $rawRow) {
                $raw = trim((string) ($rawRow['answer_value'] ?? ''));
                $decoded = json_decode($raw, true);
                if ($reportType === 'matrix_rating' && is_array($decoded)) {
                    foreach ($decoded as $itemKey => $itemValue) {
                        if (!is_numeric($itemValue)) continue;
                        $matrixTotals[(string) $itemKey] = ($matrixTotals[(string) $itemKey] ?? 0.0) + (float) $itemValue;
                        $matrixCounts[(string) $itemKey] = ($matrixCounts[(string) $itemKey] ?? 0) + 1;
                    }
                    continue;
                }
                $values = is_array($decoded) ? array_values($decoded) : preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($values as $position => $value) {
                    $key = (string) $value;
                    if ($reportType === 'ranking') {
                        $rankTotals[$key] = ($rankTotals[$key] ?? 0) + ($position + 1);
                        $rankCounts[$key] = ($rankCounts[$key] ?? 0) + 1;
                    } else {
                        $counts[$key] = ($counts[$key] ?? 0) + 1;
                    }
                }
            }
            $analysis['total_responses'] = count($rawRows);
            if ($reportType === 'matrix_rating') {
                foreach ($matrixTotals as $key => $totalValue) {
                    $analysis['items'][] = [
                        'value' => $key,
                        'label' => $labels[$key] ?? $key,
                        'count' => $matrixCounts[$key],
                        'average' => round($totalValue / max(1, $matrixCounts[$key]), 2),
                    ];
                }
            } elseif ($reportType === 'ranking') {
                foreach ($rankTotals as $key => $totalRank) {
                    $analysis['items'][] = [
                        'value' => $key,
                        'label' => $labels[$key] ?? $key,
                        'count' => $rankCounts[$key],
                        'average_rank' => round($totalRank / max(1, $rankCounts[$key]), 2),
                    ];
                }
                usort($analysis['items'], static fn(array $left, array $right): int => $left['average_rank'] <=> $right['average_rank']);
            } else {
                foreach ($counts as $key => $count) {
                    $analysis['items'][] = [
                        'value' => $key,
                        'label' => $labels[$key] ?? $key,
                        'count' => $count,
                        'percentage' => count($rawRows) > 0 ? round(($count / count($rawRows)) * 100, 1) : 0.0,
                    ];
                }
                usort($analysis['items'], static fn(array $left, array $right): int => $right['count'] <=> $left['count']);
            }
            $categories[] = $analysis;
        }
        $departmentScore = $departmentScoreCount > 0 ? round($departmentScoreTotal / $departmentScoreCount, 1) : null;
        $departmentRow = [
            'facility_nin' => $nin,
            'facility_name' => $facilityNames[$nin] ?? $nin,
            'department_id' => (int) $dept,
            'department_name' => $departmentNames[$dept] ?? ('Department ' . $dept),
            'survey_version' => $surveyVersion,
            'responses' => $responseCount,
            'score' => $departmentScore,
            'last_response' => $group['last_response'],
        ];
        $departmentSummary[] = $departmentRow;
        if (!isset($facilitySummary[$nin])) $facilitySummary[$nin] = ['facility_nin' => $nin, 'facility_name' => $facilityNames[$nin] ?? $nin, 'district_code' => $facilityDistricts[$nin]['district_code'] ?? 'unknown', 'district_name' => $facilityDistricts[$nin]['district_name'] ?? 'District not specified', 'responses' => 0, 'score_total' => 0.0, 'score_count' => 0];
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

    /*
     * A facility + department selection receives a 12-month operational
     * series. The rating uses the same canonical question scoring as the
     * summary, then averages the valid rating-question averages per month.
     */
    $monthlyPerformance = [];
    if ($facilityNin !== '' && $departmentId !== '') {
        $monthlyBuckets = [];
        foreach ($groups as $group) {
            if (
                (string) ($group['hospital_nin'] ?? '') !== $facilityNin
                || (string) ($group['department_id'] ?? '') !== $departmentId
            ) {
                continue;
            }
            $surveyVersion = trim((string) ($group['survey_version'] ?? '1.0')) ?: '1.0';
            $versionReference = $facilityNin . '_' . $departmentId . '@' . $surveyVersion;
            $questions = $questionsByReference[$versionReference] ?? [];
            $monthlyFields = [];
            $definitions = [];
            foreach ($questions as $question) {
                $questionNumber = (int) ($question['qn'] ?? 0);
                if (
                    analyticsReportType($question) !== 'rating'
                    || $questionNumber < 1
                    || $questionNumber > 31
                ) {
                    continue;
                }
                $column = 'srvy_Q' . ($questionNumber - 1);
                $scoreKey = 'q' . $questionNumber;
                $countKey = $scoreKey . '_count';
                $ratedValues = array_keys(array_filter(
                    RatingScale::optionScores($question),
                    static fn(?float $score): bool => $score !== null
                ));
                if ($ratedValues === []) continue;
                $monthlyFields[] = 'AVG(' . RatingScale::sqlCase($column, $question) . ') AS ' . $scoreKey;
                $monthlyFields[] = 'SUM(' . $column . ' IN (' . implode(',', array_map('intval', $ratedValues))
                    . ')) AS ' . $countKey;
                $definitions[] = [
                    'indicator_id' => (string) ($question['indicator_key']
                        ?? ('DEPT_' . $departmentId . '_Q' . $questionNumber)),
                    'score_key' => $scoreKey,
                    'count_key' => $countKey,
                ];
            }
            if ($monthlyFields === []) continue;

            $versionWhere = $where;
            $versionTypes = $types;
            $versionValues = $values;
            if ($versioningEnabled) {
                $versionWhere .= " AND COALESCE(NULLIF(survey_version, ''), '1.0') = ?";
                $versionTypes .= 's';
                $versionValues[] = $surveyVersion;
            }
            $monthlySql = "SELECT DATE_FORMAT(srvy_rpl_dt, '%Y-%m') AS month_key,
                                  COUNT(*) AS response_count,
                                  " . implode(', ', $monthlyFields) . "
                           FROM srvy_responses
                           WHERE " . $versionWhere . "
                           GROUP BY DATE_FORMAT(srvy_rpl_dt, '%Y-%m')
                           ORDER BY month_key";
            $monthlyStmt = $con->prepare($monthlySql);
            analyticsBind($monthlyStmt, $versionTypes, $versionValues);
            $monthlyStmt->execute();
            $monthlyRows = $monthlyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $monthlyStmt->close();

            foreach ($monthlyRows as $monthlyRow) {
                $month = (string) ($monthlyRow['month_key'] ?? '');
                if ($month === '') continue;
                if (!isset($monthlyBuckets[$month])) {
                    $monthlyBuckets[$month] = [
                        'month_key' => $month,
                        'response_count' => 0,
                        'score_weighted' => 0.0,
                        'score_weight' => 0,
                        'indicator_score_weighted' => [],
                        'indicator_counts' => [],
                    ];
                }
                $bucket = &$monthlyBuckets[$month];
                $responseCount = (int) ($monthlyRow['response_count'] ?? 0);
                $bucket['response_count'] += $responseCount;
                $departmentScoreTotal = 0.0;
                $departmentScoreCount = 0;
                foreach ($definitions as $definition) {
                    $scoreKey = $definition['score_key'];
                    if (!array_key_exists($scoreKey, $monthlyRow) || $monthlyRow[$scoreKey] === null) {
                        continue;
                    }
                    $indicatorId = $definition['indicator_id'];
                    $score = (float) $monthlyRow[$scoreKey];
                    $validCount = (int) ($monthlyRow[$definition['count_key']] ?? 0);
                    $departmentScoreTotal += $score;
                    $departmentScoreCount++;
                    $bucket['indicator_score_weighted'][$indicatorId] =
                        ($bucket['indicator_score_weighted'][$indicatorId] ?? 0.0)
                        + ($score * $validCount);
                    $bucket['indicator_counts'][$indicatorId] =
                        ($bucket['indicator_counts'][$indicatorId] ?? 0)
                        + $validCount;
                }
                if ($departmentScoreCount > 0) {
                    $departmentAverage = $departmentScoreTotal / $departmentScoreCount;
                    $bucket['score_weighted'] += $departmentAverage * $responseCount;
                    $bucket['score_weight'] += $responseCount;
                }
                unset($bucket);
            }
        }

        ksort($monthlyBuckets);
        foreach (array_slice(array_values($monthlyBuckets), -12) as $bucket) {
            $indicatorScores = [];
            foreach ($bucket['indicator_counts'] as $indicatorId => $validCount) {
                $indicatorScores[$indicatorId] = $validCount > 0
                    ? round($bucket['indicator_score_weighted'][$indicatorId] / $validCount, 1)
                    : null;
            }
            $monthlyPerformance[] = [
                'month_key' => $bucket['month_key'],
                'response_count' => $bucket['response_count'],
                'average_score' => $bucket['score_weight'] > 0
                    ? round($bucket['score_weighted'] / $bucket['score_weight'], 1)
                    : null,
                'indicator_scores' => $indicatorScores,
                'indicator_counts' => $bucket['indicator_counts'],
            ];
        }
    }

    Response::success('Feedback analytics loaded.', [
        'summary' => ['total_responses' => $totalResponses, 'score' => $totalResponses > 0 ? round($weightedScore / $totalResponses, 1) : null, 'facility_count' => count($facilities)],
        'facilities' => $facilities,
        'departments' => $departmentSummary,
        'indicators' => $indicators,
        'categories' => $categories,
        'available_versions' => $availableVersions,
        'monthly_performance' => $monthlyPerformance,
        'ignored_legacy_groups' => $ignoredLegacyGroups,
        'filters' => ['facility_nin' => $facilityNin, 'district_id' => $districtId, 'department_id' => $departmentId, 'survey_version' => $surveyVersionFilter, 'from' => $from, 'to' => $to],
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

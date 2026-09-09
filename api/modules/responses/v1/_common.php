<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';
require_once dirname(__DIR__, 3) . '/helpers/RatingScale.php';
require_once dirname(__DIR__, 3) . '/helpers/AccessScope.php';

SessionManager::requireLogin();
if (!in_array(SessionManager::roleId(), [1, 2, 3, 7, 8], true)) {
    Response::forbidden('Your role is not allowed to view feedback responses.');
}

function feedbackFacilityMap(): array
{
    $map = [];
    foreach (AccessScope::filterFacilities(SurveyConfig::facilities()) as $facility) {
        $nin = (string) ($facility['facilityNIN'] ?? '');
        if ($nin !== '') {
            $map[$nin] = (string) ($facility['facilityName'] ?? $nin);
        }
    }
    return $map;
}

function feedbackDepartmentMap(): array
{
    $map = [];
    foreach (SurveyConfig::departments() as $department) {
        $id = (string) ($department['departmentId'] ?? '');
        if ($id !== '') {
            $map[$id] = (string) ($department['departmentName'] ?? ('Department ' . $id));
        }
    }
    return $map;
}

function feedbackRatingMeta(string $nin, string $departmentId, string $surveyVersion = '1.0'): array
{
    static $cache = [];
    $surveyVersion = trim($surveyVersion) ?: '1.0';
    $key = $nin . '_' . $departmentId . '@' . $surveyVersion;
    if (isset($cache[$key])) return $cache[$key];

    try {
        $context = SurveyConfig::resolveReference($nin . '_' . $departmentId, $surveyVersion);
        $questions = SurveyConfig::questions($context, 1);
        $meta = [];
        foreach ($questions as $question) {
            $reportType = strtolower(trim((string) ($question['report_type'] ?? 'rating')));
            if ($reportType !== 'rating') continue;
            $qn = (int) ($question['qn'] ?? 0);
            $count = count($question['options'] ?? []);
            if ($qn >= 1 && $qn <= 31 && $count >= 2) {
                $meta[] = ['qn' => $qn, 'scores' => RatingScale::optionScores($question)];
            }
        }
        return $cache[$key] = $meta;
    } catch (Throwable) {
        /* Some historic/test responses use a NIN that is no longer present in
           facilityCodes.json. Questions and scoring are department/version
           specific, so use a configured facility only to load that same
           department survey definition; never alter the stored response. */
        try {
            $fallbackFacility = SurveyConfig::facilities()[0]['facilityNIN'] ?? '';
            if ($fallbackFacility === '') {
                return $cache[$key] = [];
            }
            $context = SurveyConfig::resolveReference($fallbackFacility . '_' . $departmentId, $surveyVersion);
            $questions = SurveyConfig::questions($context, 1);
            $meta = [];
            foreach ($questions as $question) {
                if (strtolower(trim((string) ($question['report_type'] ?? 'rating'))) !== 'rating') continue;
                $qn = (int) ($question['qn'] ?? 0);
                if ($qn >= 1 && $qn <= 31 && count($question['options'] ?? []) >= 2) {
                    $meta[] = ['qn' => $qn, 'scores' => RatingScale::optionScores($question)];
                }
            }
            return $cache[$key] = $meta;
        } catch (Throwable) {
            return $cache[$key] = [];
        }
    }
}

function feedbackScore(array $row): ?float
{
    $meta = feedbackRatingMeta(
        (string) $row['hospital_nin'],
        (string) $row['department_id'],
        (string) ($row['survey_version'] ?? '1.0')
    );
    $scores = [];
    foreach ($meta as $question) {
        $column = 'srvy_Q' . ($question['qn'] - 1);
        if (!isset($row[$column]) || !is_numeric($row[$column])) continue;
        $answer = (int) $row[$column];
        $score = $question['scores'][$answer] ?? null;
        if ($score !== null) $scores[] = (float) $score;
    }
    return $scores === [] ? null : round(array_sum($scores) / count($scores), 1);
}

function feedbackSentiment(?float $score): string
{
    if ($score === null) return 'neutral';
    if ($score >= 3.6) return 'positive';
    if ($score <= 2.5) return 'negative';
    return 'neutral';
}

function feedbackLocationVerified(array $row): bool
{
    try {
        $context = SurveyConfig::resolveReference((string) $row['hospital_nin'] . '_' . (string) $row['department_id']);
        $lat = filter_var($row['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $lon = filter_var($row['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lon === false) return false;
        $distance = SurveyConfig::distanceMeters(
            (float) $context['facility']['facilityLat'],
            (float) $context['facility']['facilityLong'],
            (float) $lat,
            (float) $lon
        );
        return $distance <= (float) $context['geo_radius_meters'];
    } catch (Throwable) {
        return false;
    }
}

function feedbackPublicRow(array $row, array $facilityMap, array $departmentMap): array
{
    $score = feedbackScore($row);
    $nin = (string) ($row['hospital_nin'] ?? '');
    $departmentId = (string) ($row['department_id'] ?? '');
    return [
        'id' => (int) ($row['id'] ?? 0),
        'submission_id' => (string) ($row['submission_id'] ?? $row['id'] ?? ''),
        'submitted_at' => $row['srvy_rpl_dt'] ?? null,
        'facility_nin' => $nin,
        'facility_name' => $facilityMap[$nin] ?? $nin,
        'department_id' => (int) $departmentId,
        'department_name' => $departmentMap[$departmentId] ?? ('Department ' . $departmentId),
        'survey_code' => (string) ($row['survey_code'] ?? ''),
        'survey_version' => (string) ($row['survey_version'] ?? '1.0'),
        'rating' => $score,
        'sentiment' => feedbackSentiment($score),
        'location_verified' => feedbackLocationVerified($row),
        'review_status' => 'Unreviewed',
    ];
}

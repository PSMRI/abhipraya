<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
Security::requireMethod('GET');

try {
    $id = trim((string) ($_GET['id'] ?? ''));
    if ($id === '') Response::validation(['id' => 'Response ID is required.']);

    if (ctype_digit($id)) {
        $stmt = $con->prepare('SELECT * FROM srvy_responses WHERE id = ? LIMIT 1');
        $numericId = (int) $id; $stmt->bind_param('i', $numericId);
    } else {
        $stmt = $con->prepare('SELECT * FROM srvy_responses WHERE submission_id = ? LIMIT 1');
        $stmt->bind_param('s', $id);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) Response::notFound('Feedback response was not found.');

    $facilityMap = feedbackFacilityMap();
    $departmentMap = feedbackDepartmentMap();
    $public = feedbackPublicRow($row, $facilityMap, $departmentMap);
    $answers = [];
    try {
        $context = SurveyConfig::resolveReference((string) $row['hospital_nin'] . '_' . (string) $row['department_id']);
        foreach (SurveyConfig::questions($context, 1) as $question) {
            $qn = (int) ($question['qn'] ?? 0);
            if ($qn < 1 || $qn > 31) continue;
            $value = $row['srvy_Q' . ($qn - 1)] ?? null;
            if ($value === null || $value === '') continue;
            $option = $question['options'][(int) $value - 1]['text'] ?? ('Option ' . $value);
            $answers[] = [
                'question_number' => $qn,
                'question_text' => (string) ($question['ques'] ?? ('Question ' . $qn)),
                'answer_value' => (int) $value,
                'answer_text' => (string) $option,
            ];
        }
    } catch (Throwable) {}

    $public['answers'] = $answers;
    Response::success('Feedback response loaded.', $public);
} catch (Throwable $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'responses.view', 'user_id' => SessionManager::userId()]);
    Response::serverError();
}

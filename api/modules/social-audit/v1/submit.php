<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';
Security::requireMethod('POST');
try {
    $payload = Security::jsonInput(); $token = trim((string) ($payload['token'] ?? '')); $language = in_array($payload['language'] ?? '', ['en', 'hi'], true) ? $payload['language'] : 'en'; $answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : []; $respondent = hash('sha256', trim((string) ($payload['respondent_key'] ?? '')));
    $lookup = $con->prepare('SELECT facility_nin, survey_date FROM social_audit_tokens WHERE token = ? AND valid_until >= NOW()'); $lookup->bind_param('s', $token); $lookup->execute(); $context = $lookup->get_result()->fetch_assoc(); $lookup->close();
    if (!$context) Response::error('This Social Audit QR link is invalid or has expired.', null, 410);
    if ($context['facility_nin'] === '1234567890') $respondent = hash('sha256', $respondent . '|' . bin2hex(random_bytes(16)));
    $config = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/masters/social-audit/survey_questions.json'), true) ?: []; $questions = []; foreach (($config['questions'] ?? []) as $question) foreach (($question['sub_questions'] ?? [$question]) as $item) $questions[] = $item;
    $valid = []; foreach ($questions as $question) { $id = (string) ($question['id'] ?? ''); $type = (string) ($question['type'] ?? 'yes_no'); $valid[$id] = array_map(static fn($item) => (string) $item['value'], $config['answer_options'][$type][$language] ?? $config['answer_options'][$type]['en'] ?? []); }
    if (count($answers) !== count($valid) || strlen($respondent) !== 64) Response::validation(['answers' => 'Please answer every question.']);
    foreach ($valid as $id => $options) if (!isset($answers[$id]) || !in_array((string) $answers[$id], $options, true)) Response::validation(['answers' => 'One or more answers are invalid.']);
    $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $insert = $con->prepare('INSERT INTO social_audit_answers (token, facility_nin, survey_date, question_id, answer_value, language, respondent_key, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $con->begin_transaction();
    try {
        if ($context['facility_nin'] !== '1234567890') {
            $claim = $con->prepare('INSERT INTO social_audit_submissions (token, respondent_key, facility_nin, ip_address) VALUES (?, ?, ?, ?)');
            $claim->bind_param('ssss', $token, $respondent, $context['facility_nin'], $ipAddress);
            $claim->execute();
            $claim->close();
        }
        foreach ($valid as $id => $_) { $answer = (string) $answers[$id]; $insert->bind_param('ssssssss', $token, $context['facility_nin'], $context['survey_date'], $id, $answer, $language, $respondent, $ipAddress); $insert->execute(); }
        $con->commit();
    } catch (mysqli_sql_exception $exception) {
        $con->rollback();
        if ((int) $exception->getCode() === 1062) Response::error('This device has already submitted this survey.', null, 409);
        throw $exception;
    }
    Response::success('Your opinion has been submitted successfully.');
} catch (Throwable $exception) { if ($con->errno) $con->rollback(); ErrorHandler::log($exception, ['endpoint' => 'social-audit.submit']); Response::serverError('Unable to save your answers.'); }

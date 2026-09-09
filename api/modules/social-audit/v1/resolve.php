<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';
Security::requireMethod('GET');
$token = trim((string) ($_GET['token'] ?? ''));
$stmt = $con->prepare('SELECT facility_nin, survey_date FROM social_audit_tokens WHERE token = ? AND valid_until >= NOW()');
$stmt->bind_param('s', $token); $stmt->execute(); $context = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$context) Response::error('This Social Audit QR link is invalid or has expired.', null, 410);
$config = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/masters/social-audit/survey_questions.json'), true) ?: [];
$questions = []; foreach (($config['questions'] ?? []) as $question) foreach (($question['sub_questions'] ?? [$question]) as $item) $questions[] = $item;
Response::success('Social Audit survey loaded.', ['questions' => $questions, 'answer_options' => $config['answer_options'] ?? [], 'facility_nin' => $context['facility_nin'], 'survey_date' => $context['survey_date']]);

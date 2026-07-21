<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';

Security::requireMethod('POST');
SessionManager::requireLogin();

$roleId = (int) (SessionManager::user()['role_id'] ?? 0);
if (!in_array($roleId, [1, 2, 3], true)) {
    Response::forbidden('Your role is not allowed to generate QR links.');
}

try {
    $payload = Security::jsonInput();
    Security::requireFields($payload, ['facility_nin', 'department_id']);
    $facilityNin = trim((string) $payload['facility_nin']);
    if ($roleId === 2 && !hash_equals((string) SessionManager::facilityId(), $facilityNin)) {
        Response::forbidden('Facility Administrators can generate QR links only for their assigned facility.');
    }
    $reference = $facilityNin . '_' . (int) $payload['department_id'];
    $context = SurveyConfig::resolveReference($reference);
    $url = SurveyConfig::surveyUrl($context['reference']);

    Response::success('QR survey URL generated', [
        'reference' => $context['reference'],
        'survey_url' => $url,
        'facility_name' => $context['facility']['facilityName'],
        'department_name' => $context['department']['departmentName'],
    ]);
} catch (InvalidArgumentException $exception) {
    Response::validation(['qr' => $exception->getMessage()]);
} catch (Throwable $exception) {
    ErrorHandler::log($exception, [
        'endpoint' => 'qr.generate',
        'user_id' => SessionManager::userId(),
    ]);
    Response::serverError($exception->getMessage());
}

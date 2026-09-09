<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';
require_once dirname(__DIR__, 3) . '/helpers/AccessScope.php';

Security::requireMethod('POST');
SessionManager::requireLogin();

if (!in_array(SessionManager::roleId(), [1, 2, 3, 7, 8], true)) {
    Response::forbidden('Your role is not allowed to validate QR links.');
}

try {
    $payload = Security::jsonInput();
    $reference = trim((string) ($payload['ref'] ?? ''));

    if ($reference === '') {
        Security::requireFields($payload, ['facility_nin', 'department_id']);
        $reference = trim((string) $payload['facility_nin']) . '_' . (int) $payload['department_id'];
    }

    $context = SurveyConfig::resolveReference($reference);
    AccessScope::assertFacilityAllowed((string) ($context['facility']['facilityNIN'] ?? ''));
    $questions = SurveyConfig::questions($context, 1);

    Response::success('QR configuration is valid', [
        'reference' => $context['reference'],
        'survey_url' => SurveyConfig::surveyUrl($context['reference']),
        'question_count' => count($questions),
        'geo_radius_meters' => $context['geo_radius_meters'],
    ]);
} catch (InvalidArgumentException $exception) {
    Response::validation(['qr' => $exception->getMessage()]);
} catch (Throwable $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'qr.validate', 'user_id' => SessionManager::userId()]);
    Response::serverError();
}

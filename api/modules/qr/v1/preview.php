<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';
require_once dirname(__DIR__, 3) . '/helpers/AccessScope.php';

Security::requireMethod('GET');
SessionManager::requireLogin();

if (!in_array(SessionManager::roleId(), [1, 2, 3, 7, 8], true)) {
    Response::forbidden('Your role is not allowed to preview QR links.');
}

try {
    $reference = trim((string) ($_GET['ref'] ?? ''));
    if ($reference === '') {
        $reference = trim((string) ($_GET['facility_nin'] ?? '')) . '_' . (int) ($_GET['department_id'] ?? 0);
    }
    $context = SurveyConfig::resolveReference($reference);
    AccessScope::assertFacilityAllowed((string) ($context['facility']['facilityNIN'] ?? ''));
    Response::success('QR preview loaded', [
        'reference' => $context['reference'],
        'survey_url' => SurveyConfig::surveyUrl($context['reference']),
        'facility' => $context['facility'],
        'department' => $context['department'],
        'geo_radius_meters' => $context['geo_radius_meters'],
    ]);
} catch (InvalidArgumentException $exception) {
    Response::validation(['qr' => $exception->getMessage()]);
} catch (Throwable $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'qr.preview', 'user_id' => SessionManager::userId()]);
    Response::serverError();
}

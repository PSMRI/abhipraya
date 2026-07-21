<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';
Security::requireMethod('GET');
try {
    $context = SurveyConfig::resolveReference((string) ($_GET['ref'] ?? ''));
    Response::success('Survey link resolved', [
        'reference' => $context['reference'],
        'facility' => [
            'nin' => (string) $context['facility']['facilityNIN'],
            'name' => $context['facility']['facilityName'],
            'address' => (string) ($context['facility']['facilityAddress'] ?? ''),
        ],
        'department' => ['id' => $context['department']['departmentId'], 'name' => $context['department']['departmentName']],
        'languages' => [1, 2],
        'geo_required' => true,
        'geo_radius_meters' => $context['geo_radius_meters'],
    ]);
} catch (InvalidArgumentException $exception) {
    Response::error($exception->getMessage(), null, 404);
} catch (Throwable $exception) {
    Response::serverError($exception->getMessage());
}

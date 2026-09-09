<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';
require_once dirname(__DIR__, 3) . '/helpers/AccessScope.php';

Security::requireMethod('GET');
SessionManager::requireLogin();

if (!in_array(SessionManager::roleId(), [1, 2, 3, 7, 8], true)) {
    Response::forbidden('Your role is not allowed to view QR links.');
}

try {
    $search = trim((string) ($_GET['search'] ?? ''));
    $districtId = trim((string) ($_GET['district_id'] ?? ''));
    // limit=0 is used by scoped admin pickers that must show every permitted
    // facility. Other callers retain the safe default and maximum.
    $requestedLimit = (int) ($_GET['limit'] ?? 25);
    $limit = $requestedLimit === 0 ? 0 : max(1, min(500, $requestedLimit));
    $facilities = SurveyConfig::facilities($search);
    // Role 2 is a Facility Administrator. The server, not only the UI,
    // limits the picker to the facility mapped to their account.
    $facilities = AccessScope::filterFacilities($facilities);
    if ($districtId !== '') {
        $facilities = array_values(array_filter($facilities, static fn(array $facility): bool =>
            (string) ($facility['districtCode'] ?? '') === $districtId
        ));
    }
    $totalFacilities = count($facilities);
    $districts = [];
    foreach ($facilities as $facility) {
        $code = trim((string) ($facility['districtCode'] ?? ''));
        if ($code === '' || isset($districts[$code])) {
            continue;
        }
        $districts[$code] = [
            'districtCode' => $code,
            'districtName' => trim((string) ($facility['districtName'] ?? '')) ?: ('District ' . $code),
        ];
    }
    uasort($districts, static fn(array $left, array $right): int => strcasecmp($left['districtName'], $right['districtName']));
    if ($limit > 0) {
        $facilities = array_slice($facilities, 0, $limit);
    }
    $language = (int) ($_GET['lang'] ?? 1);
    $departments = SurveyConfig::departments($language === 2 ? 2 : 1);

    Response::success('QR source configuration loaded', [
        'facilities' => $facilities,
        'departments' => $departments,
        'districts' => array_values($districts),
        'total_facilities' => $totalFacilities,
    ]);
} catch (Throwable $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'qr.list', 'user_id' => SessionManager::userId()]);
    Response::serverError();
}

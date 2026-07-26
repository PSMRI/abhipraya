<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';

Security::requireMethod('GET');
SessionManager::requireLogin();

if (!in_array(SessionManager::roleId(), [1, 2, 3], true)) {
    Response::forbidden('Your role is not allowed to view QR links.');
}

try {
    $search = trim((string) ($_GET['search'] ?? ''));
    $limit = max(1, min(500, (int) ($_GET['limit'] ?? 25)));
    $facilities = SurveyConfig::facilities($search);
    // Role 2 is a Facility Administrator. The server, not only the UI,
    // limits the picker to the facility mapped to their account.
    if (SessionManager::roleId() === 2) {
        $assignedNin = (string) SessionManager::facilityId();
        $facilities = array_values(array_filter(
            $facilities,
            static fn(array $facility): bool => (string) ($facility['facilityNIN'] ?? '') === $assignedNin
        ));
    }
    $totalFacilities = count($facilities);
    $facilities = array_slice($facilities, 0, $limit);
    $departments = SurveyConfig::departments();

    Response::success('QR source configuration loaded', [
        'facilities' => $facilities,
        'departments' => $departments,
        'total_facilities' => $totalFacilities,
    ]);
} catch (Throwable $exception) {
    ErrorHandler::log($exception, ['endpoint' => 'qr.list', 'user_id' => SessionManager::userId()]);
    Response::serverError();
}

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/helpers/RoleConfig.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';
Security::requireMethod('GET');
if (!SessionManager::isLoggedIn()) {
    Response::unauthorized('Please sign in to continue.');
}
$user = SessionManager::user();
$user['role_name'] = RoleConfig::nameForId((int) ($user['role_id'] ?? 0));

/* Facility accounts use their NIN as the login name. Expose the matching
 * display name separately so user-facing pages do not need to show the NIN. */
$facilityNin = (string) ($user['fac_id'] ?? '');
foreach (SurveyConfig::facilities() as $facility) {
    if ((string) ($facility['facilityNIN'] ?? '') === $facilityNin) {
        $user['facility_name'] = (string) ($facility['facilityName'] ?? '');
        break;
    }
}
Response::success('Current user loaded', ['user' => $user]);

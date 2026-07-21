<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/helpers/RoleConfig.php';
Security::requireMethod('GET');
if (!SessionManager::isLoggedIn()) {
    Response::unauthorized('Please sign in to continue.');
}
$user = SessionManager::user();
$user['role_name'] = RoleConfig::nameForId((int) ($user['role_id'] ?? 0));
Response::success('Current user loaded', ['user' => $user]);

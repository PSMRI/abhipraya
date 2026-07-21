<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';

Security::requireMethod('POST');
SessionManager::logout();
Response::success('You have been signed out.');

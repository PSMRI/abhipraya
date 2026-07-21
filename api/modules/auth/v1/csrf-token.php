<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';

Security::requireMethod('GET');
Response::success(
    'CSRF token generated',
    Csrf::getTokenInfo()
);

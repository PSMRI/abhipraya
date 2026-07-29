<?php

require_once dirname(__DIR__, 3) . '/public_api.php';

SessionManager::requireLogin();

Response::success(
    'CSRF token generated',
    Csrf::getTokenInfo()
);

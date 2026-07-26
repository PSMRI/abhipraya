<?php
declare(strict_types=1);

/*
 * Versioned public API contract. Add routes here; do not route URLs directly
 * to physical PHP paths. Existing /api/modules URLs remain as legacy aliases.
 */
$module = static fn(string $area, string $file): string => __DIR__ . '/modules/' . $area . '/v1/' . $file . '.php';

return [
    'GET' => [
        '/v1/auth/captcha' => $module('auth', 'captcha'),
        '/v1/auth/me' => $module('auth', 'me'),
        '/v1/auth/csrf' => $module('auth', 'csrf'),
        '/v1/auth/profile' => $module('auth', 'profile'),
        '/v1/qr' => $module('qr', 'list'),
        '/v1/analytics/summary' => $module('analytics', 'summary'),
        '/v1/capa/actions' => $module('capa', 'actions'),
        '/v1/responses' => $module('responses', 'list'),
        '/v1/responses/view' => $module('responses', 'view'),
        '/v1/responses/export' => $module('responses', 'export'),
        '/v1/public-survey/questions' => $module('public-survey', 'questions'),
        '/v1/public-survey/resolve' => $module('public-survey', 'resolve'),
    ],
    'POST' => [
        '/v1/auth/login' => $module('auth', 'login'),
        '/v1/auth/logout' => $module('auth', 'logout'),
        '/v1/auth/change-password' => $module('auth', 'change-password'),
        '/v1/auth/profile' => $module('auth', 'profile'),
        '/v1/qr/generate' => $module('qr', 'generate'),
        '/v1/capa/actions' => $module('capa', 'actions'),
        '/v1/public-survey/submit' => $module('public-survey', 'submit'),
        '/v1/public-survey/location' => $module('public-survey', 'location'),
    ],
];

<?php
declare(strict_types=1);

/*
 * Abhipraya UI front controller.
 * All browser-facing routes resolve here; page file locations are internal.
 */
$route = trim((string) ($_GET['route'] ?? ''), '/');

/* Protect direct navigation to administrator pages before sending HTML. */
$adminRoutes = ['admin/dashboard', 'admin/qr', 'admin/account', 'admin/reports', 'admin/capa'];
if (in_array($route, $adminRoutes, true)) {
    require_once __DIR__ . '/../api/bootstrap.php';
    if (!SessionManager::isLoggedIn()) {
        header('Location: /admin/login?reason=session-expired', true, 302);
        exit;
    }
}

$views = [
    'landing' => __DIR__ . '/pages/public/landing.html',
    'developer' => __DIR__ . '/pages/public/developer-hub.html',
    'admin/login' => __DIR__ . '/pages/auth/login.html',
    'admin/forgot-password' => __DIR__ . '/pages/auth/forgot-password.html',
    'admin/dashboard' => __DIR__ . '/pages/dashboard/index.html',
    'admin/qr' => __DIR__ . '/pages/qr/generate.html',
    'admin/account' => __DIR__ . '/pages/account/security.html',
    'admin/reports' => __DIR__ . '/pages/reports/indicators.html',
    'admin/capa' => __DIR__ . '/pages/capa/index.html',
    'survey' => __DIR__ . '/pages/public-survey/question.php',
    'question' => __DIR__ . '/pages/public-survey/question.php',
];

if (!isset($views[$route])) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><title>Page not found</title><h1>Page not found</h1>';
    exit;
}

$view = $views[$route];
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (str_ends_with($view, '.php')) {
    require $view;
    exit;
}

header('Content-Type: text/html; charset=utf-8');
readfile($view);

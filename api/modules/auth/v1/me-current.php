<?php
declare(strict_types=1);

/* Load the current session-store resilience handling before API bootstrap. */
$sessionManager = dirname(__DIR__, 3) . '/core/SessionManager.php';
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate($sessionManager, true);
}
require __DIR__ . '/me.php';

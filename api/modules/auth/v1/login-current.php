<?php
declare(strict_types=1);

/*
 * Stable entry point used after security updates. Some IIS FastCGI pools run
 * PHP with timestamp checks disabled, so explicitly invalidate the handler
 * before loading it. This keeps the deployed lockout logic current.
 */
$handler = __DIR__ . '/login.php';
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate($handler, true);
}
require $handler;

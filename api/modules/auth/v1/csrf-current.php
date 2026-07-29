<?php
declare(strict_types=1);

/* Ensure the versioned endpoint uses the current CSRF access control. */
$handler = __DIR__ . '/csrf.php';
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate($handler, true);
}
require $handler;

<?php
declare(strict_types=1);

/* SaQshi-style shared API bootstrap for Abhipraya. */
date_default_timezone_set('Asia/Kolkata');

/*
 * This must run before any included file or session startup. A PHP warning
 * written before the JSON response would make browser clients fail while
 * parsing the API body.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/core/Security.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/ErrorHandler.php';
require_once __DIR__ . '/core/Env.php';
require_once __DIR__ . '/core/SessionManager.php';
require_once __DIR__ . '/core/Csrf.php';
require_once __DIR__ . '/core/Event.php';
require_once __DIR__ . '/core/Router.php';

Env::load();
Security::headers();
ErrorHandler::register();
SessionManager::start();
Event::traceRequest();

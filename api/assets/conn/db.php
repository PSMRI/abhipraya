<?php
declare(strict_types=1);

/* MySQLi connection adapter: accepts both legacy and new environment names. */
require_once dirname(__DIR__, 2) . '/core/Env.php';
Env::load();

$dbHost = Env::get('DB_HOST');
$dbPort = (int) Env::get('DB_PORT', '3306');
$dbName = Env::get('DB_DATABASE', Env::get('DB_NAME'));
$dbUser = Env::get('DB_USERNAME', Env::get('DB_USER'));
$dbPass = Env::get('DB_PASSWORD');
$timeout = max(1, (int) Env::get('DB_CONNECT_TIMEOUT', Env::get('DB_TIMEOUT', '5')));

if ($dbHost === null || $dbName === null || $dbUser === null || $dbPass === null) {
    Response::serverError('Database configuration is missing.');
}

$con = mysqli_init();
if ($con === false) {
    Response::serverError('Database client could not be initialised.');
}

mysqli_options($con, MYSQLI_OPT_CONNECT_TIMEOUT, $timeout);
$connected = false;
try {
    $connected = mysqli_real_connect($con, $dbHost, $dbUser, $dbPass, $dbName, $dbPort);
} catch (mysqli_sql_exception $exception) {
    ErrorHandler::log('Database connection failed', ['mysqli_errno' => $exception->getCode()]);
}

if (!$connected) {
    ErrorHandler::log('Database connection failed', ['mysqli_errno' => mysqli_connect_errno()]);
    Response::serverError('Database service is unavailable.');
}

mysqli_set_charset($con, 'utf8mb4');

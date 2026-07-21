<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';
Security::requireMethod('GET');
$username = (string) ($_GET['username'] ?? '');
$usernameHash = hash('sha256', strtolower(trim($username)));
$statement = $con->prepare('SELECT failed_attempts, locked_until, last_attempt_at FROM auth_login_attempts WHERE username_hash = ? ORDER BY last_attempt_at DESC');
$statement->bind_param('s', $usernameHash);
$statement->execute();
Response::success('Lock state', $statement->get_result()->fetch_all(MYSQLI_ASSOC));

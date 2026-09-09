<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';

Security::requireMethod('POST');
SessionManager::requireLogin();
Csrf::validate();
try {
    $payload = Security::jsonInput();
    $nin = trim((string) ($payload['facility_nin'] ?? ''));
    $date = trim((string) ($payload['survey_date'] ?? ''));
    if ($nin === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) Response::validation(['social_audit' => 'Select a facility and valid survey date.']);
    $roleId = (int) (SessionManager::user()['role_id'] ?? 0);
    if (!in_array($roleId, [1, 2, 3], true)) Response::forbidden('Your role is not allowed to generate Social Audit QR links.');
    if ($roleId === 2 && !hash_equals((string) SessionManager::facilityId(), $nin)) Response::forbidden('You can generate QR links only for your assigned facility.');
    $facility = null;
    foreach (SurveyConfig::facilities() as $item) if ((string) ($item['facilityNIN'] ?? '') === $nin) { $facility = $item; break; }
    if ($facility === null) Response::validation(['facility_nin' => 'This facility is not available in your scope.']);
    $select = $con->prepare('SELECT token FROM social_audit_tokens WHERE facility_nin = ? AND survey_date = ?');
    $select->bind_param('ss', $nin, $date); $select->execute(); $existing = $select->get_result()->fetch_assoc(); $select->close();
    $token = (string) ($existing['token'] ?? bin2hex(random_bytes(16)));
    $expires = $nin === '1234567890'
        ? '9999-12-31 23:59:59'
        : date('Y-m-d H:i:s', strtotime('+4 hours'));
    if (!$existing) {
        $userId = SessionManager::userId();
        $insert = $con->prepare('INSERT INTO social_audit_tokens (token, facility_nin, survey_date, valid_until, created_by) VALUES (?, ?, ?, ?, ?)');
        $insert->bind_param('ssssi', $token, $nin, $date, $expires, $userId); $insert->execute(); $insert->close();
    } else {
        $refresh = $con->prepare('UPDATE social_audit_tokens SET valid_until = ? WHERE token = ?');
        $refresh->bind_param('ss', $expires, $token); $refresh->execute(); $refresh->close();
    }
    Response::success('Social Audit QR link generated.', ['token' => $token, 'survey_url' => '/social-audit?token=' . rawurlencode($token), 'facility_name' => $facility['facilityName'], 'survey_date' => $date, 'never_expires' => $nin === '1234567890']);
} catch (Throwable $exception) { ErrorHandler::log($exception, ['endpoint' => 'social-audit.generate']); Response::serverError('Unable to generate the Social Audit QR link.'); }

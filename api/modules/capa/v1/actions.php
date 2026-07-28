<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';

SessionManager::requireLogin();
Security::requireAnyMethod(['GET', 'POST']);

if (!in_array(SessionManager::roleId(), [1, 2, 3], true)) {
    Response::forbidden('Your role is not allowed to manage CAPA plans.');
}

function capaScopeValue(mixed $value, string $field): string
{
    $clean = trim((string) $value);
    if ($field === 'facility_nin' && !preg_match('/^\d{6,20}$/', $clean)) {
        Response::validation([$field => 'Select a valid facility.']);
    }
    if ($field === 'month' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $clean)) {
        Response::validation([$field => 'Select a valid month.']);
    }
    return $clean;
}

function capaDepartment(mixed $value): int
{
    $department = filter_var($value, FILTER_VALIDATE_INT);
    if ($department === false || $department < 1 || $department > 30) {
        Response::validation(['dept_id' => 'Select a valid department.']);
    }
    return (int) $department;
}

function capaVersion(mixed $value): string
{
    $version = trim((string) $value);
    if (!preg_match('/^\d+\.\d+(?:\.\d+)?$/', $version)) {
        Response::validation(['survey_version' => 'Select a valid survey version.']);
    }
    return $version;
}

function capaSurveyContext(string $facilityNin, int $department, string $version): array
{
    try {
        return SurveyConfig::resolveReference($facilityNin . '_' . $department, $version);
    } catch (InvalidArgumentException|RuntimeException) {
        Response::validation(['survey_version' => 'The selected survey version is unavailable for this department.']);
    }
}

function capaEnforceScope(string $facilityNin): void
{
    if (SessionManager::roleId() !== 2) {
        return;
    }
    $assignedFacility = (string) SessionManager::facilityId();
    if ($assignedFacility === '' || !hash_equals($assignedFacility, $facilityNin)) {
        Response::forbidden('You can manage CAPA plans only for your assigned facility.');
    }
}

function capaText(array $action, string $field, int $maximum, bool $required = false): string
{
    $value = trim((string) ($action[$field] ?? ''));
    if ($required && $value === '') {
        Response::validation([$field => 'This field is required.']);
    }
    if (mb_strlen($value) > $maximum || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) {
        Response::validation([$field => 'Enter a valid value within ' . $maximum . ' characters.']);
    }
    if ($field === 'responsible') {
        if (!preg_match('/\p{L}/u', $value) || !preg_match('/^[\p{L}\p{M}0-9 .,&()\/-]+$/u', $value)) {
            Response::validation([$field => 'Enter a valid name or designation. Only letters, numbers, spaces, and basic punctuation are allowed.']);
        }
    }
    return $value;
}

function capaLoad(mysqli $con, string $facilityNin, int $department, string $month, string $surveyVersion): array
{
    $statement = $con->prepare(
        'SELECT id, hospital_nin, month, dept_id, survey_code, survey_version,
                survey_schema_hash, question_key, root_cause,
                action_plan, responsible, timeline, remarks, created_at
         FROM capa_actions
         WHERE hospital_nin = ? AND dept_id = ? AND month = ? AND survey_version = ?
         ORDER BY question_key'
    );
    $statement->bind_param('siss', $facilityNin, $department, $month, $surveyVersion);
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'facility_nin' => (string) $row['hospital_nin'],
            'month' => (string) $row['month'],
            'dept_id' => (int) $row['dept_id'],
            'survey_code' => (string) ($row['survey_code'] ?? ''),
            'survey_version' => (string) ($row['survey_version'] ?? ''),
            'survey_schema_hash' => (string) ($row['survey_schema_hash'] ?? ''),
            'question_key' => (string) $row['question_key'],
            'root_cause' => (string) ($row['root_cause'] ?? ''),
            'action_plan' => (string) ($row['action_plan'] ?? ''),
            'responsible' => (string) ($row['responsible'] ?? ''),
            'timeline' => (string) ($row['timeline'] ?? ''),
            'remarks' => (string) ($row['remarks'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }, $rows);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $facilityNin = capaScopeValue($_GET['facility_nin'] ?? '', 'facility_nin');
        $department = capaDepartment($_GET['dept_id'] ?? '');
        if (isset($_GET['months']) && $_GET['months'] === '1') {
            capaEnforceScope($facilityNin);
            $stmt = $con->prepare('SELECT DISTINCT month FROM capa_actions WHERE hospital_nin = ? AND dept_id = ? ORDER BY month DESC');
            $stmt->bind_param('si', $facilityNin, $department); $stmt->execute();
            $months = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'month'); $stmt->close();
            Response::success('CAPA months loaded.', ['months' => $months]);
        }
        $month = capaScopeValue($_GET['month'] ?? '', 'month');
        $surveyVersion = capaVersion($_GET['survey_version'] ?? '');
        capaEnforceScope($facilityNin);
        $surveyContext = capaSurveyContext($facilityNin, $department, $surveyVersion);
        Response::success('CAPA actions loaded.', [
            'actions' => capaLoad($con, $facilityNin, $department, $month, $surveyVersion),
            'survey' => [
                'code' => (string) $surveyContext['survey_code'],
                'version' => (string) $surveyContext['survey_version'],
                'schema_hash' => (string) $surveyContext['survey_schema_hash'],
            ],
        ]);
    }

    Csrf::validate();
    $payload = Security::jsonInput();
    $facilityNin = capaScopeValue($payload['facility_nin'] ?? '', 'facility_nin');
    $department = capaDepartment($payload['dept_id'] ?? '');
    $month = capaScopeValue($payload['month'] ?? '', 'month');
    $surveyVersion = capaVersion($payload['survey_version'] ?? '');
    capaEnforceScope($facilityNin);
    $surveyContext = capaSurveyContext($facilityNin, $department, $surveyVersion);
    $surveyCode = (string) $surveyContext['survey_code'];
    $surveySchemaHash = (string) $surveyContext['survey_schema_hash'];

    $actions = $payload['actions'] ?? null;
    if (!is_array($actions) || $actions === [] || count($actions) > 3) {
        Response::validation(['actions' => 'Submit between one and three CAPA actions.']);
    }

    $validated = [];
    foreach ($actions as $action) {
        if (!is_array($action)) {
            Response::validation(['actions' => 'Each CAPA action must be an object.']);
        }
        $questionKey = trim((string) ($action['question_key'] ?? ''));
        if (!preg_match('/^srvy_Q(?:[0-9]|[12][0-9]|30)$/', $questionKey)) {
            Response::validation(['question_key' => 'Select a valid survey indicator.']);
        }
        $validated[] = [
            'question_key' => $questionKey,
            'root_cause' => capaText($action, 'root_cause', 2000, true),
            'action_plan' => capaText($action, 'action_plan', 3000, true),
            'responsible' => capaText($action, 'responsible', 255, true),
            'timeline' => capaText($action, 'timeline', 255, true),
            'remarks' => capaText($action, 'remarks', 2000),
        ];
    }

    $statement = $con->prepare(
        'INSERT INTO capa_actions
            (hospital_nin, month, question_key, root_cause, action_plan,
             responsible, timeline, remarks, dept_id, survey_code,
             survey_version, survey_schema_hash)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            dept_id = VALUES(dept_id),
            survey_code = VALUES(survey_code),
            survey_schema_hash = VALUES(survey_schema_hash),
            root_cause = VALUES(root_cause),
            action_plan = VALUES(action_plan),
            responsible = VALUES(responsible),
            timeline = VALUES(timeline),
            remarks = VALUES(remarks)'
    );

    $con->begin_transaction();
    foreach ($validated as $action) {
        $statement->bind_param(
            'ssssssssisss',
            $facilityNin,
            $month,
            $action['question_key'],
            $action['root_cause'],
            $action['action_plan'],
            $action['responsible'],
            $action['timeline'],
            $action['remarks'],
            $department,
            $surveyCode,
            $surveyVersion,
            $surveySchemaHash
        );
        $statement->execute();
    }
    $statement->close();
    $con->commit();

    Response::success('CAPA action saved.', [
        'actions' => capaLoad($con, $facilityNin, $department, $month, $surveyVersion),
        'survey' => [
            'code' => $surveyCode,
            'version' => $surveyVersion,
            'schema_hash' => $surveySchemaHash,
        ],
    ]);
} catch (Throwable $exception) {
    if (isset($con) && $con instanceof mysqli) {
        try {
            $con->rollback();
        } catch (Throwable) {
            // The transaction may already be closed.
        }
    }
    ErrorHandler::log($exception, [
        'endpoint' => 'capa.actions',
        'user_id' => SessionManager::userId(),
    ]);
    Response::serverError();
}

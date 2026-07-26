<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/public_api.php';
require_once dirname(__DIR__, 3) . '/assets/conn/db.php';
require_once dirname(__DIR__, 3) . '/helpers/SurveyConfig.php';
Security::requireMethod('POST');

function responseVersionColumnsAvailable(mysqli $connection): bool
{
    $result = $connection->query(
        "SELECT COUNT(*) AS total
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'srvy_responses'
           AND COLUMN_NAME IN ('survey_code', 'survey_version', 'survey_schema_hash')"
    );
    return (int) ($result->fetch_assoc()['total'] ?? 0) === 3;
}

try {
    $payload = Security::jsonInput();
    Security::requireFields($payload, ['ref', 'lang', 'device_id', 'latitude', 'longitude', 'answers']);
    $requestedVersion = trim((string) ($payload['survey_version'] ?? ''));
    $context = SurveyConfig::resolveReference(
        (string) $payload['ref'],
        $requestedVersion !== '' ? $requestedVersion : null
    );
    $requestedCode = trim((string) ($payload['survey_code'] ?? ''));
    $requestedHash = strtolower(trim((string) ($payload['survey_schema_hash'] ?? '')));
    if ($requestedCode !== '' && !hash_equals((string) $context['survey_code'], $requestedCode)) {
        Response::validation(['survey_code' => 'The survey configuration changed. Reload the survey and try again.']);
    }
    if ($requestedHash !== '' && !hash_equals((string) $context['survey_schema_hash'], $requestedHash)) {
        Response::validation(['survey_schema_hash' => 'The survey configuration changed. Reload the survey and try again.']);
    }
    $language = (int) $payload['lang'];
    $questions = SurveyConfig::questions($context, $language);
    $deviceId = trim((string) $payload['device_id']);
    $latitude = filter_var($payload['latitude'], FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($payload['longitude'], FILTER_VALIDATE_FLOAT);

    if (strlen($deviceId) < 16 || strlen($deviceId) > 250 || $latitude === false || $longitude === false || !is_array($payload['answers'])) {
        Response::validation(['submission' => 'Invalid device, location or answer data.']);
    }

    $distance = SurveyConfig::distanceMeters((float) $context['facility']['facilityLat'], (float) $context['facility']['facilityLong'], (float) $latitude, (float) $longitude);
    if ($distance > $context['geo_radius_meters']) {
        Response::error('Feedback can only be submitted within the facility premises.', ['distance_meters' => round($distance)], 403);
    }

    $nin = (string) $context['facility']['facilityNIN'];
    $departmentId = (string) $context['department']['departmentId'];
    $duplicateWindowHours = (int) $context['duplicate_window_hours'];
    $duplicate = $con->prepare('SELECT id FROM srvy_responses WHERE hospital_nin = ? AND department_id = ? AND device_id = ? AND srvy_rpl_dt >= DATE_SUB(NOW(), INTERVAL ? HOUR) LIMIT 1');
    $duplicate->bind_param('sssi', $nin, $departmentId, $deviceId, $duplicateWindowHours);
    $duplicate->execute();
    $alreadySubmitted = $duplicate->get_result()->num_rows > 0;
    $duplicate->close();
    if ($alreadySubmitted) {
        Response::error('Feedback from this device was already submitted for this department in the last ' . $duplicateWindowHours . ' hours.', null, 409);
    }

    /* Legacy response columns are zero-based: qn 1 -> srvy_Q0. */
    $answerValues = array_fill(0, 31, null);
    foreach ($questions as $question) {
        $number = (int) $question['qn'];
        $answer = $payload['answers'][(string) $number] ?? $payload['answers'][$number] ?? null;
        $allowedValues = [];
        foreach (array_values($question['options'] ?? []) as $optionIndex => $option) {
            $configuredValue = is_array($option) ? ($option['value'] ?? null) : null;
            $allowedValues[] = is_numeric($configuredValue) ? (int) $configuredValue : $optionIndex + 1;
        }
        if ($number < 1 || $number > 31 || !is_numeric($answer)
            || !in_array((int) $answer, $allowedValues, true)) {
            Response::validation(['answers' => 'Please answer every survey question.']);
        }
        $answerValues[$number - 1] = (string) (int) $answer;
    }

    $columns = [
        'hospital_nin',
        'department_id',
        'latitude',
        'longitude',
        'ip_address',
        'device_id',
        'submission_id',
    ];
    for ($i = 0; $i < 31; $i++) { $columns[] = 'srvy_Q' . $i; }
    $submissionId = bin2hex(random_bytes(24));
    $values = [
        $nin,
        $departmentId,
        (string) $latitude,
        (string) $longitude,
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        $deviceId,
        $submissionId,
    ];
    if (responseVersionColumnsAvailable($con)) {
        array_splice($columns, 2, 0, ['survey_code', 'survey_version', 'survey_schema_hash']);
        array_splice($values, 2, 0, [
            (string) $context['survey_code'],
            (string) $context['survey_version'],
            (string) $context['survey_schema_hash'],
        ]);
    }
    $values = array_merge($values, array_values($answerValues));
    $sql = 'INSERT INTO srvy_responses (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
    $insert = $con->prepare($sql);
    $types = str_repeat('s', count($values));
    $params = [$types]; foreach ($values as &$value) { $params[] = &$value; } unset($value);
    call_user_func_array([$insert, 'bind_param'], $params);
    $insert->execute();
    $insert->close();

    Response::success('Feedback submitted successfully.', [
        'submission_id' => $submissionId,
        'survey_code' => $context['survey_code'],
        'survey_version' => $context['survey_version'],
        'distance_meters' => round($distance),
        'thank_you' => SurveyConfig::thankYouMessage($language),
    ], 201);
} catch (Throwable $exception) {
    Response::serverError($exception->getMessage());
}

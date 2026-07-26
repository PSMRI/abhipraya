<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$masterDirectory = $projectRoot . '/api/masters';
$publishedAt = '2026-07-25';

for ($departmentId = 1; $departmentId <= 9; $departmentId++) {
    $legacyFile = $masterDirectory . '/dept_id_' . $departmentId . '.json';
    if (!is_file($legacyFile)) {
        throw new RuntimeException('Missing legacy survey: ' . $legacyFile);
    }

    $questions = json_decode(
        (string) file_get_contents($legacyFile),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($questions)) {
        throw new RuntimeException('Invalid survey JSON: ' . $legacyFile);
    }

    foreach ($questions as &$question) {
        if (!is_array($question)) {
            continue;
        }
        $number = (int) ($question['qn'] ?? 0);
        if ($number < 1 || $number > 31) {
            throw new RuntimeException('Invalid question number in ' . basename($legacyFile));
        }
        $indicatorKey = 'DEPT_' . $departmentId . '_Q' . $number;
        $question['indicator_key'] = trim((string) ($question['indicator_key'] ?? ''))
            ?: $indicatorKey;
        $question['question_id'] = trim((string) ($question['question_id'] ?? ''))
            ?: $indicatorKey . '_V1_0';
        $question['report_type'] = trim((string) ($question['report_type'] ?? ''))
            ?: 'rating';
    }
    unset($question);

    $packageDirectory = $masterDirectory . '/surveys/department_' . $departmentId;
    $versionDirectory = $packageDirectory . '/v1.0';
    if (!is_dir($versionDirectory) && !mkdir($versionDirectory, 0775, true) && !is_dir($versionDirectory)) {
        throw new RuntimeException('Unable to create ' . $versionDirectory);
    }

    $surveyFile = $versionDirectory . '/survey.json';
    $encodedSurvey = json_encode(
        $questions,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    if (is_file($surveyFile)) {
        if (!hash_equals(hash('sha256', (string) file_get_contents($surveyFile)), hash('sha256', $encodedSurvey))) {
            throw new RuntimeException(
                'Refusing to overwrite published survey department '
                . $departmentId . ' version 1.0.'
            );
        }
    } else {
        file_put_contents($surveyFile, $encodedSurvey, LOCK_EX);
    }
    $schemaHash = hash_file('sha256', $surveyFile);

    $manifest = [
        'department_id' => $departmentId,
        'survey_code' => 'DEPARTMENT_' . $departmentId . '_FEEDBACK',
        'active_version' => '1.0',
        'versions' => [[
            'version' => '1.0',
            'status' => 'published',
            'published_at' => $publishedAt,
            'path' => 'v1.0/survey.json',
            'schema_hash' => $schemaHash,
        ]],
    ];
    $manifestFile = $packageDirectory . '/manifest.json';
    $encodedManifest = json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    if (is_file($manifestFile)) {
        $currentManifest = json_decode(
            (string) file_get_contents($manifestFile),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $currentHash = (string) ($currentManifest['versions'][0]['schema_hash'] ?? '');
        if (!hash_equals($schemaHash, $currentHash)) {
            throw new RuntimeException(
                'The published manifest does not match department '
                . $departmentId . ' version 1.0.'
            );
        }
    } else {
        file_put_contents($manifestFile, $encodedManifest, LOCK_EX);
    }
    echo 'Built department ', $departmentId, ' version 1.0 ', $schemaHash, PHP_EOL;
}

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const SUPPORTED_REPORT_TYPES = [
    'rating',
    'duration',
    'numeric',
    'text',
    'binary',
    'category',
    'availability',
    'severity',
    'demographic',
    'consent',
];

function fail(string $message): never
{
    fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    exit(1);
}

function readJsonFile(string $path): array
{
    if (!is_file($path)) {
        fail('File not found: ' . $path);
    }
    try {
        $decoded = json_decode(
            (string) file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $exception) {
        fail('Invalid JSON in ' . $path . ': ' . $exception->getMessage());
    }
    if (!is_array($decoded)) {
        fail('Expected a JSON array or object in ' . $path . '.');
    }
    return $decoded;
}

function normalizeQuestions(array $questions, string $version, array &$warnings): array
{
    if ($questions === [] || !array_is_list($questions)) {
        fail('The source survey must be a non-empty JSON array.');
    }

    $seenLanguageQuestion = [];
    $definitions = [];
    $normalized = [];
    $versionToken = preg_replace('/[^0-9A-Za-z]+/', '_', $version);

    foreach ($questions as $offset => $question) {
        $position = $offset + 1;
        if (!is_array($question)) {
            fail('Question entry ' . $position . ' must be an object.');
        }

        $number = (int) ($question['qn'] ?? 0);
        $language = (int) ($question['lang'] ?? 0);
        $text = trim((string) ($question['ques'] ?? ''));
        $type = strtolower(trim((string) ($question['report_type'] ?? '')));
        $indicatorKey = strtoupper(trim((string) ($question['indicator_key'] ?? '')));

        if ($number < 1 || $number > 31) {
            fail('Question entry ' . $position . ' has qn outside the supported range 1-31.');
        }
        if (!in_array($language, [1, 2], true)) {
            fail('Question ' . $number . ' must use lang 1 or 2.');
        }
        if ($text === '') {
            fail('Question ' . $number . ' language ' . $language . ' has no question text.');
        }
        if (!in_array($type, SUPPORTED_REPORT_TYPES, true)) {
            fail(
                'Question ' . $number . ' uses unsupported report_type "' . $type
                . '". The current public survey supports: '
                . implode(', ', SUPPORTED_REPORT_TYPES) . '.'
            );
        }
        if (!preg_match('/^[A-Z0-9][A-Z0-9_.-]{2,99}$/', $indicatorKey)) {
            fail(
                'Question ' . $number
                . ' requires a stable indicator_key using uppercase letters, numbers, dot, dash or underscore.'
            );
        }

        $languageQuestionKey = $language . ':' . $number;
        if (isset($seenLanguageQuestion[$languageQuestionKey])) {
            fail('qn ' . $number . ' is duplicated for language ' . $language . '.');
        }
        $seenLanguageQuestion[$languageQuestionKey] = true;

        $options = $question['options'] ?? null;
        $minimumOptions = $type === 'numeric' ? 1 : 2;
        if (!is_array($options) || count($options) < $minimumOptions) {
            fail('Question ' . $number . ' requires at least ' . $minimumOptions . ' option(s).');
        }

        $optionValues = [];
        $assignedLegacyValues = false;
        foreach (array_values($options) as $optionOffset => &$option) {
            if (!is_array($option)) {
                fail('Question ' . $number . ' option ' . ($optionOffset + 1) . ' must be an object.');
            }
            if (trim((string) ($option['text'] ?? '')) === '') {
                fail('Question ' . $number . ' option ' . ($optionOffset + 1) . ' has no text.');
            }
            if (!array_key_exists('value', $option)) {
                $option['value'] = $optionOffset + 1;
                $assignedLegacyValues = true;
            }
            if (!is_int($option['value']) && !(is_string($option['value']) && ctype_digit($option['value']))) {
                fail('Question ' . $number . ' option values must be positive integers.');
            }
            $option['value'] = (int) $option['value'];
            if ($option['value'] < 1 || in_array($option['value'], $optionValues, true)) {
                fail('Question ' . $number . ' option values must be unique positive integers.');
            }
            $optionValues[] = $option['value'];
        }
        unset($option);
        if ($assignedLegacyValues) {
            $warnings[] = 'Question ' . $number . ' language ' . $language
                . ': assigned stable numeric values to legacy options.';
        }

        sort($optionValues);
        if (isset($definitions[$number])) {
            $definition = $definitions[$number];
            if ($definition['indicator_key'] !== $indicatorKey) {
                fail('Translations for qn ' . $number . ' use different indicator_key values.');
            }
            if ($definition['report_type'] !== $type) {
                fail('Translations for indicator ' . $indicatorKey . ' use different report_type values.');
            }
            if ($definition['option_values'] !== $optionValues) {
                fail('Translations for indicator ' . $indicatorKey . ' use different option values.');
            }
        } else {
            $definitions[$number] = [
                'indicator_key' => $indicatorKey,
                'report_type' => $type,
                'option_values' => $optionValues,
                'languages' => [],
            ];
        }
        $definitions[$number]['languages'][$language] = true;

        $question['qn'] = $number;
        $question['lang'] = (string) $language;
        $question['ques'] = $text;
        $question['report_type'] = $type;
        $question['indicator_key'] = $indicatorKey;
        $question['question_id'] = $indicatorKey . '_V' . $versionToken;
        $question['options'] = array_values($options);
        $normalized[] = $question;
    }

    foreach ($definitions as $number => $definition) {
        if (!isset($definition['languages'][1], $definition['languages'][2])) {
            fail('Question ' . $number . ' must be supplied in both language 1 and language 2.');
        }
    }

    usort(
        $normalized,
        static fn(array $left, array $right): int =>
            [(int) $left['lang'], (int) $left['qn']]
            <=> [(int) $right['lang'], (int) $right['qn']]
    );
    return $normalized;
}

function questionDefinitions(array $questions): array
{
    $definitions = [];
    foreach ($questions as $question) {
        if (!is_array($question) || (int) ($question['lang'] ?? 0) !== 1) {
            continue;
        }
        $indicatorKey = strtoupper(trim((string) ($question['indicator_key'] ?? '')));
        if ($indicatorKey === '') {
            continue;
        }
        $optionSignature = [];
        foreach (array_values($question['options'] ?? []) as $index => $option) {
            if (!is_array($option)) {
                continue;
            }
            $optionSignature[] = [
                'value' => (int) ($option['value'] ?? ($index + 1)),
                'score' => $option['score'] ?? null,
                'sentiment' => $option['sentiment'] ?? null,
                'status' => $option['status'] ?? null,
            ];
        }
        $definitions[$indicatorKey] = [
            'qn' => (int) ($question['qn'] ?? 0),
            'report_type' => strtolower(trim((string) ($question['report_type'] ?? 'rating'))),
            'text' => trim((string) ($question['ques'] ?? '')),
            'options' => $optionSignature,
        ];
    }
    return $definitions;
}

function compareVersions(array $active, array $candidate): array
{
    $activeDefinitions = questionDefinitions($active);
    $candidateDefinitions = questionDefinitions($candidate);
    $added = array_values(array_diff(array_keys($candidateDefinitions), array_keys($activeDefinitions)));
    $removed = array_values(array_diff(array_keys($activeDefinitions), array_keys($candidateDefinitions)));
    $retained = array_values(array_intersect(array_keys($activeDefinitions), array_keys($candidateDefinitions)));
    $textChanged = [];
    $moved = [];
    $optionsChanged = [];

    foreach ($retained as $indicatorKey) {
        $before = $activeDefinitions[$indicatorKey];
        $after = $candidateDefinitions[$indicatorKey];
        if ($before['report_type'] !== $after['report_type']) {
            fail(
                'Indicator ' . $indicatorKey . ' changes report_type from '
                . $before['report_type'] . ' to ' . $after['report_type']
                . '. Use a new indicator_key for a changed meaning.'
            );
        }
        if ($before['qn'] !== $after['qn']) {
            $moved[] = $indicatorKey . ' (' . $before['qn'] . ' -> ' . $after['qn'] . ')';
        }
        if ($before['text'] !== $after['text']) {
            $textChanged[] = $indicatorKey;
        }
        if ($before['options'] !== $after['options']) {
            if ($before['report_type'] === 'rating') {
                fail(
                    'Rating indicator ' . $indicatorKey
                    . ' changes its value/score scale. Use a new indicator_key.'
                );
            }
            $optionsChanged[] = $indicatorKey;
        }
    }

    sort($added);
    sort($removed);
    sort($textChanged);
    sort($moved);
    sort($optionsChanged);
    return compact('added', 'removed', 'retained', 'textChanged', 'moved', 'optionsChanged');
}

function printList(string $label, array $items): void
{
    echo $label, ': ', $items === [] ? 'None' : implode(', ', $items), PHP_EOL;
}

$options = getopt('', ['department:', 'version:', 'source:', 'publish']);
$departmentId = filter_var($options['department'] ?? null, FILTER_VALIDATE_INT);
$version = trim((string) ($options['version'] ?? ''));
$source = trim((string) ($options['source'] ?? ''));
$publish = array_key_exists('publish', $options);

if ($departmentId === false || $departmentId < 1 || $departmentId > 30) {
    fail('Use --department with a valid configured department ID.');
}
if (!preg_match('/^\d+\.\d+(?:\.\d+)?$/', $version)) {
    fail('Use --version with a value such as 1.1 or 2.0.');
}
if ($source === '') {
    fail('Use --source with the path to the candidate survey JSON.');
}

$projectRoot = dirname(__DIR__);
$packageDirectory = $projectRoot . '/api/masters/surveys/department_' . $departmentId;
$manifestFile = $packageDirectory . '/manifest.json';
$manifest = readJsonFile($manifestFile);
$activeVersion = trim((string) ($manifest['active_version'] ?? ''));
if ($activeVersion === '' || !version_compare($version, $activeVersion, '>')) {
    fail('The new version must be greater than active version ' . $activeVersion . '.');
}
foreach (($manifest['versions'] ?? []) as $existingVersion) {
    if ((string) ($existingVersion['version'] ?? '') === $version) {
        fail('Version ' . $version . ' already exists and cannot be overwritten.');
    }
}

$activeEntry = null;
foreach (($manifest['versions'] ?? []) as $entry) {
    if ((string) ($entry['version'] ?? '') === $activeVersion) {
        $activeEntry = $entry;
        break;
    }
}
if (!is_array($activeEntry)) {
    fail('The active manifest entry is missing.');
}
$activeFile = $packageDirectory . '/' . ltrim((string) ($activeEntry['path'] ?? ''), '/\\');
$activeQuestions = readJsonFile($activeFile);
$activeHash = hash_file('sha256', $activeFile);
if (!hash_equals(strtolower((string) ($activeEntry['schema_hash'] ?? '')), strtolower((string) $activeHash))) {
    fail('The active survey file does not match its published manifest hash.');
}

$warnings = [];
$candidateQuestions = normalizeQuestions(readJsonFile($source), $version, $warnings);
$impact = compareVersions($activeQuestions, $candidateQuestions);
$encodedSurvey = json_encode(
    $candidateQuestions,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
) . PHP_EOL;
$candidateHash = hash('sha256', $encodedSurvey);

echo 'Abhipraya survey version ', $publish ? 'publish' : 'dry run', PHP_EOL;
echo 'Department: ', $departmentId, PHP_EOL;
echo 'Active version: ', $activeVersion, PHP_EOL;
echo 'Candidate version: ', $version, PHP_EOL;
echo 'Schema hash: ', $candidateHash, PHP_EOL;
echo 'Question records: ', count($candidateQuestions), PHP_EOL;
printList('Added indicators', $impact['added']);
printList('Removed indicators', $impact['removed']);
printList('Reworded indicators', $impact['textChanged']);
printList('Moved indicators', $impact['moved']);
printList('Changed category options', $impact['optionsChanged']);
if ($warnings !== []) {
    echo 'Normalizations:', PHP_EOL;
    foreach ($warnings as $warning) {
        echo '  - ', $warning, PHP_EOL;
    }
}

if (!$publish) {
    echo 'Validation passed. No files were changed.', PHP_EOL;
    echo 'Run again with --publish to activate version ', $version, '.', PHP_EOL;
    exit(0);
}

$versionDirectory = $packageDirectory . '/v' . $version;
if (file_exists($versionDirectory)) {
    fail('Target version directory already exists and cannot be overwritten.');
}
if (!mkdir($versionDirectory, 0775, true) && !is_dir($versionDirectory)) {
    fail('Unable to create the target version directory.');
}

$surveyFile = $versionDirectory . '/survey.json';
try {
    if (file_put_contents($surveyFile, $encodedSurvey, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write the new survey file.');
    }
    $writtenHash = hash_file('sha256', $surveyFile);
    if (!hash_equals($candidateHash, (string) $writtenHash)) {
        throw new RuntimeException('The written survey failed its hash verification.');
    }

    foreach ($manifest['versions'] as &$entry) {
        if ((string) ($entry['version'] ?? '') === $activeVersion) {
            $entry['status'] = 'retired';
        }
    }
    unset($entry);
    $manifest['active_version'] = $version;
    $manifest['versions'][] = [
        'version' => $version,
        'status' => 'published',
        'published_at' => date('Y-m-d'),
        'path' => 'v' . $version . '/survey.json',
        'schema_hash' => $candidateHash,
    ];
    $encodedManifest = json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
    if (file_put_contents($manifestFile, $encodedManifest, LOCK_EX) === false) {
        throw new RuntimeException('Unable to update the survey manifest.');
    }
} catch (Throwable $exception) {
    if (is_file($surveyFile)) {
        unlink($surveyFile);
    }
    if (is_dir($versionDirectory)) {
        rmdir($versionDirectory);
    }
    fail($exception->getMessage());
}

echo 'Published and activated version ', $version, '.', PHP_EOL;

<?php
declare(strict_types=1);

final class SurveyConfig
{
    private const MASTER_DIR = __DIR__ . '/../masters';

    /** @var array<string, array<int|string, mixed>> */
    private static array $jsonCache = [];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $facilitiesByNin = null;

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $englishFacilities = null;

    public static function resolveReference(string $reference, ?string $version = null): array
    {
        if (!preg_match('/^(\d{6,20})_(\d{1,2})$/', trim($reference), $matches)) {
            throw new InvalidArgumentException('Invalid survey reference.');
        }

        $facility = self::findFacility($matches[1]);
        $department = self::findDepartment((int) $matches[2]);
        $departmentId = (int) $department['departmentId'];
        $manifestFile = self::MASTER_DIR . '/surveys/department_' . $departmentId . '/manifest.json';
        $surveyCode = 'DEPARTMENT_' . $departmentId . '_FEEDBACK';
        $surveyVersion = $version !== null && trim($version) !== '' ? trim($version) : '1.0';
        $questionFile = self::MASTER_DIR . '/dept_id_' . $departmentId . '.json';
        $expectedSchemaHash = '';

        if (is_file($manifestFile)) {
            $manifest = self::readJson($manifestFile);
            $surveyCode = trim((string) ($manifest['survey_code'] ?? $surveyCode));
            $surveyVersion = $version !== null && trim($version) !== ''
                ? trim($version)
                : trim((string) ($manifest['active_version'] ?? ''));
            if (!preg_match('/^\d+\.\d+(?:\.\d+)?$/', $surveyVersion)) {
                throw new RuntimeException('The survey manifest has an invalid active version.');
            }

            $configuredVersion = null;
            foreach (($manifest['versions'] ?? []) as $candidate) {
                if (is_array($candidate) && (string) ($candidate['version'] ?? '') === $surveyVersion) {
                    $configuredVersion = $candidate;
                    break;
                }
            }
            if ($configuredVersion === null) {
                throw new InvalidArgumentException('The requested survey version is unavailable.');
            }
            $status = strtolower(trim((string) ($configuredVersion['status'] ?? '')));
            if (!in_array($status, ['published', 'retired'], true)) {
                throw new InvalidArgumentException('The requested survey version is not published.');
            }
            $relativePath = trim((string) ($configuredVersion['path'] ?? ''));
            $expectedSchemaHash = strtolower(trim((string) ($configuredVersion['schema_hash'] ?? '')));
            $questionFile = $relativePath !== ''
                ? self::MASTER_DIR . '/surveys/department_' . $departmentId . '/' . ltrim($relativePath, '/\\')
                : self::MASTER_DIR . '/surveys/department_' . $departmentId . '/v' . $surveyVersion . '/survey.json';
        } elseif ($version !== null && trim($version) !== '' && trim($version) !== '1.0') {
            throw new InvalidArgumentException('The requested survey version is unavailable.');
        }

        if (!is_file($questionFile)) {
            throw new RuntimeException('No survey questions are configured for this department.');
        }
        $schemaHash = hash_file('sha256', $questionFile);
        if (!is_string($schemaHash) || $schemaHash === '') {
            throw new RuntimeException('The survey configuration hash could not be calculated.');
        }
        if ($expectedSchemaHash !== '' && !hash_equals($expectedSchemaHash, strtolower($schemaHash))) {
            throw new RuntimeException(
                'The published survey file does not match its manifest. Publish a new version instead of editing it.'
            );
        }

        $radiusSettings = self::radiusSettings();
        // Survey geofencing is configured exclusively in masters/radius.json.
        // A per-facility value can be added there when required; values in the
        // facility master are descriptive data and must not change this rule.
        $facilityRadius = $radiusSettings['facility_overrides'][(string) $matches[1]]
            ?? $radiusSettings['default_radius_meters'];
        $geoRequired = !in_array((string) $matches[1], $radiusSettings['geo_bypass_facilities'], true);
        $geoRadius = max(
            (int) $radiusSettings['minimum_radius_meters'],
            min((int) $radiusSettings['maximum_radius_meters'], (int) $facilityRadius)
        );

        return [
            'reference' => $matches[1] . '_' . $department['departmentId'],
            'facility' => $facility,
            'department' => $department,
            'geo_radius_meters' => $geoRadius,
            'geo_required' => $geoRequired,
            'duplicate_window_hours' => (int) $radiusSettings['duplicate_window_hours'],
            'question_file' => $questionFile,
            'survey_code' => $surveyCode,
            'survey_version' => $surveyVersion,
            'survey_schema_hash' => $schemaHash,
        ];
    }

    public static function questions(array $context, int $language): array
    {
        $all = self::readJson($context['question_file']);
        $questions = array_values(array_filter($all, static fn(array $item): bool => (int) ($item['lang'] ?? 0) === $language));

        if ($questions === []) {
            throw new InvalidArgumentException('Questions are not available in the selected language.');
        }

        usort($questions, static fn(array $left, array $right): int => (int) $left['qn'] <=> (int) $right['qn']);
        $departmentId = (int) ($context['department']['departmentId'] ?? 0);
        // The OPD public survey uses the first ten service-rating questions only.
        if ($departmentId === 4) {
            $questions = array_values(array_filter(
                $questions,
                static fn(array $question): bool => (int) ($question['qn'] ?? 0) >= 1 && (int) ($question['qn'] ?? 0) <= 10
            ));
        }
        $versionToken = preg_replace('/[^0-9A-Za-z]+/', '_', (string) ($context['survey_version'] ?? '1.0'));
        return array_map(static function (array $question) use ($departmentId, $versionToken): array {
            $number = (int) ($question['qn'] ?? 0);
            $question['indicator_key'] = trim((string) ($question['indicator_key'] ?? ''))
                ?: 'DEPT_' . $departmentId . '_Q' . $number;
            $question['question_id'] = trim((string) ($question['question_id'] ?? ''))
                ?: $question['indicator_key'] . '_V' . $versionToken;
            $question['storage_column'] = $number >= 1 && $number <= 31
                ? 'srvy_Q' . ($number - 1)
                : null;
            return $question;
        }, $questions);
    }

    /** @return array<string, string> */
    public static function buttonLabels(int $language): array
    {
        $labels = ['1' => 'Start Survey', '2' => 'Previous', '3' => 'Next', '4' => 'Submit'];
        foreach (self::readJson(self::MASTER_DIR . '/buttons.json') as $button) {
            if ((int) ($button['lang'] ?? 0) === $language && isset($button['btn'], $button['txt'])) {
                $labels[(string) $button['btn']] = (string) $button['txt'];
            }
        }
        return $labels;
    }

    /** @return array{icon: string, message: string} */
    public static function thankYouMessage(int $language): array
    {
        foreach (self::readJson(self::MASTER_DIR . '/thankyou_messages.json') as $message) {
            if ((int) ($message['lang'] ?? 0) === $language) {
                return [
                    'icon' => (string) ($message['icon'] ?? ''),
                    'message' => (string) ($message['message'] ?? ''),
                ];
            }
        }

        return [
            'icon' => '',
            'message' => 'Thank you for your feedback.',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function facilities(string $search = ''): array
    {
        $search = strtolower(trim($search));
        $facilities = self::englishFacilities();
        if ($search === '') {
            return $facilities;
        }

        return array_values(array_filter($facilities, static fn(array $facility): bool =>
            str_contains(strtolower((string) ($facility['facilityNIN'] ?? '')), $search)
            || str_contains(strtolower((string) ($facility['facilityName'] ?? '')), $search)
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public static function departments(int $language = 1): array
    {
        $language = in_array($language, [1, 2], true) ? $language : 1;
        $departments = array_values(array_filter(
            self::readJson(self::MASTER_DIR . '/departmet.json'),
            static fn(array $department): bool => (int) ($department['langCode'] ?? 1) === $language
        ));
        usort($departments, static fn(array $left, array $right): int => (int) ($left['departmentId'] ?? 0) <=> (int) ($right['departmentId'] ?? 0));
        return $departments;
    }

    /** @return array<int, string> */
    public static function surveyVersions(?int $departmentId = null): array
    {
        $versions = [];
        $departmentIds = $departmentId !== null
            ? [$departmentId]
            : array_map(
                static fn(array $department): int => (int) ($department['departmentId'] ?? 0),
                self::departments()
            );

        foreach (array_unique($departmentIds) as $configuredDepartmentId) {
            if ($configuredDepartmentId < 1) {
                continue;
            }
            $manifestFile = self::MASTER_DIR . '/surveys/department_'
                . $configuredDepartmentId . '/manifest.json';
            if (!is_file($manifestFile)) {
                continue;
            }
            foreach ((self::readJson($manifestFile)['versions'] ?? []) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $version = trim((string) ($entry['version'] ?? ''));
                $status = strtolower(trim((string) ($entry['status'] ?? '')));
                if (
                    preg_match('/^\d+\.\d+(?:\.\d+)?$/', $version)
                    && in_array($status, ['published', 'retired'], true)
                ) {
                    $versions[$version] = true;
                }
            }
        }

        $result = array_keys($versions);
        usort($result, static fn(string $left, string $right): int => version_compare($right, $left));
        return $result;
    }

    public static function surveyUrl(string $reference): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Keep the public URL compatible with the existing live QR posters.
        // The `nin` parameter contains `facilityNIN_departmentId`.
        $default = $scheme . '://' . $host . '/question.php';
        $baseUrl = rtrim((string) Env::get('SURVEY_PUBLIC_BASE_URL', $default), '?');
        return $baseUrl . '?nin=' . rawurlencode($reference);
    }

    public static function distanceMeters(float $fromLat, float $fromLong, float $toLat, float $toLong): float
    {
        $earthRadius = 6371000.0;
        $latDelta = deg2rad($toLat - $fromLat);
        $longDelta = deg2rad($toLong - $fromLong);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($longDelta / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private static function findFacility(string $nin): array
    {
        $facilities = self::facilitiesByNin();
        if (isset($facilities[$nin])) {
            return $facilities[$nin];
        }
        throw new InvalidArgumentException('Facility was not found.');
    }

    /** @return array<int, array<string, mixed>> */
    private static function englishFacilities(): array
    {
        if (self::$englishFacilities !== null) {
            return self::$englishFacilities;
        }

        self::$englishFacilities = array_values(self::facilitiesByNin());
        usort(self::$englishFacilities, static fn(array $left, array $right): int => strcmp(
            (string) ($left['facilityName'] ?? ''),
            (string) ($right['facilityName'] ?? '')
        ));
        return self::$englishFacilities;
    }

    /** @return array<string, array<string, mixed>> */
    private static function facilitiesByNin(): array
    {
        if (self::$facilitiesByNin !== null) {
            return self::$facilitiesByNin;
        }

        self::$facilitiesByNin = [];
        foreach (self::readJson(self::MASTER_DIR . '/facilityCodes.json') as $facility) {
            if (!is_array($facility) || (int) ($facility['langCode'] ?? 1) !== 1) {
                continue;
            }
            $nin = trim((string) ($facility['facilityNIN'] ?? ''));
            if ($nin !== '') {
                self::$facilitiesByNin[$nin] = $facility;
            }
        }
        return self::$facilitiesByNin;
    }

    private static function findDepartment(int $departmentId): array
    {
        foreach (self::readJson(self::MASTER_DIR . '/departmet.json') as $department) {
            if ((int) ($department['departmentId'] ?? 0) === $departmentId && (int) ($department['langCode'] ?? 1) === 1) {
                return $department;
            }
        }
        throw new InvalidArgumentException('Department was not found.');
    }

    private static function readJson(string $file): array
    {
        if (isset(self::$jsonCache[$file])) {
            return self::$jsonCache[$file];
        }
        if (!is_file($file)) {
            throw new RuntimeException('Required survey configuration is unavailable.');
        }
        try {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid JSON in ' . basename($file) . '.', 0, $exception);
        }
        if (!is_array($data)) {
            throw new RuntimeException('Invalid survey configuration.');
        }
        return self::$jsonCache[$file] = $data;
    }

    /** @return array<string, mixed> */
    private static function radiusSettings(): array
    {
        $settings = self::readJson(self::MASTER_DIR . '/radius.json');
        $settings['default_radius_meters'] = max(1, (int) ($settings['default_radius_meters'] ?? 200));
        $settings['minimum_radius_meters'] = max(1, (int) ($settings['minimum_radius_meters'] ?? 25));
        $settings['maximum_radius_meters'] = max((int) $settings['minimum_radius_meters'], (int) ($settings['maximum_radius_meters'] ?? 5000));
        $settings['duplicate_window_hours'] = max(1, min(168, (int) ($settings['duplicate_window_hours'] ?? 24)));
        $settings['facility_overrides'] = is_array($settings['facility_overrides'] ?? null) ? $settings['facility_overrides'] : [];
        $settings['geo_bypass_facilities'] = is_array($settings['geo_bypass_facilities'] ?? null)
            ? array_map('strval', $settings['geo_bypass_facilities'])
            : [];
        return $settings;
    }

    private function __construct() {}
}

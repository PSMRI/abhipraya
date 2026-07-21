<?php
declare(strict_types=1);

final class SurveyConfig
{
    private const MASTER_DIR = __DIR__ . '/../masters';

    public static function resolveReference(string $reference): array
    {
        if (!preg_match('/^(\d{6,20})_(\d{1,2})$/', trim($reference), $matches)) {
            throw new InvalidArgumentException('Invalid survey reference.');
        }

        $facility = self::findFacility($matches[1]);
        $department = self::findDepartment((int) $matches[2]);
        $questionFile = self::MASTER_DIR . '/dept_id_' . $department['departmentId'] . '.json';

        if (!is_file($questionFile)) {
            throw new RuntimeException('No survey questions are configured for this department.');
        }

        return [
            'reference' => $matches[1] . '_' . $department['departmentId'],
            'facility' => $facility,
            'department' => $department,
            'geo_radius_meters' => max(1, (int) ($facility['facilityGeoRadiusMeters'] ?? Env::get('SURVEY_DEFAULT_GEO_RADIUS_METERS', '200'))),
            'question_file' => $questionFile,
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
        return $questions;
    }

    /** @return array<int, array<string, mixed>> */
    public static function facilities(string $search = ''): array
    {
        $search = strtolower(trim($search));
        $facilities = array_values(array_filter(
            self::readJson(self::MASTER_DIR . '/facilityCodes.json'),
            static function (array $facility) use ($search): bool {
                if ((int) ($facility['langCode'] ?? 1) !== 1) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                return str_contains(strtolower((string) ($facility['facilityNIN'] ?? '')), $search)
                    || str_contains(strtolower((string) ($facility['facilityName'] ?? '')), $search);
            }
        ));

        usort($facilities, static fn(array $left, array $right): int => strcmp(
            (string) ($left['facilityName'] ?? ''),
            (string) ($right['facilityName'] ?? '')
        ));

        return $facilities;
    }

    /** @return array<int, array<string, mixed>> */
    public static function departments(): array
    {
        $departments = array_values(array_filter(
            self::readJson(self::MASTER_DIR . '/departmet.json'),
            static fn(array $department): bool => (int) ($department['langCode'] ?? 1) === 1
        ));
        usort($departments, static fn(array $left, array $right): int => (int) ($left['departmentId'] ?? 0) <=> (int) ($right['departmentId'] ?? 0));
        return $departments;
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
        foreach (self::readJson(self::MASTER_DIR . '/facilityCodes.json') as $facility) {
            if ((string) ($facility['facilityNIN'] ?? '') === $nin && (int) ($facility['langCode'] ?? 1) === 1) {
                return $facility;
            }
        }
        throw new InvalidArgumentException('Facility was not found.');
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
        return $data;
    }

    private function __construct() {}
}

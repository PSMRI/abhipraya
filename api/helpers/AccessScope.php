<?php
declare(strict_types=1);

/** Server-side facility scope for state, district, block, and facility accounts. */
final class AccessScope
{
    /** @return array<int, string>|null Null means state-wide access. */
    public static function facilityNins(): ?array
    {
        $roleId = SessionManager::roleId();
        if (in_array($roleId, [1, 3, 4, 5, 6], true)) {
            return null;
        }
        if ($roleId === 2) {
            $nin = trim((string) SessionManager::facilityId());
            return $nin === '' ? [] : [$nin];
        }

        $user = SessionManager::user();
        $scopeValue = $roleId === 7 ? (string) ($user['dist_id'] ?? '') : (string) ($user['block_id'] ?? '');
        if (!in_array($roleId, [7, 8], true) || $scopeValue === '' || $scopeValue === '0') {
            return [];
        }
        $scopeKey = $roleId === 7 ? 'districtCode' : 'blockCode';
        return array_values(array_map(
            static fn(array $facility): string => (string) ($facility['facilityNIN'] ?? ''),
            array_filter(SurveyConfig::facilities(), static fn(array $facility): bool =>
                (string) ($facility[$scopeKey] ?? '') === $scopeValue
        )));
    }

    public static function assertFacilityAllowed(string $facilityNin): void
    {
        $allowed = self::facilityNins();
        if ($allowed !== null && !in_array($facilityNin, $allowed, true)) {
            Response::forbidden('This facility is outside your assigned scope.');
        }
    }

    /** @param array<int, array<string, mixed>> $facilities */
    public static function filterFacilities(array $facilities): array
    {
        $allowed = self::facilityNins();
        if ($allowed === null) return $facilities;
        return array_values(array_filter($facilities, static fn(array $facility): bool =>
            in_array((string) ($facility['facilityNIN'] ?? $facility['facility_nin'] ?? ''), $allowed, true)
        ));
    }

    private function __construct() {}
}

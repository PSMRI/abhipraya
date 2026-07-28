<?php
declare(strict_types=1);

final class BoundaryCatalog
{
    private const MASTER_FILE = __DIR__ . '/../masters/facilityCodes.json';

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        $json = file_get_contents(self::MASTER_FILE);
        if ($json === false) {
            throw new RuntimeException('Boundary configuration is unavailable.');
        }
        $facilities = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($facilities)) {
            throw new RuntimeException('Boundary configuration is invalid.');
        }

        $states = [];
        $districts = [];
        $blocks = [];
        $facilityItems = [];
        foreach ($facilities as $facility) {
            if (!is_array($facility) || (int) ($facility['langCode'] ?? 1) !== 1) {
                continue;
            }
            $nin = trim((string) ($facility['facilityNIN'] ?? ''));
            if ($nin === '') {
                continue;
            }
            $state = trim((string) ($facility['stateCode'] ?? '')) ?: 'unknown';
            $district = trim((string) ($facility['districtCode'] ?? '')) ?: 'unknown';
            $block = trim((string) ($facility['blockCode'] ?? '')) ?: 'unknown';
            $stateCode = 'state-' . $state;
            $districtCode = 'district-' . $state . '-' . $district;
            $blockCode = 'block-' . $state . '-' . $district . '-' . $block;
            $states[$stateCode] = ['code' => $stateCode, 'level' => 'state', 'name' => 'State ' . $state, 'parent' => null];
            $districts[$districtCode] = ['code' => $districtCode, 'level' => 'district', 'name' => 'District ' . $district, 'parent' => $stateCode];
            $blocks[$blockCode] = ['code' => $blockCode, 'level' => 'block', 'name' => 'Block ' . $block, 'parent' => $districtCode];
            $facilityItems[] = [
                'code' => 'facility-' . $nin,
                'level' => 'facility',
                'name' => trim((string) ($facility['facilityName'] ?? $nin)),
                'parent' => $blockCode,
                'facility_nin' => $nin,
                'facility_type' => trim((string) ($facility['facilityType'] ?? '')),
                'address' => trim((string) ($facility['facilityAddress'] ?? '')),
            ];
        }
        $items = array_merge(array_values($states), array_values($districts), array_values($blocks), $facilityItems);
        usort($items, static fn(array $left, array $right): int => strcmp($left['code'], $right['code']));
        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    public static function search(string $level = '', string $parent = '', string $search = ''): array
    {
        $level = strtolower(trim($level));
        $parent = trim($parent);
        $search = strtolower(trim($search));
        return array_values(array_filter(self::all(), static function (array $item) use ($level, $parent, $search): bool {
            if ($level !== '' && ($item['level'] ?? '') !== $level) {
                return false;
            }
            if ($parent !== '' && ($item['parent'] ?? '') !== $parent) {
                return false;
            }
            return $search === ''
                || str_contains(strtolower((string) ($item['code'] ?? '')), $search)
                || str_contains(strtolower((string) ($item['name'] ?? '')), $search);
        }));
    }

    /** @return array<string, mixed>|null */
    public static function find(string $code): ?array
    {
        foreach (self::all() as $item) {
            if (hash_equals((string) $item['code'], trim($code))) {
                return $item;
            }
        }
        return null;
    }
}

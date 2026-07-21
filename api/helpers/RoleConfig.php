<?php
declare(strict_types=1);

/**
 * Loads role names from the versioned role master.  The UI never needs to
 * duplicate a role label or assume that a numeric role ID has a fixed name.
 */
final class RoleConfig
{
    private const FILE = __DIR__ . '/../masters/rolecode.json';

    public static function nameForId(int $roleId): string
    {
        if (!is_file(self::FILE)) {
            return 'Administrator';
        }

        try {
            $roles = json_decode((string) file_get_contents(self::FILE), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'Administrator';
        }

        foreach (is_array($roles) ? $roles : [] as $role) {
            if ((int) ($role['role_id'] ?? 0) === $roleId && (int) ($role['active_status'] ?? 0) === 1) {
                return trim((string) ($role['role_name'] ?? '')) ?: 'Administrator';
            }
        }

        return 'Administrator';
    }

    private function __construct() {}
}

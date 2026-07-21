<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Role repository.
 *
 * Responsibilities:
 * - Read roles
 * - Create, update and delete roles
 * - Read permission records
 * - Manage role-permission assignments
 *
 * Business rules must remain inside RoleService.
 */
final class RoleRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Return a filtered and paginated role list.
     *
     * Supported filters:
     * - search
     * - status
     *
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     pagination: array{
     *         page: int,
     *         limit: int,
     *         total: int,
     *         total_pages: int
     *     }
     * }
     */
    public function paginate(
        array $filters = [],
        int $page = 1,
        int $limit = 20
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $where = [];
        $parameters = [];

        if (
            isset($filters['search'])
            && trim((string) $filters['search']) !== ''
        ) {
            $where[] = '(
                r.role_code LIKE :search
                OR r.role_name LIKE :search
                OR r.description LIKE :search
            )';

            $parameters['search'] =
                '%' . trim((string) $filters['search']) . '%';
        }

        if (
            array_key_exists('status', $filters)
            && $filters['status'] !== null
            && $filters['status'] !== ''
        ) {
            $where[] = 'r.active_status = :status';
            $parameters['status'] =
                (int) $filters['status'];
        }

        $whereSql = $where !== []
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        try {
            $countStatement = $this->pdo->prepare(
                "SELECT COUNT(*) AS total
                 FROM role_master r
                 {$whereSql}"
            );

            $this->bindParameters(
                $countStatement,
                $parameters
            );

            $countStatement->execute();

            $total = (int) (
                $countStatement->fetch()['total']
                ?? 0
            );

            $listStatement = $this->pdo->prepare(
                "SELECT
                    r.role_id,
                    r.role_code,
                    r.role_name,
                    r.description,
                    r.is_system_role,
                    r.active_status,
                    r.created_at,
                    r.updated_at,
                    COUNT(
                        DISTINCT ur.user_id
                    ) AS assigned_user_count,
                    COUNT(
                        DISTINCT rp.permission_id
                    ) AS permission_count
                 FROM role_master r
                 LEFT JOIN user_role ur
                    ON ur.role_id = r.role_id
                 LEFT JOIN role_permission rp
                    ON rp.role_id = r.role_id
                 {$whereSql}
                 GROUP BY
                    r.role_id,
                    r.role_code,
                    r.role_name,
                    r.description,
                    r.is_system_role,
                    r.active_status,
                    r.created_at,
                    r.updated_at
                 ORDER BY
                    r.is_system_role DESC,
                    r.role_name ASC
                 LIMIT :limit
                 OFFSET :offset"
            );

            $this->bindParameters(
                $listStatement,
                $parameters
            );

            $listStatement->bindValue(
                ':limit',
                $limit,
                PDO::PARAM_INT
            );

            $listStatement->bindValue(
                ':offset',
                $offset,
                PDO::PARAM_INT
            );

            $listStatement->execute();

            $items = array_map(
                static function (array $row): array {
                    return [
                        'id' =>
                            (int) $row['role_id'],
                        'code' =>
                            (string) $row['role_code'],
                        'name' =>
                            (string) $row['role_name'],
                        'description' =>
                            $row['description'] ?? null,
                        'active' =>
                            (int) $row['active_status'] === 1,
                        'is_system_role' =>
                            (int) $row['is_system_role'] === 1,
                        'assigned_user_count' =>
                            (int) $row['assigned_user_count'],
                        'permission_count' =>
                            (int) $row['permission_count'],
                        'created_at' =>
                            $row['created_at'] ?? null,
                        'updated_at' =>
                            $row['updated_at'] ?? null,
                    ];
                },
                $listStatement->fetchAll()
            );

            return [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' =>
                        $total > 0
                            ? (int) ceil($total / $limit)
                            : 0,
                ],
            ];
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to paginate roles.',
                $exception,
                [
                    'filters' => $filters,
                    'page' => $page,
                    'limit' => $limit,
                ]
            );

            throw new RuntimeException(
                'Unable to load roles.'
            );
        }
    }

    /**
     * Find a role by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(
        int $roleId
    ): ?array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    role_id,
                    role_code,
                    role_name,
                    description,
                    is_system_role,
                    active_status,
                    created_at,
                    updated_at
                 FROM role_master
                 WHERE role_id = :role_id
                 LIMIT 1'
            );

            $statement->execute([
                'role_id' => $roleId,
            ]);

            $role = $statement->fetch();

            return is_array($role)
                ? $role
                : null;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to find role by ID.',
                $exception,
                [
                    'role_id' => $roleId,
                ]
            );

            throw new RuntimeException(
                'Unable to load role.'
            );
        }
    }

    /**
     * Find a role by code.
     *
     * @return array<string, mixed>|null
     */
    public function findByCode(
        string $roleCode
    ): ?array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    role_id,
                    role_code,
                    role_name,
                    description,
                    is_system_role,
                    active_status,
                    created_at,
                    updated_at
                 FROM role_master
                 WHERE UPPER(role_code) = :role_code
                 LIMIT 1'
            );

            $statement->execute([
                'role_code' =>
                    strtoupper(trim($roleCode)),
            ]);

            $role = $statement->fetch();

            return is_array($role)
                ? $role
                : null;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to find role by code.',
                $exception,
                [
                    'role_code' => $roleCode,
                ]
            );

            throw new RuntimeException(
                'Unable to load role.'
            );
        }
    }

    /**
     * Create a role.
     *
     * Expected keys:
     * - role_code
     * - role_name
     * - description
     * - is_system_role
     * - active_status
     *
     * @param array<string, mixed> $data
     */
    public function create(
        array $data
    ): int {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO role_master
                    (
                        role_code,
                        role_name,
                        description,
                        is_system_role,
                        active_status,
                        created_at,
                        updated_at
                    )
                 VALUES
                    (
                        :role_code,
                        :role_name,
                        :description,
                        :is_system_role,
                        :active_status,
                        NOW(),
                        NOW()
                    )'
            );

            $statement->bindValue(
                ':role_code',
                strtoupper(
                    trim(
                        (string) $data['role_code']
                    )
                ),
                PDO::PARAM_STR
            );

            $statement->bindValue(
                ':role_name',
                trim(
                    (string) $data['role_name']
                ),
                PDO::PARAM_STR
            );

            $description = $this->nullableString(
                $data['description'] ?? null
            );

            $statement->bindValue(
                ':description',
                $description,
                $description === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $statement->bindValue(
                ':is_system_role',
                (int) (
                    $data['is_system_role']
                    ?? 0
                ),
                PDO::PARAM_INT
            );

            $statement->bindValue(
                ':active_status',
                (int) (
                    $data['active_status']
                    ?? 1
                ),
                PDO::PARAM_INT
            );

            $statement->execute();

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to create role.',
                $exception,
                [
                    'role_code' =>
                        $data['role_code'] ?? null,
                    'role_name' =>
                        $data['role_name'] ?? null,
                ]
            );

            if ($this->isDuplicateKey($exception)) {
                throw new RuntimeException(
                    'Role code already exists.',
                    409
                );
            }

            throw new RuntimeException(
                'Unable to create role.'
            );
        }
    }

    /**
     * Update role metadata.
     *
     * Role code is intentionally not updated.
     *
     * @param array<string, mixed> $data
     */
    public function update(
        int $roleId,
        array $data
    ): bool {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE role_master
                 SET
                    role_name = :role_name,
                    description = :description,
                    active_status = :active_status,
                    updated_at = NOW()
                 WHERE role_id = :role_id'
            );

            $statement->bindValue(
                ':role_name',
                trim(
                    (string) $data['role_name']
                ),
                PDO::PARAM_STR
            );

            $description = $this->nullableString(
                $data['description'] ?? null
            );

            $statement->bindValue(
                ':description',
                $description,
                $description === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $statement->bindValue(
                ':active_status',
                (int) (
                    $data['active_status']
                    ?? 1
                ),
                PDO::PARAM_INT
            );

            $statement->bindValue(
                ':role_id',
                $roleId,
                PDO::PARAM_INT
            );

            $statement->execute();

            return $statement->rowCount() > 0;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to update role.',
                $exception,
                [
                    'role_id' => $roleId,
                ]
            );

            throw new RuntimeException(
                'Unable to update role.'
            );
        }
    }

    /**
     * Update only role active status.
     */
    public function updateStatus(
        int $roleId,
        int $activeStatus
    ): bool {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE role_master
                 SET
                    active_status = :active_status,
                    updated_at = NOW()
                 WHERE role_id = :role_id'
            );

            $statement->execute([
                'active_status' => $activeStatus,
                'role_id' => $roleId,
            ]);

            return $statement->rowCount() > 0;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to update role status.',
                $exception,
                [
                    'role_id' => $roleId,
                    'active_status' =>
                        $activeStatus,
                ]
            );

            throw new RuntimeException(
                'Unable to update role status.'
            );
        }
    }

    /**
     * Physically delete a role.
     *
     * Service layer must first verify:
     * - role is not a system role
     * - role is not assigned to users
     */
    public function delete(
        int $roleId
    ): bool {
        try {
            $statement = $this->pdo->prepare(
                'DELETE FROM role_master
                 WHERE role_id = :role_id'
            );

            $statement->execute([
                'role_id' => $roleId,
            ]);

            return $statement->rowCount() > 0;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to delete role.',
                $exception,
                [
                    'role_id' => $roleId,
                ]
            );

            if ($this->isForeignKeyViolation($exception)) {
                throw new RuntimeException(
                    'Role is currently in use and cannot be deleted.',
                    409
                );
            }

            throw new RuntimeException(
                'Unable to delete role.'
            );
        }
    }

    /**
     * Check whether a role code already exists.
     */
    public function codeExists(
        string $roleCode,
        ?int $excludeRoleId = null
    ): bool {
        $sql = 'SELECT 1
                FROM role_master
                WHERE UPPER(role_code) = :role_code';

        $parameters = [
            'role_code' =>
                strtoupper(trim($roleCode)),
        ];

        if ($excludeRoleId !== null) {
            $sql .= ' AND role_id <> :exclude_role_id';

            $parameters['exclude_role_id'] =
                $excludeRoleId;
        }

        $sql .= ' LIMIT 1';

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);

            return (bool) $statement->fetchColumn();
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to verify role-code uniqueness.',
                $exception,
                [
                    'role_code' => $roleCode,
                ]
            );

            throw new RuntimeException(
                'Unable to verify role code.'
            );
        }
    }

    /**
     * Return all permissions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllPermissions(): array
    {
        try {
            $statement = $this->pdo->query(
                'SELECT
                    permission_id,
                    permission_code,
                    permission_name,
                    module_name,
                    description
                 FROM permission_master
                 ORDER BY
                    module_name,
                    permission_code'
            );

            return array_map(
                static function (array $row): array {
                    return [
                        'permission_id' =>
                            (int) $row['permission_id'],
                        'permission_code' =>
                            (string) $row['permission_code'],
                        'permission_name' =>
                            (string) $row['permission_name'],
                        'module_name' =>
                            (string) $row['module_name'],
                        'description' =>
                            $row['description'] ?? null,
                    ];
                },
                $statement->fetchAll()
            );
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to load permissions.',
                $exception
            );

            throw new RuntimeException(
                'Unable to load permissions.'
            );
        }
    }

    /**
     * Return permission IDs assigned to a role.
     *
     * @return array<int, int>
     */
    public function getPermissionIds(
        int $roleId
    ): array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT permission_id
                 FROM role_permission
                 WHERE role_id = :role_id
                 ORDER BY permission_id'
            );

            $statement->execute([
                'role_id' => $roleId,
            ]);

            return array_values(array_map(
                static fn (array $row): int =>
                    (int) $row['permission_id'],
                $statement->fetchAll()
            ));
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to load role permission IDs.',
                $exception,
                [
                    'role_id' => $roleId,
                ]
            );

            throw new RuntimeException(
                'Unable to load role permissions.'
            );
        }
    }

    /**
     * Return permission details assigned to a role.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPermissionsByRole(
        int $roleId
    ): array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    p.permission_id,
                    p.permission_code,
                    p.permission_name,
                    p.module_name,
                    p.description
                 FROM role_permission rp
                 INNER JOIN permission_master p
                    ON p.permission_id =
                       rp.permission_id
                 WHERE rp.role_id = :role_id
                 ORDER BY
                    p.module_name,
                    p.permission_code'
            );

            $statement->execute([
                'role_id' => $roleId,
            ]);

            return array_map(
                static function (array $row): array {
                    return [
                        'id' =>
                            (int) $row['permission_id'],
                        'code' =>
                            (string) $row['permission_code'],
                        'name' =>
                            (string) $row['permission_name'],
                        'module' =>
                            (string) $row['module_name'],
                        'description' =>
                            $row['description'] ?? null,
                    ];
                },
                $statement->fetchAll()
            );
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to load permissions assigned to role.',
                $exception,
                [
                    'role_id' => $roleId,
                ]
            );

            throw new RuntimeException(
                'Unable to load role permissions.'
            );
        }
    }

    /**
     * Return permission records by IDs.
     *
     * @param array<int, int> $permissionIds
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPermissionsByIds(
        array $permissionIds
    ): array {
        $permissionIds = array_values(
            array_unique(
                array_map('intval', $permissionIds)
            )
        );

        $permissionIds = array_values(
            array_filter(
                $permissionIds,
                static fn (int $id): bool => $id > 0
            )
        );

        if ($permissionIds === []) {
            return [];
        }

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($permissionIds),
                '?'
            )
        );

        try {
            $statement = $this->pdo->prepare(
                "SELECT
                    permission_id,
                    permission_code,
                    permission_name,
                    module_name,
                    description
                 FROM permission_master
                 WHERE permission_id IN (
                    {$placeholders}
                 )
                 ORDER BY permission_id"
            );

            foreach (
                $permissionIds as $index => $permissionId
            ) {
                $statement->bindValue(
                    $index + 1,
                    $permissionId,
                    PDO::PARAM_INT
                );
            }

            $statement->execute();

            return array_map(
                static function (array $row): array {
                    return [
                        'permission_id' =>
                            (int) $row['permission_id'],
                        'permission_code' =>
                            (string) $row['permission_code'],
                        'permission_name' =>
                            (string) $row['permission_name'],
                        'module_name' =>
                            (string) $row['module_name'],
                        'description' =>
                            $row['description'] ?? null,
                    ];
                },
                $statement->fetchAll()
            );
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to load permissions by IDs.',
                $exception,
                [
                    'permission_ids' =>
                        $permissionIds,
                ]
            );

            throw new RuntimeException(
                'Unable to load permissions.'
            );
        }
    }

    /**
     * Replace all permissions assigned to a role.
     *
     * Must be called inside a transaction.
     *
     * @param array<int, int> $permissionIds
     */
    public function replacePermissions(
        int $roleId,
        array $permissionIds
    ): void {
        $permissionIds = array_values(
            array_unique(
                array_map('intval', $permissionIds)
            )
        );

        try {
            $deleteStatement = $this->pdo->prepare(
                'DELETE FROM role_permission
                 WHERE role_id = :role_id'
            );

            $deleteStatement->execute([
                'role_id' => $roleId,
            ]);

            if ($permissionIds === []) {
                return;
            }

            $insertStatement = $this->pdo->prepare(
                'INSERT INTO role_permission
                    (
                        role_id,
                        permission_id
                    )
                 VALUES
                    (
                        :role_id,
                        :permission_id
                    )'
            );

            foreach ($permissionIds as $permissionId) {
                if ($permissionId <= 0) {
                    continue;
                }

                $insertStatement->execute([
                    'role_id' => $roleId,
                    'permission_id' =>
                        $permissionId,
                ]);
            }
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to replace role permissions.',
                $exception,
                [
                    'role_id' => $roleId,
                    'permission_ids' =>
                        $permissionIds,
                ]
            );

            if ($this->isForeignKeyViolation($exception)) {
                throw new RuntimeException(
                    'One or more permission IDs are invalid.',
                    422
                );
            }

            throw new RuntimeException(
                'Unable to update role permissions.'
            );
        }
    }

    /**
     * Count users assigned to a role.
     */
    public function countAssignedUsers(
        int $roleId,
        bool $activeOnly = false
    ): int {
        $sql = 'SELECT COUNT(DISTINCT ur.user_id) AS total
                FROM user_role ur';

        if ($activeOnly) {
            $sql .= ' INNER JOIN user_master u
                        ON u.u_id = ur.user_id
                       AND u.active = 1';
        }

        $sql .= ' WHERE ur.role_id = :role_id';

        try {
            $statement = $this->pdo->prepare($sql);

            $statement->execute([
                'role_id' => $roleId,
            ]);

            return (int) (
                $statement->fetch()['total']
                ?? 0
            );
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to count users assigned to role.',
                $exception,
                [
                    'role_id' => $roleId,
                ]
            );

            throw new RuntimeException(
                'Unable to count assigned users.'
            );
        }
    }

    /**
     * Check whether a role is assigned to a user.
     */
    public function isAssignedToUser(
        int $roleId,
        int $userId
    ): bool {
        try {
            $statement = $this->pdo->prepare(
                'SELECT 1
                 FROM user_role
                 WHERE role_id = :role_id
                   AND user_id = :user_id
                 LIMIT 1'
            );

            $statement->execute([
                'role_id' => $roleId,
                'user_id' => $userId,
            ]);

            return (bool) $statement->fetchColumn();
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to verify user-role assignment.',
                $exception,
                [
                    'role_id' => $roleId,
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to verify role assignment.'
            );
        }
    }

    /**
     * Bind dynamic parameters.
     *
     * @param array<string, mixed> $parameters
     */
    private function bindParameters(
        PDOStatement $statement,
        array $parameters
    ): void {
        foreach ($parameters as $key => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };

            $statement->bindValue(
                ':' . $key,
                $value,
                $type
            );
        }
    }

    /**
     * Convert empty strings to NULL.
     */
    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
    }

    /**
     * Detect duplicate-key errors.
     */
    private function isDuplicateKey(
        PDOException $exception
    ): bool {
        return (
            (string) $exception->getCode()
            === '23000'
        ) && (
            !isset($exception->errorInfo[1])
            || (int) $exception->errorInfo[1]
                === 1062
        );
    }

    /**
     * Detect foreign-key violations.
     */
    private function isForeignKeyViolation(
        PDOException $exception
    ): bool {
        if ((string) $exception->getCode() !== '23000') {
            return false;
        }

        if (!isset($exception->errorInfo[1])) {
            return true;
        }

        return in_array(
            (int) $exception->errorInfo[1],
            [
                1451,
                1452,
            ],
            true
        );
    }

    /**
     * Log repository database errors.
     *
     * @param array<string, mixed> $context
     */
    private function logDatabaseError(
        string $message,
        Throwable $exception,
        array $context = []
    ): void {
        Logger::error(
            $message,
            array_merge(
                $context,
                [
                    'exception' =>
                        $exception->getMessage(),
                ]
            )
        );
    }
}
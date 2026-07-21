<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * User repository.
 *
 * Responsibilities:
 * - Read user records
 * - Create and update users
 * - Update password hashes
 * - Manage user roles
 * - Manage facility/department scope
 * - Update last-login and status fields
 *
 * Business rules must remain in service classes.
 */
final class UserRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Find a user for authentication.
     *
     * Lookup is case-insensitive by username.
     *
     * @return array<string, mixed>|null
     */
    public function findForAuthentication(
        string $username
    ): ?array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    u_id,
                    u_name,
                    u_pass,
                    u_fname,
                    u_mname,
                    u_lname,
                    u_email_id,
                    u_mob_no,
                    active,
                    last_login_at,
                    password_changed_at,
                    created_at,
                    updated_at
                 FROM user_master
                 WHERE LOWER(u_name) = :username
                 LIMIT 1'
            );

            $statement->execute([
                'username' => mb_strtolower(
                    trim($username)
                ),
            ]);

            $user = $statement->fetch();

            return is_array($user)
                ? $user
                : null;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to find user for authentication.',
                $exception,
                [
                    'username' => $username,
                ]
            );

            throw new RuntimeException(
                'Unable to load user account.'
            );
        }
    }

    /**
     * Find a user by ID.
     *
     * Includes password hash because this method is used
     * by password-change logic.
     *
     * @return array<string, mixed>|null
     */
    public function findById(
        int $userId
    ): ?array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    u_id,
                    u_name,
                    u_pass,
                    u_fname,
                    u_mname,
                    u_lname,
                    u_email_id,
                    u_mob_no,
                    active,
                    last_login_at,
                    password_changed_at,
                    created_at,
                    updated_at
                 FROM user_master
                 WHERE u_id = :user_id
                 LIMIT 1'
            );

            $statement->execute([
                'user_id' => $userId,
            ]);

            $user = $statement->fetch();

            return is_array($user)
                ? $user
                : null;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to find user by ID.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to load user account.'
            );
        }
    }

    /**
     * Find a user by username or email.
     *
     * Used by forgot-password flow.
     *
     * @return array<string, mixed>|null
     */
    public function findByUsernameOrEmail(
        string $identity
    ): ?array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    u_id,
                    u_name,
                    u_pass,
                    u_fname,
                    u_mname,
                    u_lname,
                    u_email_id,
                    u_mob_no,
                    active,
                    last_login_at,
                    password_changed_at,
                    created_at,
                    updated_at
                 FROM user_master
                 WHERE LOWER(u_name) = :identity
                    OR LOWER(u_email_id) = :identity
                 LIMIT 1'
            );

            $statement->execute([
                'identity' => mb_strtolower(
                    trim($identity)
                ),
            ]);

            $user = $statement->fetch();

            return is_array($user)
                ? $user
                : null;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to find user by username or email.',
                $exception,
                [
                    'identity_hash' =>
                        hash('sha256', $identity),
                ]
            );

            throw new RuntimeException(
                'Unable to load user account.'
            );
        }
    }

    /**
     * Return a paginated user list.
     *
     * Supported filters:
     * - search
     * - status
     * - role_id
     * - facility_id
     * - department_id
     *
     * @param array<string, mixed> $filters
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     pagination: array<string, int>
     * }
     */
    public function paginate(
        array $filters = [],
        int $page = 1,
        int $limit = 20
    ): array {
        $page = max(1, $page);
        $limit = max(1, min($limit, 100));
        $offset = ($page - 1) * $limit;

        $where = [];
        $parameters = [];

        if (
            isset($filters['search'])
            && trim((string) $filters['search']) !== ''
        ) {
            $where[] = '(
                u.u_name LIKE :search
                OR u.u_fname LIKE :search
                OR u.u_lname LIKE :search
                OR u.u_email_id LIKE :search
                OR u.u_mob_no LIKE :search
            )';

            $parameters['search'] =
                '%' . trim((string) $filters['search']) . '%';
        }

        if (
            array_key_exists('status', $filters)
            && $filters['status'] !== ''
            && $filters['status'] !== null
        ) {
            $where[] = 'u.active = :status';
            $parameters['status'] =
                (int) $filters['status'];
        }

        if (
            isset($filters['role_id'])
            && (int) $filters['role_id'] > 0
        ) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM user_role ur_filter
                WHERE ur_filter.user_id = u.u_id
                  AND ur_filter.role_id = :role_id
            )';

            $parameters['role_id'] =
                (int) $filters['role_id'];
        }

        if (
            isset($filters['facility_id'])
            && (int) $filters['facility_id'] > 0
        ) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM user_department_scope uds_filter
                WHERE uds_filter.user_id = u.u_id
                  AND uds_filter.facility_id = :facility_id
            )';

            $parameters['facility_id'] =
                (int) $filters['facility_id'];
        }

        if (
            isset($filters['department_id'])
            && (int) $filters['department_id'] > 0
        ) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM user_department_scope uds_department
                WHERE uds_department.user_id = u.u_id
                  AND uds_department.department_id = :department_id
            )';

            $parameters['department_id'] =
                (int) $filters['department_id'];
        }

        $whereSql = $where !== []
            ? 'WHERE ' . implode(' AND ', $where)
            : '';

        try {
            $countStatement = $this->pdo->prepare(
                "SELECT COUNT(DISTINCT u.u_id) AS total
                 FROM user_master u
                 {$whereSql}"
            );

            $this->bindParameters(
                $countStatement,
                $parameters
            );

            $countStatement->execute();

            $total = (int) (
                $countStatement->fetch()['total'] ?? 0
            );

            $listStatement = $this->pdo->prepare(
                "SELECT
                    u.u_id,
                    u.u_name,
                    u.u_fname,
                    u.u_mname,
                    u.u_lname,
                    u.u_email_id,
                    u.u_mob_no,
                    u.active,
                    u.last_login_at,
                    u.created_at,
                    u.updated_at,
                    GROUP_CONCAT(
                        DISTINCT r.role_code
                        ORDER BY r.role_code
                        SEPARATOR ','
                    ) AS role_codes
                 FROM user_master u
                 LEFT JOIN user_role ur
                    ON ur.user_id = u.u_id
                 LEFT JOIN role_master r
                    ON r.role_id = ur.role_id
                 {$whereSql}
                 GROUP BY
                    u.u_id,
                    u.u_name,
                    u.u_fname,
                    u.u_mname,
                    u.u_lname,
                    u.u_email_id,
                    u.u_mob_no,
                    u.active,
                    u.last_login_at,
                    u.created_at,
                    u.updated_at
                 ORDER BY u.u_id DESC
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
                    $row['u_id'] =
                        (int) $row['u_id'];

                    $row['active'] =
                        (int) $row['active'];

                    $row['roles'] =
                        $row['role_codes'] !== null
                        && $row['role_codes'] !== ''
                            ? explode(
                                ',',
                                (string) $row['role_codes']
                            )
                            : [];

                    unset($row['role_codes']);

                    return $row;
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
                        (int) ceil($total / $limit),
                ],
            ];
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to paginate users.',
                $exception,
                [
                    'filters' => $filters,
                    'page' => $page,
                    'limit' => $limit,
                ]
            );

            throw new RuntimeException(
                'Unable to load users.'
            );
        }
    }

    /**
     * Return complete user details without password hash.
     *
     * @return array<string, mixed>|null
     */
    public function findDetailsById(
        int $userId
    ): ?array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    u_id,
                    u_name,
                    u_fname,
                    u_mname,
                    u_lname,
                    u_email_id,
                    u_mob_no,
                    active,
                    last_login_at,
                    password_changed_at,
                    created_at,
                    updated_at
                 FROM user_master
                 WHERE u_id = :user_id
                 LIMIT 1'
            );

            $statement->execute([
                'user_id' => $userId,
            ]);

            $user = $statement->fetch();

            if (!is_array($user)) {
                return null;
            }

            $user['u_id'] =
                (int) $user['u_id'];

            $user['active'] =
                (int) $user['active'];

            return $user;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to load user details.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to load user details.'
            );
        }
    }

    /**
     * Create a user.
     *
     * Expected data keys:
     * - username
     * - password_hash
     * - first_name
     * - middle_name
     * - last_name
     * - email
     * - mobile
     * - active
     *
     * @param array<string, mixed> $data
     */
    public function create(
        array $data
    ): int {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO user_master
                    (
                        u_name,
                        u_pass,
                        u_fname,
                        u_mname,
                        u_lname,
                        u_email_id,
                        u_mob_no,
                        active,
                        last_login_at,
                        password_changed_at,
                        created_at,
                        updated_at
                    )
                 VALUES
                    (
                        :username,
                        :password_hash,
                        :first_name,
                        :middle_name,
                        :last_name,
                        :email,
                        :mobile,
                        :active,
                        NULL,
                        NOW(),
                        NOW(),
                        NOW()
                    )'
            );

            $statement->execute([
                'username' =>
                    trim((string) $data['username']),
                'password_hash' =>
                    (string) $data['password_hash'],
                'first_name' =>
                    $this->nullableString(
                        $data['first_name'] ?? null
                    ),
                'middle_name' =>
                    $this->nullableString(
                        $data['middle_name'] ?? null
                    ),
                'last_name' =>
                    $this->nullableString(
                        $data['last_name'] ?? null
                    ),
                'email' =>
                    $this->nullableString(
                        $data['email'] ?? null
                    ),
                'mobile' =>
                    $this->nullableString(
                        $data['mobile'] ?? null
                    ),
                'active' =>
                    (int) ($data['active'] ?? 1),
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to create user.',
                $exception,
                [
                    'username' =>
                        $data['username'] ?? null,
                    'email' =>
                        $data['email'] ?? null,
                ]
            );

            if ($this->isDuplicateKey($exception)) {
                throw new RuntimeException(
                    'Username or email already exists.',
                    409
                );
            }

            throw new RuntimeException(
                'Unable to create user.'
            );
        }
    }

    /**
     * Update editable user profile fields.
     *
     * @param array<string, mixed> $data
     */
    public function update(
        int $userId,
        array $data
    ): bool {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE user_master
                 SET
                    u_fname = :first_name,
                    u_mname = :middle_name,
                    u_lname = :last_name,
                    u_email_id = :email,
                    u_mob_no = :mobile,
                    active = :active,
                    updated_at = NOW()
                 WHERE u_id = :user_id'
            );

            $statement->execute([
                'first_name' =>
                    $this->nullableString(
                        $data['first_name'] ?? null
                    ),
                'middle_name' =>
                    $this->nullableString(
                        $data['middle_name'] ?? null
                    ),
                'last_name' =>
                    $this->nullableString(
                        $data['last_name'] ?? null
                    ),
                'email' =>
                    $this->nullableString(
                        $data['email'] ?? null
                    ),
                'mobile' =>
                    $this->nullableString(
                        $data['mobile'] ?? null
                    ),
                'active' =>
                    (int) ($data['active'] ?? 1),
                'user_id' => $userId,
            ]);

            return $statement->rowCount() > 0;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to update user.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            if ($this->isDuplicateKey($exception)) {
                throw new RuntimeException(
                    'Email already exists.',
                    409
                );
            }

            throw new RuntimeException(
                'Unable to update user.'
            );
        }
    }

    /**
     * Update only the active status.
     */
    public function updateStatus(
        int $userId,
        int $active
    ): bool {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE user_master
                 SET
                    active = :active,
                    updated_at = NOW()
                 WHERE u_id = :user_id'
            );

            $statement->execute([
                'active' => $active,
                'user_id' => $userId,
            ]);

            return $statement->rowCount() > 0;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to update user status.',
                $exception,
                [
                    'user_id' => $userId,
                    'active' => $active,
                ]
            );

            throw new RuntimeException(
                'Unable to update user status.'
            );
        }
    }

    /**
     * Update last-login timestamp.
     */
    public function updateLastLogin(
        int $userId
    ): void {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE user_master
                 SET
                    last_login_at = NOW(),
                    updated_at = NOW()
                 WHERE u_id = :user_id'
            );

            $statement->execute([
                'user_id' => $userId,
            ]);
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to update last-login timestamp.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to update last-login timestamp.'
            );
        }
    }

    /**
     * Update password and password-changed timestamp.
     */
    public function updatePassword(
        int $userId,
        string $passwordHash
    ): void {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE user_master
                 SET
                    u_pass = :password_hash,
                    password_changed_at = NOW(),
                    updated_at = NOW()
                 WHERE u_id = :user_id'
            );

            $statement->execute([
                'password_hash' => $passwordHash,
                'user_id' => $userId,
            ]);

            if ($statement->rowCount() !== 1) {
                throw new RuntimeException(
                    'User password was not updated.'
                );
            }
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to update user password.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to update user password.'
            );
        }
    }

    /**
     * Update password hash without changing the
     * password-changed timestamp.
     *
     * Used only when rehashing the same valid password.
     */
    public function updatePasswordHashOnly(
        int $userId,
        string $passwordHash
    ): void {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE user_master
                 SET
                    u_pass = :password_hash,
                    updated_at = NOW()
                 WHERE u_id = :user_id'
            );

            $statement->execute([
                'password_hash' => $passwordHash,
                'user_id' => $userId,
            ]);
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to rehash user password.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to update password hash.'
            );
        }
    }

    /**
     * Check whether a username already exists.
     */
    public function usernameExists(
        string $username,
        ?int $excludeUserId = null
    ): bool {
        $sql = 'SELECT 1
                FROM user_master
                WHERE LOWER(u_name) = :username';

        $parameters = [
            'username' =>
                mb_strtolower(trim($username)),
        ];

        if ($excludeUserId !== null) {
            $sql .= ' AND u_id <> :exclude_user_id';
            $parameters['exclude_user_id'] =
                $excludeUserId;
        }

        $sql .= ' LIMIT 1';

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);

            return (bool) $statement->fetchColumn();
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to verify username uniqueness.',
                $exception
            );

            throw new RuntimeException(
                'Unable to verify username.'
            );
        }
    }

    /**
     * Check whether an email already exists.
     */
    public function emailExists(
        string $email,
        ?int $excludeUserId = null
    ): bool {
        $sql = 'SELECT 1
                FROM user_master
                WHERE LOWER(u_email_id) = :email';

        $parameters = [
            'email' =>
                mb_strtolower(trim($email)),
        ];

        if ($excludeUserId !== null) {
            $sql .= ' AND u_id <> :exclude_user_id';
            $parameters['exclude_user_id'] =
                $excludeUserId;
        }

        $sql .= ' LIMIT 1';

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);

            return (bool) $statement->fetchColumn();
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to verify email uniqueness.',
                $exception
            );

            throw new RuntimeException(
                'Unable to verify email.'
            );
        }
    }

    /**
     * Return role IDs assigned to a user.
     *
     * @return array<int, int>
     */
    public function getRoleIds(
        int $userId
    ): array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT role_id
                 FROM user_role
                 WHERE user_id = :user_id
                 ORDER BY role_id'
            );

            $statement->execute([
                'user_id' => $userId,
            ]);

            return array_values(array_map(
                static fn (array $row): int =>
                    (int) $row['role_id'],
                $statement->fetchAll()
            ));
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to load user roles.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to load user roles.'
            );
        }
    }

    /**
     * Replace all roles assigned to a user.
     *
     * Must be called inside a transaction.
     *
     * @param array<int, int> $roleIds
     */
    public function replaceRoles(
        int $userId,
        array $roleIds
    ): void {
        try {
            $delete = $this->pdo->prepare(
                'DELETE FROM user_role
                 WHERE user_id = :user_id'
            );

            $delete->execute([
                'user_id' => $userId,
            ]);

            if ($roleIds === []) {
                return;
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO user_role
                    (
                        user_id,
                        role_id
                    )
                 VALUES
                    (
                        :user_id,
                        :role_id
                    )'
            );

            foreach (
                array_values(array_unique($roleIds))
                as $roleId
            ) {
                $insert->execute([
                    'user_id' => $userId,
                    'role_id' => (int) $roleId,
                ]);
            }
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to replace user roles.',
                $exception,
                [
                    'user_id' => $userId,
                    'role_ids' => $roleIds,
                ]
            );

            throw new RuntimeException(
                'Unable to update user roles.'
            );
        }
    }

    /**
     * Return user scope rows.
     *
     * @return array<int, array{
     *     scope_id: int,
     *     facility_id: int|null,
     *     department_id: int|null
     * }>
     */
    public function getScopes(
        int $userId
    ): array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    scope_id,
                    facility_id,
                    department_id
                 FROM user_department_scope
                 WHERE user_id = :user_id
                 ORDER BY
                    facility_id,
                    department_id'
            );

            $statement->execute([
                'user_id' => $userId,
            ]);

            return array_map(
                static function (array $row): array {
                    return [
                        'scope_id' =>
                            (int) $row['scope_id'],
                        'facility_id' =>
                            $row['facility_id'] !== null
                                ? (int) $row['facility_id']
                                : null,
                        'department_id' =>
                            $row['department_id'] !== null
                                ? (int) $row['department_id']
                                : null,
                    ];
                },
                $statement->fetchAll()
            );
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to load user scopes.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to load user scopes.'
            );
        }
    }

    /**
     * Replace all facility/department scope rows.
     *
     * Must be called inside a transaction.
     *
     * Expected scope item:
     *
     * [
     *   'facility_id' => 1,
     *   'department_id' => 4
     * ]
     *
     * @param array<int, array<string, int|null>> $scopes
     */
    public function replaceScopes(
        int $userId,
        array $scopes
    ): void {
        try {
            $delete = $this->pdo->prepare(
                'DELETE FROM user_department_scope
                 WHERE user_id = :user_id'
            );

            $delete->execute([
                'user_id' => $userId,
            ]);

            if ($scopes === []) {
                return;
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO user_department_scope
                    (
                        user_id,
                        facility_id,
                        department_id,
                        created_at
                    )
                 VALUES
                    (
                        :user_id,
                        :facility_id,
                        :department_id,
                        NOW()
                    )'
            );

            foreach ($scopes as $scope) {
                $facilityId =
                    isset($scope['facility_id'])
                    && $scope['facility_id'] !== null
                        ? (int) $scope['facility_id']
                        : null;

                $departmentId =
                    isset($scope['department_id'])
                    && $scope['department_id'] !== null
                        ? (int) $scope['department_id']
                        : null;

                if (
                    $facilityId === null
                    && $departmentId === null
                ) {
                    continue;
                }

                $insert->bindValue(
                    ':user_id',
                    $userId,
                    PDO::PARAM_INT
                );

                $insert->bindValue(
                    ':facility_id',
                    $facilityId,
                    $facilityId === null
                        ? PDO::PARAM_NULL
                        : PDO::PARAM_INT
                );

                $insert->bindValue(
                    ':department_id',
                    $departmentId,
                    $departmentId === null
                        ? PDO::PARAM_NULL
                        : PDO::PARAM_INT
                );

                $insert->execute();
            }
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to replace user scopes.',
                $exception,
                [
                    'user_id' => $userId,
                    'scopes' => $scopes,
                ]
            );

            if ($this->isDuplicateKey($exception)) {
                throw new RuntimeException(
                    'Duplicate user scope assignment.',
                    409
                );
            }

            throw new RuntimeException(
                'Unable to update user scopes.'
            );
        }
    }

    /**
     * Count active users assigned to a role.
     */
    public function countUsersByRole(
        int $roleId
    ): int {
        try {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(DISTINCT ur.user_id) AS total
                 FROM user_role ur
                 INNER JOIN user_master u
                    ON u.u_id = ur.user_id
                 WHERE ur.role_id = :role_id
                   AND u.active = 1'
            );

            $statement->execute([
                'role_id' => $roleId,
            ]);

            return (int) (
                $statement->fetch()['total'] ?? 0
            );
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to count users by role.',
                $exception,
                [
                    'role_id' => $roleId,
                ]
            );

            throw new RuntimeException(
                'Unable to count users.'
            );
        }
    }

    /**
     * Count active users mapped to a facility.
     */
    public function countUsersByFacility(
        int $facilityId
    ): int {
        try {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(DISTINCT uds.user_id) AS total
                 FROM user_department_scope uds
                 INNER JOIN user_master u
                    ON u.u_id = uds.user_id
                 WHERE uds.facility_id = :facility_id
                   AND u.active = 1'
            );

            $statement->execute([
                'facility_id' => $facilityId,
            ]);

            return (int) (
                $statement->fetch()['total'] ?? 0
            );
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to count users by facility.',
                $exception,
                [
                    'facility_id' => $facilityId,
                ]
            );

            throw new RuntimeException(
                'Unable to count users.'
            );
        }
    }

    /**
     * Delete a user physically.
     *
     * This should normally not be used.
     * Prefer updateStatus(..., 0).
     */
    public function delete(
        int $userId
    ): bool {
        try {
            $statement = $this->pdo->prepare(
                'DELETE FROM user_master
                 WHERE u_id = :user_id'
            );

            $statement->execute([
                'user_id' => $userId,
            ]);

            return $statement->rowCount() > 0;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to delete user.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to delete user.'
            );
        }
    }

    /**
     * Bind dynamic query parameters using their types.
     *
     * @param array<string, mixed> $parameters
     */
    private function bindParameters(
        \PDOStatement $statement,
        array $parameters
    ): void {
        foreach ($parameters as $key => $value) {
            $statement->bindValue(
                ':' . $key,
                $value,
                is_int($value)
                    ? PDO::PARAM_INT
                    : PDO::PARAM_STR
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
     * Check duplicate-key errors.
     */
    private function isDuplicateKey(
        PDOException $exception
    ): bool {
        return (string) $exception->getCode() === '23000'
            || (
                isset($exception->errorInfo[1])
                && (int) $exception->errorInfo[1] === 1062
            );
    }

    /**
     * Write structured repository errors.
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
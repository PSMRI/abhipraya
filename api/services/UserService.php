<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Authentication;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Validator;
use App\Repositories\AuthRepository;
use App\Repositories\UserRepository;
use PDO;
use RuntimeException;
use Throwable;

/**
 * User-management business service.
 *
 * Responsibilities:
 * - List and retrieve users
 * - Create and update user accounts
 * - Activate and deactivate users
 * - Assign roles and access scope
 * - Reset passwords
 * - Return the current user's profile
 * - Enforce user-management business rules
 * - Write audit records
 *
 * HTTP responses must remain inside endpoint files.
 */
final class UserService
{
    private UserRepository $userRepository;

    private AuthRepository $authRepository;

    public function __construct(
        ?UserRepository $userRepository = null,
        ?AuthRepository $authRepository = null
    ) {
        $this->userRepository = $userRepository
            ?? new UserRepository(Database::connection());

        $this->authRepository = $authRepository
            ?? new AuthRepository(Database::connection());
    }

    /**
     * Return a filtered and paginated user list.
     *
     * Supported query filters:
     * - search
     * - status
     * - role_id
     * - facility_id
     * - department_id
     * - page
     * - limit
     *
     * @return array<string, mixed>
     */
    public function listUsers(
        array $query
    ): array {
        $page = isset($query['page'])
            ? max(1, (int) $query['page'])
            : 1;

        $limit = isset($query['limit'])
            ? max(1, min(100, (int) $query['limit']))
            : 20;

        $filters = [
            'search' => trim(
                (string) ($query['search'] ?? '')
            ),
            'status' => $query['status'] ?? null,
            'role_id' => isset($query['role_id'])
                ? (int) $query['role_id']
                : null,
            'facility_id' =>
                isset($query['facility_id'])
                    ? (int) $query['facility_id']
                    : null,
            'department_id' =>
                isset($query['department_id'])
                    ? (int) $query['department_id']
                    : null,
        ];

        return $this->userRepository->paginate(
            $filters,
            $page,
            $limit
        );
    }

    /**
     * Return a complete user record.
     *
     * @return array<string, mixed>
     */
    public function getUser(
        int $userId
    ): array {
        $this->assertPositiveId($userId, 'user_id');

        $user = $this->userRepository
            ->findDetailsById($userId);

        if ($user === null) {
            throw new UserNotFoundException();
        }

        $roleIds = $this->userRepository
            ->getRoleIds($userId);

        $scopes = $this->userRepository
            ->getScopes($userId);

        return [
            'user' => $this->formatUser($user),
            'role_ids' => $roleIds,
            'roles' => $this->authRepository
                ->getUserRoles($userId),
            'permissions' => $this->authRepository
                ->getUserPermissions($userId),
            'scopes' => $scopes,
        ];
    }

    /**
     * Create a new user.
     *
     * Expected payload:
     *
     * {
     *   "username": "facility.admin",
     *   "password": "Secure@123",
     *   "first_name": "Facility",
     *   "middle_name": "",
     *   "last_name": "Admin",
     *   "email": "admin@example.org",
     *   "mobile": "9876543210",
     *   "active": 1,
     *   "role_ids": [3],
     *   "scopes": [
     *      {
     *        "facility_id": 10,
     *        "department_id": null
     *      }
     *   ]
     * }
     *
     * @return array<string, mixed>
     */
    public function createUser(
        array $payload,
        Request $request
    ): array {
        $this->validateCreatePayload($payload);

        $username = mb_strtolower(
            trim((string) $payload['username'])
        );

        $email = $this->nullableString(
            $payload['email'] ?? null
        );

        if (
            $this->userRepository
                ->usernameExists($username)
        ) {
            throw new UserValidationException(
                'Validation failed.',
                [
                    'username' => [
                        'Username already exists.',
                    ],
                ]
            );
        }

        if (
            $email !== null
            && $this->userRepository
                ->emailExists($email)
        ) {
            throw new UserValidationException(
                'Validation failed.',
                [
                    'email' => [
                        'Email address already exists.',
                    ],
                ]
            );
        }

        $this->validatePasswordPolicy(
            (string) $payload['password']
        );

        $roleIds = $this->normalizeIntegerList(
            $payload['role_ids'] ?? []
        );

        $scopes = $this->normalizeScopes(
            $payload['scopes'] ?? []
        );

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        try {
            $userId = Database::transaction(
                function (PDO $pdo) use (
                    $payload,
                    $username,
                    $email,
                    $roleIds,
                    $scopes,
                    $actorUserId,
                    $request
                ): int {
                    $userId = $this->userRepository
                        ->create([
                            'username' => $username,
                            'password_hash' =>
                                password_hash(
                                    (string) $payload['password'],
                                    PASSWORD_DEFAULT
                                ),
                            'first_name' =>
                                $this->nullableString(
                                    $payload['first_name']
                                        ?? null
                                ),
                            'middle_name' =>
                                $this->nullableString(
                                    $payload['middle_name']
                                        ?? null
                                ),
                            'last_name' =>
                                $this->nullableString(
                                    $payload['last_name']
                                        ?? null
                                ),
                            'email' => $email,
                            'mobile' =>
                                $this->nullableString(
                                    $payload['mobile']
                                        ?? null
                                ),
                            'active' =>
                                (int) (
                                    $payload['active']
                                    ?? 1
                                ),
                        ]);

                    $this->userRepository
                        ->replaceRoles(
                            $userId,
                            $roleIds
                        );

                    $this->userRepository
                        ->replaceScopes(
                            $userId,
                            $scopes
                        );

                    $this->authRepository
                        ->writeAudit(
                            $actorUserId,
                            'USER_CREATED',
                            'USER',
                            (string) $userId,
                            null,
                            [
                                'username' => $username,
                                'email' => $email,
                                'active' =>
                                    (int) (
                                        $payload['active']
                                        ?? 1
                                    ),
                                'role_ids' => $roleIds,
                                'scopes' => $scopes,
                            ],
                            $request->ip(),
                            $request->requestId()
                        );

                    return $userId;
                }
            );

            Logger::info(
                'User created successfully',
                [
                    'user_id' => $userId,
                    'created_by' => $actorUserId,
                    'ip_address' => $request->ip(),
                ]
            );

            return $this->getUser($userId);
        } catch (
            UserValidationException
            | RuntimeException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            Logger::error(
                'User creation failed',
                [
                    'username' => $username,
                    'created_by' => $actorUserId,
                    'exception' =>
                        $exception->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Unable to create user.',
                500
            );
        }
    }

    /**
     * Update user profile information.
     *
     * Username and password are not updated here.
     *
     * @return array<string, mixed>
     */
    public function updateUser(
        int $userId,
        array $payload,
        Request $request
    ): array {
        $this->assertPositiveId($userId, 'user_id');

        $existing = $this->userRepository
            ->findDetailsById($userId);

        if ($existing === null) {
            throw new UserNotFoundException();
        }

        $this->validateUpdatePayload($payload);

        $email = $this->nullableString(
            $payload['email']
                ?? $existing['u_email_id']
        );

        if (
            $email !== null
            && $this->userRepository->emailExists(
                $email,
                $userId
            )
        ) {
            throw new UserValidationException(
                'Validation failed.',
                [
                    'email' => [
                        'Email address already exists.',
                    ],
                ]
            );
        }

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        $newData = [
            'first_name' =>
                $payload['first_name']
                    ?? $existing['u_fname'],
            'middle_name' =>
                $payload['middle_name']
                    ?? $existing['u_mname'],
            'last_name' =>
                $payload['last_name']
                    ?? $existing['u_lname'],
            'email' => $email,
            'mobile' =>
                $payload['mobile']
                    ?? $existing['u_mob_no'],
            'active' =>
                array_key_exists('active', $payload)
                    ? (int) $payload['active']
                    : (int) $existing['active'],
        ];

        if (
            $userId === $actorUserId
            && $newData['active'] === 0
        ) {
            throw new UserValidationException(
                'Validation failed.',
                [
                    'active' => [
                        'You cannot deactivate your own account.',
                    ],
                ]
            );
        }

        Database::transaction(
            function (PDO $pdo) use (
                $userId,
                $existing,
                $newData,
                $actorUserId,
                $request
            ): void {
                $this->userRepository->update(
                    $userId,
                    $newData
                );

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'USER_UPDATED',
                    'USER',
                    (string) $userId,
                    $this->formatUser($existing),
                    $newData,
                    $request->ip(),
                    $request->requestId()
                );

                if (
                    (int) $existing['active'] === 1
                    && (int) $newData['active'] === 0
                ) {
                    $this->authRepository
                        ->revokeUserSessions($userId);
                }
            }
        );

        Logger::info(
            'User updated successfully',
            [
                'user_id' => $userId,
                'updated_by' => $actorUserId,
            ]
        );

        return $this->getUser($userId);
    }

    /**
     * Physically delete a user.
     *
     * Prefer deactivation in normal operation.
     */
    public function deleteUser(
        int $userId,
        Request $request
    ): array {
        $this->assertPositiveId($userId, 'user_id');

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        if ($userId === $actorUserId) {
            throw new UserValidationException(
                'Validation failed.',
                [
                    'user_id' => [
                        'You cannot delete your own account.',
                    ],
                ]
            );
        }

        $existing = $this->userRepository
            ->findDetailsById($userId);

        if ($existing === null) {
            throw new UserNotFoundException();
        }

        Database::transaction(
            function (PDO $pdo) use (
                $userId,
                $existing,
                $actorUserId,
                $request
            ): void {
                $this->authRepository
                    ->revokeUserSessions($userId);

                $this->userRepository->delete($userId);

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'USER_DELETED',
                    'USER',
                    (string) $userId,
                    $this->formatUser($existing),
                    null,
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        return [
            'user_id' => $userId,
            'deleted' => true,
        ];
    }

    /**
     * Activate a user.
     *
     * @return array<string, mixed>
     */
    public function activateUser(
        int $userId,
        Request $request
    ): array {
        return $this->changeStatus(
            $userId,
            1,
            $request
        );
    }

    /**
     * Deactivate a user and revoke active sessions.
     *
     * @return array<string, mixed>
     */
    public function deactivateUser(
        int $userId,
        Request $request
    ): array {
        return $this->changeStatus(
            $userId,
            0,
            $request
        );
    }

    /**
     * Reset another user's password.
     *
     * If new_password is not provided, a temporary
     * password is generated and returned once.
     *
     * @return array<string, mixed>
     */
    public function resetPassword(
        int $userId,
        array $payload,
        Request $request
    ): array {
        $this->assertPositiveId($userId, 'user_id');

        $user = $this->userRepository
            ->findDetailsById($userId);

        if ($user === null) {
            throw new UserNotFoundException();
        }

        $generatedPassword = false;

        $newPassword = isset($payload['new_password'])
            && trim((string) $payload['new_password']) !== ''
                ? (string) $payload['new_password']
                : $this->generateTemporaryPassword();

        if (
            isset($payload['confirm_password'])
            && $payload['confirm_password']
                !== $newPassword
        ) {
            throw new UserValidationException(
                'Validation failed.',
                [
                    'confirm_password' => [
                        'Password confirmation does not match.',
                    ],
                ]
            );
        }

        if (!isset($payload['new_password'])) {
            $generatedPassword = true;
        }

        $this->validatePasswordPolicy($newPassword);

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $userId,
                $newPassword,
                $actorUserId,
                $request
            ): void {
                $this->userRepository
                    ->updatePassword(
                        $userId,
                        password_hash(
                            $newPassword,
                            PASSWORD_DEFAULT
                        )
                    );

                $this->authRepository
                    ->revokeUserSessions($userId);

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'USER_PASSWORD_RESET',
                    'USER',
                    (string) $userId,
                    null,
                    [
                        'sessions_revoked' => true,
                    ],
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        $result = [
            'user_id' => $userId,
            'password_reset' => true,
            'sessions_revoked' => true,
        ];

        if ($generatedPassword) {
            $result['temporary_password'] =
                $newPassword;
        }

        return $result;
    }

    /**
     * Replace all roles assigned to a user.
     *
     * @return array<string, mixed>
     */
    public function assignRoles(
        int $userId,
        array $payload,
        Request $request
    ): array {
        $this->assertPositiveId($userId, 'user_id');

        if (
            !isset($payload['role_ids'])
            || !is_array($payload['role_ids'])
        ) {
            throw new UserValidationException(
                'Validation failed.',
                [
                    'role_ids' => [
                        'role_ids must be an array.',
                    ],
                ]
            );
        }

        $user = $this->userRepository
            ->findDetailsById($userId);

        if ($user === null) {
            throw new UserNotFoundException();
        }

        $roleIds = $this->normalizeIntegerList(
            $payload['role_ids']
        );

        $oldRoleIds = $this->userRepository
            ->getRoleIds($userId);

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $userId,
                $oldRoleIds,
                $roleIds,
                $actorUserId,
                $request
            ): void {
                $this->userRepository
                    ->replaceRoles(
                        $userId,
                        $roleIds
                    );

                $this->authRepository
                    ->revokeUserSessions($userId);

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'USER_ROLE_ASSIGNED',
                    'USER',
                    (string) $userId,
                    [
                        'role_ids' => $oldRoleIds,
                    ],
                    [
                        'role_ids' => $roleIds,
                    ],
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        return $this->getUser($userId);
    }

    /**
     * Replace facility and department scope.
     *
     * @return array<string, mixed>
     */
    public function assignScope(
        int $userId,
        array $payload,
        Request $request
    ): array {
        $this->assertPositiveId($userId, 'user_id');

        if (
            !isset($payload['scopes'])
            || !is_array($payload['scopes'])
        ) {
            throw new UserValidationException(
                'Validation failed.',
                [
                    'scopes' => [
                        'scopes must be an array.',
                    ],
                ]
            );
        }

        $user = $this->userRepository
            ->findDetailsById($userId);

        if ($user === null) {
            throw new UserNotFoundException();
        }

        $scopes = $this->normalizeScopes(
            $payload['scopes']
        );

        $oldScopes = $this->userRepository
            ->getScopes($userId);

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $userId,
                $oldScopes,
                $scopes,
                $actorUserId,
                $request
            ): void {
                $this->userRepository
                    ->replaceScopes(
                        $userId,
                        $scopes
                    );

                $this->authRepository
                    ->revokeUserSessions($userId);

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'USER_SCOPE_ASSIGNED',
                    'USER',
                    (string) $userId,
                    [
                        'scopes' => $oldScopes,
                    ],
                    [
                        'scopes' => $scopes,
                    ],
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        return $this->getUser($userId);
    }

    /**
     * Return current authenticated user's profile.
     *
     * @return array<string, mixed>
     */
    public function getProfile(): array
    {
        $auth = Authentication::requireUser();

        return $this->getUser(
            (int) $auth['user_id']
        );
    }

    /**
     * Update current authenticated user's profile.
     *
     * Users cannot modify their own roles, scope,
     * username or active status through this method.
     *
     * @return array<string, mixed>
     */
    public function updateProfile(
        array $payload,
        Request $request
    ): array {
        $auth = Authentication::requireUser();
        $userId = (int) $auth['user_id'];

        unset(
            $payload['active'],
            $payload['role_ids'],
            $payload['scopes'],
            $payload['username']
        );

        return $this->updateUser(
            $userId,
            $payload,
            $request
        );
    }

    /**
     * Update active status.
     *
     * @return array<string, mixed>
     */
    private function changeStatus(
        int $userId,
        int $active,
        Request $request
    ): array {
        $this->assertPositiveId($userId, 'user_id');

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        if (
            $active === 0
            && $userId === $actorUserId
        ) {
            throw new UserValidationException(
                'Validation failed.',
                [
                    'user_id' => [
                        'You cannot deactivate your own account.',
                    ],
                ]
            );
        }

        $existing = $this->userRepository
            ->findDetailsById($userId);

        if ($existing === null) {
            throw new UserNotFoundException();
        }

        Database::transaction(
            function (PDO $pdo) use (
                $userId,
                $active,
                $existing,
                $actorUserId,
                $request
            ): void {
                $this->userRepository
                    ->updateStatus(
                        $userId,
                        $active
                    );

                if ($active === 0) {
                    $this->authRepository
                        ->revokeUserSessions($userId);
                }

                $this->authRepository->writeAudit(
                    $actorUserId,
                    $active === 1
                        ? 'USER_ACTIVATED'
                        : 'USER_DEACTIVATED',
                    'USER',
                    (string) $userId,
                    [
                        'active' =>
                            (int) $existing['active'],
                    ],
                    [
                        'active' => $active,
                    ],
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        return $this->getUser($userId);
    }

    private function validateCreatePayload(
        array $payload
    ): void {
        $validator = new Validator();

        $validator
            ->required($payload, 'username')
            ->string($payload, 'username', 3, 100)
            ->required($payload, 'password')
            ->string($payload, 'password', 8, 255)
            ->string($payload, 'first_name', 1, 100)
            ->string($payload, 'middle_name', 0, 100)
            ->string($payload, 'last_name', 0, 100)
            ->email($payload, 'email')
            ->string($payload, 'mobile', 0, 15);

        if (isset($payload['active'])) {
            $validator->in(
                $payload,
                'active',
                [0, 1, '0', '1']
            );
        }

        if (
            isset($payload['mobile'])
            && trim((string) $payload['mobile']) !== ''
        ) {
            $validator->regex(
                $payload,
                'mobile',
                '/^[0-9+\-\s]{7,15}$/',
                'Mobile number format is invalid.'
            );
        }

        if ($validator->fails()) {
            throw new UserValidationException(
                'Validation failed.',
                $validator->errors()
            );
        }
    }

    private function validateUpdatePayload(
        array $payload
    ): void {
        $validator = new Validator();

        $validator
            ->string($payload, 'first_name', 1, 100)
            ->string($payload, 'middle_name', 0, 100)
            ->string($payload, 'last_name', 0, 100)
            ->email($payload, 'email')
            ->string($payload, 'mobile', 0, 15);

        if (isset($payload['active'])) {
            $validator->in(
                $payload,
                'active',
                [0, 1, '0', '1']
            );
        }

        if (
            isset($payload['mobile'])
            && trim((string) $payload['mobile']) !== ''
        ) {
            $validator->regex(
                $payload,
                'mobile',
                '/^[0-9+\-\s]{7,15}$/',
                'Mobile number format is invalid.'
            );
        }

        if ($validator->fails()) {
            throw new UserValidationException(
                'Validation failed.',
                $validator->errors()
            );
        }
    }

    private function validatePasswordPolicy(
        string $password
    ): void {
        $minimumLength = Config::int(
            'PASSWORD_MIN_LENGTH',
            8
        );

        $errors = [];

        if (mb_strlen($password) < $minimumLength) {
            $errors[] =
                "Password must contain at least {$minimumLength} characters.";
        }

        if (
            Config::bool(
                'PASSWORD_REQUIRE_UPPERCASE',
                true
            )
            && !preg_match('/[A-Z]/', $password)
        ) {
            $errors[] =
                'Password must contain an uppercase letter.';
        }

        if (
            Config::bool(
                'PASSWORD_REQUIRE_LOWERCASE',
                true
            )
            && !preg_match('/[a-z]/', $password)
        ) {
            $errors[] =
                'Password must contain a lowercase letter.';
        }

        if (
            Config::bool(
                'PASSWORD_REQUIRE_NUMBER',
                true
            )
            && !preg_match('/[0-9]/', $password)
        ) {
            $errors[] =
                'Password must contain a number.';
        }

        if (
            Config::bool(
                'PASSWORD_REQUIRE_SPECIAL',
                true
            )
            && !preg_match(
                '/[^A-Za-z0-9]/',
                $password
            )
        ) {
            $errors[] =
                'Password must contain a special character.';
        }

        if ($errors !== []) {
            throw new UserValidationException(
                'Password policy validation failed.',
                [
                    'password' => $errors,
                ]
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIntegerList(
        mixed $values
    ): array {
        if (!is_array($values)) {
            return [];
        }

        $result = [];

        foreach ($values as $value) {
            if (
                filter_var(
                    $value,
                    FILTER_VALIDATE_INT
                ) === false
                || (int) $value <= 0
            ) {
                throw new UserValidationException(
                    'Validation failed.',
                    [
                        'role_ids' => [
                            'Every role ID must be a positive integer.',
                        ],
                    ]
                );
            }

            $result[] = (int) $value;
        }

        return array_values(
            array_unique($result)
        );
    }

    /**
     * @return array<int, array{
     *     facility_id: int|null,
     *     department_id: int|null
     * }>
     */
    private function normalizeScopes(
        mixed $scopes
    ): array {
        if (!is_array($scopes)) {
            return [];
        }

        $result = [];
        $unique = [];

        foreach ($scopes as $index => $scope) {
            if (!is_array($scope)) {
                throw new UserValidationException(
                    'Validation failed.',
                    [
                        "scopes.{$index}" => [
                            'Each scope must be an object.',
                        ],
                    ]
                );
            }

            $facilityId = $scope['facility_id']
                ?? null;

            $departmentId = $scope['department_id']
                ?? null;

            if (
                $facilityId !== null
                && (
                    filter_var(
                        $facilityId,
                        FILTER_VALIDATE_INT
                    ) === false
                    || (int) $facilityId <= 0
                )
            ) {
                throw new UserValidationException(
                    'Validation failed.',
                    [
                        "scopes.{$index}.facility_id" => [
                            'Facility ID must be a positive integer.',
                        ],
                    ]
                );
            }

            if (
                $departmentId !== null
                && (
                    filter_var(
                        $departmentId,
                        FILTER_VALIDATE_INT
                    ) === false
                    || (int) $departmentId <= 0
                )
            ) {
                throw new UserValidationException(
                    'Validation failed.',
                    [
                        "scopes.{$index}.department_id" => [
                            'Department ID must be a positive integer.',
                        ],
                    ]
                );
            }

            if (
                $facilityId === null
                && $departmentId === null
            ) {
                throw new UserValidationException(
                    'Validation failed.',
                    [
                        "scopes.{$index}" => [
                            'Facility or department ID is required.',
                        ],
                    ]
                );
            }

            $normalized = [
                'facility_id' =>
                    $facilityId !== null
                        ? (int) $facilityId
                        : null,
                'department_id' =>
                    $departmentId !== null
                        ? (int) $departmentId
                        : null,
            ];

            $key = ($normalized['facility_id'] ?? 0)
                . ':'
                . ($normalized['department_id'] ?? 0);

            if (!isset($unique[$key])) {
                $result[] = $normalized;
                $unique[$key] = true;
            }
        }

        return $result;
    }

    private function generateTemporaryPassword(): string
    {
        return sprintf(
            '%s@%s%s',
            substr(
                strtoupper(
                    bin2hex(random_bytes(3))
                ),
                0,
                5
            ),
            random_int(100, 999),
            substr(
                strtolower(
                    bin2hex(random_bytes(2))
                ),
                0,
                3
            )
        );
    }

    private function assertPositiveId(
        int $id,
        string $field
    ): void {
        if ($id <= 0) {
            throw new UserValidationException(
                'Validation failed.',
                [
                    $field => [
                        'A positive integer is required.',
                    ],
                ]
            );
        }
    }

    /**
     * Remove password and internal-only fields.
     *
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    private function formatUser(
        array $user
    ): array {
        return [
            'id' => (int) $user['u_id'],
            'username' =>
                (string) $user['u_name'],
            'first_name' =>
                $user['u_fname'] ?? null,
            'middle_name' =>
                $user['u_mname'] ?? null,
            'last_name' =>
                $user['u_lname'] ?? null,
            'full_name' => trim(
                implode(
                    ' ',
                    array_filter([
                        $user['u_fname'] ?? null,
                        $user['u_mname'] ?? null,
                        $user['u_lname'] ?? null,
                    ])
                )
            ),
            'email' =>
                $user['u_email_id'] ?? null,
            'mobile' =>
                $user['u_mob_no'] ?? null,
            'active' =>
                (int) $user['active'] === 1,
            'last_login_at' =>
                $user['last_login_at'] ?? null,
            'password_changed_at' =>
                $user['password_changed_at']
                    ?? null,
            'created_at' =>
                $user['created_at'] ?? null,
            'updated_at' =>
                $user['updated_at'] ?? null,
        ];
    }

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
}

/**
 * User validation failure.
 */
final class UserValidationException extends RuntimeException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = []
    ) {
        parent::__construct($message, 422);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

/**
 * User not found.
 */
final class UserNotFoundException extends RuntimeException
{
    public function __construct(
        string $message = 'User was not found.'
    ) {
        parent::__construct($message, 404);
    }
}
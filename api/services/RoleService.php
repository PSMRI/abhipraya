<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Authentication;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Validator;
use App\Repositories\AuthRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Role management business service.
 *
 * Responsibilities:
 * - List and view roles
 * - Create and update roles
 * - Delete non-system roles
 * - Load permission matrix
 * - Assign permissions
 * - Clone roles
 * - Protect system roles
 * - Prevent privilege escalation
 * - Write audit records
 */
final class RoleService
{
    private RoleRepository $roleRepository;

    private AuthRepository $authRepository;

    private UserRepository $userRepository;

    public function __construct(
        ?RoleRepository $roleRepository = null,
        ?AuthRepository $authRepository = null,
        ?UserRepository $userRepository = null
    ) {
        $this->roleRepository = $roleRepository
            ?? new RoleRepository(Database::connection());

        $this->authRepository = $authRepository
            ?? new AuthRepository(Database::connection());

        $this->userRepository = $userRepository
            ?? new UserRepository(Database::connection());
    }

    /**
     * Return a paginated role list.
     *
     * Supported query parameters:
     * - search
     * - status
     * - page
     * - limit
     *
     * @return array<string, mixed>
     */
    public function listRoles(array $query): array
    {
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
        ];

        return $this->roleRepository->paginate(
            $filters,
            $page,
            $limit
        );
    }

    /**
     * Return role details with assigned permissions.
     *
     * @return array<string, mixed>
     */
    public function getRole(int $roleId): array
    {
        $this->assertPositiveId(
            $roleId,
            'role_id'
        );

        $role = $this->roleRepository->findById(
            $roleId
        );

        if ($role === null) {
            throw new RoleNotFoundException();
        }

        $permissionIds =
            $this->roleRepository
                ->getPermissionIds($roleId);

        $permissions =
            $this->roleRepository
                ->getPermissionsByRole($roleId);

        return [
            'role' => $this->formatRole($role),
            'permission_ids' => $permissionIds,
            'permissions' => $permissions,
            'assigned_user_count' =>
                $this->userRepository
                    ->countUsersByRole($roleId),
        ];
    }

    /**
     * Create a role and optionally assign permissions.
     *
     * Expected payload:
     *
     * {
     *   "role_code": "FACILITY_ADMIN",
     *   "role_name": "Facility Administrator",
     *   "description": "Manages assigned facilities.",
     *   "active_status": 1,
     *   "permission_ids": [1, 2, 3]
     * }
     *
     * @return array<string, mixed>
     */
    public function createRole(
        array $payload,
        Request $request
    ): array {
        $this->validateCreatePayload($payload);

        $roleCode = strtoupper(
            trim((string) $payload['role_code'])
        );

        $roleName = trim(
            (string) $payload['role_name']
        );

        if (
            $this->roleRepository
                ->codeExists($roleCode)
        ) {
            throw new RoleValidationException(
                'Validation failed.',
                [
                    'role_code' => [
                        'Role code already exists.',
                    ],
                ]
            );
        }

        $permissionIds =
            $this->normalizeIntegerList(
                $payload['permission_ids'] ?? [],
                'permission_ids'
            );

        $this->validatePermissionIds(
            $permissionIds
        );

        $this->enforcePermissionGrantCeiling(
            $permissionIds
        );

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        try {
            $roleId = Database::transaction(
                function (PDO $pdo) use (
                    $roleCode,
                    $roleName,
                    $payload,
                    $permissionIds,
                    $actorUserId,
                    $request
                ): int {
                    $roleId =
                        $this->roleRepository->create([
                            'role_code' => $roleCode,
                            'role_name' => $roleName,
                            'description' =>
                                $this->nullableString(
                                    $payload['description']
                                        ?? null
                                ),
                            'active_status' =>
                                (int) (
                                    $payload['active_status']
                                    ?? 1
                                ),
                            'is_system_role' => 0,
                        ]);

                    if ($permissionIds !== []) {
                        $this->roleRepository
                            ->replacePermissions(
                                $roleId,
                                $permissionIds
                            );
                    }

                    $this->authRepository->writeAudit(
                        $actorUserId,
                        'ROLE_CREATED',
                        'ROLE',
                        (string) $roleId,
                        null,
                        [
                            'role_code' => $roleCode,
                            'role_name' => $roleName,
                            'description' =>
                                $payload['description']
                                    ?? null,
                            'active_status' =>
                                (int) (
                                    $payload['active_status']
                                    ?? 1
                                ),
                            'permission_ids' =>
                                $permissionIds,
                        ],
                        $request->ip(),
                        $request->requestId()
                    );

                    return $roleId;
                }
            );

            Logger::info(
                'Role created successfully',
                [
                    'role_id' => $roleId,
                    'role_code' => $roleCode,
                    'created_by' => $actorUserId,
                ]
            );

            return $this->getRole($roleId);
        } catch (
            RoleValidationException
            | RuntimeException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            Logger::error(
                'Role creation failed',
                [
                    'role_code' => $roleCode,
                    'created_by' => $actorUserId,
                    'exception' =>
                        $exception->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Unable to create role.',
                500
            );
        }
    }

    /**
     * Update role metadata.
     *
     * Role code remains immutable.
     *
     * @return array<string, mixed>
     */
    public function updateRole(
        int $roleId,
        array $payload,
        Request $request
    ): array {
        $this->assertPositiveId(
            $roleId,
            'role_id'
        );

        $role = $this->roleRepository->findById(
            $roleId
        );

        if ($role === null) {
            throw new RoleNotFoundException();
        }

        $this->validateUpdatePayload($payload);

        if (
            array_key_exists('role_code', $payload)
            && strtoupper(
                trim((string) $payload['role_code'])
            ) !== (string) $role['role_code']
        ) {
            throw new RoleValidationException(
                'Validation failed.',
                [
                    'role_code' => [
                        'Role code cannot be changed.',
                    ],
                ]
            );
        }

        $isSystemRole =
            (int) ($role['is_system_role'] ?? 0) === 1;

        if ($isSystemRole) {
            $this->protectSystemRoleUpdate(
                $role,
                $payload
            );
        }

        $newData = [
            'role_name' =>
                $payload['role_name']
                    ?? $role['role_name'],
            'description' =>
                array_key_exists(
                    'description',
                    $payload
                )
                    ? $this->nullableString(
                        $payload['description']
                    )
                    : $role['description'],
            'active_status' =>
                array_key_exists(
                    'active_status',
                    $payload
                )
                    ? (int) $payload['active_status']
                    : (int) $role['active_status'],
        ];

        if (
            $isSystemRole
            && $newData['active_status'] === 0
        ) {
            throw new RoleValidationException(
                'Validation failed.',
                [
                    'active_status' => [
                        'A protected system role cannot be deactivated.',
                    ],
                ]
            );
        }

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $roleId,
                $role,
                $newData,
                $actorUserId,
                $request
            ): void {
                $this->roleRepository->update(
                    $roleId,
                    $newData
                );

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'ROLE_UPDATED',
                    'ROLE',
                    (string) $roleId,
                    $this->formatRole($role),
                    $newData,
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        Logger::info(
            'Role updated successfully',
            [
                'role_id' => $roleId,
                'updated_by' => $actorUserId,
            ]
        );

        return $this->getRole($roleId);
    }

    /**
     * Delete a role.
     *
     * Protected system roles and assigned roles
     * cannot be deleted.
     *
     * @return array<string, mixed>
     */
    public function deleteRole(
        int $roleId,
        Request $request
    ): array {
        $this->assertPositiveId(
            $roleId,
            'role_id'
        );

        $role = $this->roleRepository->findById(
            $roleId
        );

        if ($role === null) {
            throw new RoleNotFoundException();
        }

        if (
            (int) ($role['is_system_role'] ?? 0) === 1
            || strtoupper(
                (string) $role['role_code']
            ) === 'SUPER_ADMIN'
        ) {
            throw new RoleValidationException(
                'Role cannot be deleted.',
                [
                    'role_id' => [
                        'Protected system roles cannot be deleted.',
                    ],
                ]
            );
        }

        $assignedUserCount =
            $this->userRepository
                ->countUsersByRole($roleId);

        if ($assignedUserCount > 0) {
            throw new RoleValidationException(
                'Role cannot be deleted.',
                [
                    'role_id' => [
                        "The role is assigned to {$assignedUserCount} active user(s).",
                    ],
                ]
            );
        }

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $roleId,
                $role,
                $actorUserId,
                $request
            ): void {
                $this->roleRepository->delete(
                    $roleId
                );

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'ROLE_DELETED',
                    'ROLE',
                    (string) $roleId,
                    $this->formatRole($role),
                    null,
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        Logger::info(
            'Role deleted successfully',
            [
                'role_id' => $roleId,
                'deleted_by' => $actorUserId,
            ]
        );

        return [
            'role_id' => $roleId,
            'deleted' => true,
        ];
    }

    /**
     * Return permission matrix.
     *
     * @return array<string, mixed>
     */
    public function getPermissions(
        ?int $roleId = null
    ): array {
        $assignedPermissionIds = [];

        if ($roleId !== null) {
            $this->assertPositiveId(
                $roleId,
                'role_id'
            );

            $role = $this->roleRepository
                ->findById($roleId);

            if ($role === null) {
                throw new RoleNotFoundException();
            }

            $assignedPermissionIds =
                $this->roleRepository
                    ->getPermissionIds($roleId);
        }

        $allPermissions =
            $this->roleRepository
                ->getAllPermissions();

        $grouped = [];

        foreach ($allPermissions as $permission) {
            $moduleName = (string) (
                $permission['module_name']
                ?? 'General'
            );

            if (!isset($grouped[$moduleName])) {
                $grouped[$moduleName] = [];
            }

            $permissionId =
                (int) $permission['permission_id'];

            $grouped[$moduleName][] = [
                'id' => $permissionId,
                'code' =>
                    (string) $permission['permission_code'],
                'name' =>
                    (string) $permission['permission_name'],
                'description' =>
                    $permission['description'] ?? null,
                'assigned' => in_array(
                    $permissionId,
                    $assignedPermissionIds,
                    true
                ),
            ];
        }

        $modules = [];

        foreach ($grouped as $module => $items) {
            $modules[] = [
                'module' => $module,
                'permissions' => $items,
            ];
        }

        return [
            'modules' => $modules,
            'assigned_permission_ids' =>
                $assignedPermissionIds,
        ];
    }

    /**
     * Replace all permissions assigned to a role.
     *
     * @return array<string, mixed>
     */
    public function assignPermissions(
        int $roleId,
        array $payload,
        Request $request
    ): array {
        $this->assertPositiveId(
            $roleId,
            'role_id'
        );

        $role = $this->roleRepository
            ->findById($roleId);

        if ($role === null) {
            throw new RoleNotFoundException();
        }

        if (
            !isset($payload['permission_ids'])
            || !is_array(
                $payload['permission_ids']
            )
        ) {
            throw new RoleValidationException(
                'Validation failed.',
                [
                    'permission_ids' => [
                        'permission_ids must be an array.',
                    ],
                ]
            );
        }

        $permissionIds =
            $this->normalizeIntegerList(
                $payload['permission_ids'],
                'permission_ids'
            );

        $this->validatePermissionIds(
            $permissionIds
        );

        $this->enforcePermissionGrantCeiling(
            $permissionIds
        );

        $oldPermissionIds =
            $this->roleRepository
                ->getPermissionIds($roleId);

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        Database::transaction(
            function (PDO $pdo) use (
                $roleId,
                $oldPermissionIds,
                $permissionIds,
                $actorUserId,
                $request
            ): void {
                $this->roleRepository
                    ->replacePermissions(
                        $roleId,
                        $permissionIds
                    );

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'ROLE_PERMISSION_CHANGED',
                    'ROLE',
                    (string) $roleId,
                    [
                        'permission_ids' =>
                            $oldPermissionIds,
                    ],
                    [
                        'permission_ids' =>
                            $permissionIds,
                    ],
                    $request->ip(),
                    $request->requestId()
                );
            }
        );

        Logger::info(
            'Role permissions updated',
            [
                'role_id' => $roleId,
                'updated_by' => $actorUserId,
            ]
        );

        return $this->getRole($roleId);
    }

    /**
     * Clone a role and all assigned permissions.
     *
     * @return array<string, mixed>
     */
    public function cloneRole(
        int $sourceRoleId,
        array $payload,
        Request $request
    ): array {
        $this->assertPositiveId(
            $sourceRoleId,
            'source_role_id'
        );

        $sourceRole =
            $this->roleRepository
                ->findById($sourceRoleId);

        if ($sourceRole === null) {
            throw new RoleNotFoundException(
                'Source role was not found.'
            );
        }

        $this->validateCreatePayload($payload);

        $newRoleCode = strtoupper(
            trim((string) $payload['role_code'])
        );

        if (
            $this->roleRepository
                ->codeExists($newRoleCode)
        ) {
            throw new RoleValidationException(
                'Validation failed.',
                [
                    'role_code' => [
                        'Role code already exists.',
                    ],
                ]
            );
        }

        $permissionIds =
            $this->roleRepository
                ->getPermissionIds($sourceRoleId);

        $this->enforcePermissionGrantCeiling(
            $permissionIds
        );

        $actor = Authentication::requireUser();
        $actorUserId = (int) $actor['user_id'];

        $newRoleId = Database::transaction(
            function (PDO $pdo) use (
                $sourceRoleId,
                $newRoleCode,
                $payload,
                $permissionIds,
                $actorUserId,
                $request
            ): int {
                $newRoleId =
                    $this->roleRepository->create([
                        'role_code' => $newRoleCode,
                        'role_name' => trim(
                            (string) $payload['role_name']
                        ),
                        'description' =>
                            $this->nullableString(
                                $payload['description']
                                    ?? null
                            ),
                        'active_status' =>
                            (int) (
                                $payload['active_status']
                                ?? 1
                            ),
                        'is_system_role' => 0,
                    ]);

                $this->roleRepository
                    ->replacePermissions(
                        $newRoleId,
                        $permissionIds
                    );

                $this->authRepository->writeAudit(
                    $actorUserId,
                    'ROLE_CLONED',
                    'ROLE',
                    (string) $newRoleId,
                    null,
                    [
                        'source_role_id' =>
                            $sourceRoleId,
                        'new_role_code' =>
                            $newRoleCode,
                        'permission_ids' =>
                            $permissionIds,
                    ],
                    $request->ip(),
                    $request->requestId()
                );

                return $newRoleId;
            }
        );

        return $this->getRole($newRoleId);
    }

    private function validateCreatePayload(
        array $payload
    ): void {
        $validator = new Validator();

        $validator
            ->required($payload, 'role_code')
            ->string($payload, 'role_code', 2, 50)
            ->required($payload, 'role_name')
            ->string($payload, 'role_name', 2, 100)
            ->string($payload, 'description', 0, 255);

        if (
            isset($payload['active_status'])
        ) {
            $validator->in(
                $payload,
                'active_status',
                [0, 1, '0', '1']
            );
        }

        if (
            isset($payload['role_code'])
        ) {
            $validator->regex(
                $payload,
                'role_code',
                '/^[A-Za-z][A-Za-z0-9_]*$/',
                'Role code may contain letters, numbers and underscores only.'
            );
        }

        if ($validator->fails()) {
            throw new RoleValidationException(
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
            ->string($payload, 'role_name', 2, 100)
            ->string($payload, 'description', 0, 255);

        if (
            isset($payload['active_status'])
        ) {
            $validator->in(
                $payload,
                'active_status',
                [0, 1, '0', '1']
            );
        }

        if ($validator->fails()) {
            throw new RoleValidationException(
                'Validation failed.',
                $validator->errors()
            );
        }
    }

    /**
     * Prevent non-super administrators from granting
     * permissions they do not possess.
     *
     * @param array<int, int> $permissionIds
     */
    private function enforcePermissionGrantCeiling(
        array $permissionIds
    ): void {
        $actor = Authentication::requireUser();

        if (
            in_array(
                'SUPER_ADMIN',
                $actor['roles'] ?? [],
                true
            )
        ) {
            return;
        }

        $actorPermissionCodes =
            array_values(
                $actor['permissions'] ?? []
            );

        $requestedPermissions =
            $this->roleRepository
                ->getPermissionsByIds(
                    $permissionIds
                );

        foreach (
            $requestedPermissions
            as $permission
        ) {
            $permissionCode =
                (string) $permission[
                    'permission_code'
                ];

            if (
                !in_array(
                    $permissionCode,
                    $actorPermissionCodes,
                    true
                )
            ) {
                throw new RoleValidationException(
                    'Permission assignment denied.',
                    [
                        'permission_ids' => [
                            "You cannot grant permission: {$permissionCode}.",
                        ],
                    ]
                );
            }
        }
    }

    /**
     * Confirm that all permission IDs exist.
     *
     * @param array<int, int> $permissionIds
     */
    private function validatePermissionIds(
        array $permissionIds
    ): void {
        if ($permissionIds === []) {
            return;
        }

        $existingPermissions =
            $this->roleRepository
                ->getPermissionsByIds(
                    $permissionIds
                );

        $existingIds = array_map(
            static fn (array $permission): int =>
                (int) $permission['permission_id'],
            $existingPermissions
        );

        $missingIds = array_values(
            array_diff(
                $permissionIds,
                $existingIds
            )
        );

        if ($missingIds !== []) {
            throw new RoleValidationException(
                'Validation failed.',
                [
                    'permission_ids' => [
                        'One or more permission IDs do not exist: '
                        . implode(', ', $missingIds),
                    ],
                ]
            );
        }
    }

    /**
     * Prevent dangerous changes to protected roles.
     *
     * @param array<string, mixed> $role
     * @param array<string, mixed> $payload
     */
    private function protectSystemRoleUpdate(
        array $role,
        array $payload
    ): void {
        if (
            isset($payload['active_status'])
            && (int) $payload['active_status'] === 0
        ) {
            throw new RoleValidationException(
                'Validation failed.',
                [
                    'active_status' => [
                        'Protected system roles cannot be deactivated.',
                    ],
                ]
            );
        }

        if (
            strtoupper(
                (string) $role['role_code']
            ) === 'SUPER_ADMIN'
            && isset($payload['role_name'])
            && trim(
                (string) $payload['role_name']
            ) === ''
        ) {
            throw new RoleValidationException(
                'Validation failed.',
                [
                    'role_name' => [
                        'Super Administrator role name cannot be empty.',
                    ],
                ]
            );
        }
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIntegerList(
        mixed $values,
        string $field
    ): array {
        if (!is_array($values)) {
            throw new RoleValidationException(
                'Validation failed.',
                [
                    $field => [
                        "{$field} must be an array.",
                    ],
                ]
            );
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
                throw new RoleValidationException(
                    'Validation failed.',
                    [
                        $field => [
                            'Every ID must be a positive integer.',
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

    private function assertPositiveId(
        int $id,
        string $field
    ): void {
        if ($id <= 0) {
            throw new RoleValidationException(
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
     * @param array<string, mixed> $role
     *
     * @return array<string, mixed>
     */
    private function formatRole(
        array $role
    ): array {
        return [
            'id' =>
                (int) $role['role_id'],
            'code' =>
                (string) $role['role_code'],
            'name' =>
                (string) $role['role_name'],
            'description' =>
                $role['description'] ?? null,
            'active' =>
                (int) $role['active_status'] === 1,
            'is_system_role' =>
                (int) (
                    $role['is_system_role']
                    ?? 0
                ) === 1,
            'created_at' =>
                $role['created_at'] ?? null,
            'updated_at' =>
                $role['updated_at'] ?? null,
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
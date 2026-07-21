<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Authentication repository.
 *
 * Responsibilities:
 * - Read roles, permissions and access scope
 * - Record login attempts
 * - Write authentication audit logs
 * - Create and validate password-reset tokens
 * - Revoke user sessions
 *
 * Business rules must remain in AuthService.
 */
final class AuthRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * Count all login attempts from an IP address
     * within the configured time window.
     */
    public function countRecentIpAttempts(
        string $ipAddress,
        int $windowMinutes
    ): int {
        try {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) AS attempt_count
                 FROM login_attempts
                 WHERE ip_address = :ip_address
                   AND attempt_time >= DATE_SUB(
                       NOW(),
                       INTERVAL :window_minutes MINUTE
                   )'
            );

            $statement->bindValue(
                ':ip_address',
                $ipAddress,
                PDO::PARAM_STR
            );

            $statement->bindValue(
                ':window_minutes',
                $windowMinutes,
                PDO::PARAM_INT
            );

            $statement->execute();

            $row = $statement->fetch();

            return (int) ($row['attempt_count'] ?? 0);
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to count recent IP login attempts.',
                $exception,
                [
                    'ip_address' => $ipAddress,
                    'window_minutes' => $windowMinutes,
                ]
            );

            throw new RuntimeException(
                'Unable to verify login rate limit.'
            );
        }
    }

    /**
     * Count failed login attempts for the same username
     * and IP address within the lockout window.
     */
    public function countRecentFailedAttempts(
        string $username,
        string $ipAddress,
        int $lockoutMinutes
    ): int {
        try {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) AS failed_count
                 FROM login_attempts
                 WHERE username = :username
                   AND ip_address = :ip_address
                   AND status = :status
                   AND attempt_time >= DATE_SUB(
                       NOW(),
                       INTERVAL :lockout_minutes MINUTE
                   )'
            );

            $statement->bindValue(
                ':username',
                $username,
                PDO::PARAM_STR
            );

            $statement->bindValue(
                ':ip_address',
                $ipAddress,
                PDO::PARAM_STR
            );

            $statement->bindValue(
                ':status',
                'FAILED',
                PDO::PARAM_STR
            );

            $statement->bindValue(
                ':lockout_minutes',
                $lockoutMinutes,
                PDO::PARAM_INT
            );

            $statement->execute();

            $row = $statement->fetch();

            return (int) ($row['failed_count'] ?? 0);
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to count recent failed login attempts.',
                $exception,
                [
                    'username' => $username,
                    'ip_address' => $ipAddress,
                    'lockout_minutes' => $lockoutMinutes,
                ]
            );

            throw new RuntimeException(
                'Unable to verify account lockout status.'
            );
        }
    }

    /**
     * Record a login attempt.
     *
     * Allowed statuses:
     * - SUCCESS
     * - FAILED
     * - LOCKED
     */
    public function recordLoginAttempt(
        string $username,
        string $ipAddress,
        string $status
    ): void {
        $allowedStatuses = [
            'SUCCESS',
            'FAILED',
            'LOCKED',
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException(
                'Unsupported login attempt status.'
            );
        }

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO login_attempts
                    (
                        username,
                        ip_address,
                        attempt_time,
                        status
                    )
                 VALUES
                    (
                        :username,
                        :ip_address,
                        NOW(),
                        :status
                    )'
            );

            $statement->execute([
                'username' => $username,
                'ip_address' => $ipAddress,
                'status' => $status,
            ]);
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to record login attempt.',
                $exception,
                [
                    'username' => $username,
                    'ip_address' => $ipAddress,
                    'status' => $status,
                ]
            );

            throw new RuntimeException(
                'Unable to record login attempt.'
            );
        }
    }

    /**
     * Return active user role codes.
     *
     * @return array<int, string>
     */
    public function getUserRoles(
        int $userId
    ): array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT DISTINCT
                    r.role_code
                 FROM user_role ur
                 INNER JOIN role_master r
                    ON r.role_id = ur.role_id
                 WHERE ur.user_id = :user_id
                   AND r.active_status = 1
                 ORDER BY r.role_code'
            );

            $statement->execute([
                'user_id' => $userId,
            ]);

            return array_values(array_map(
                static fn (array $row): string =>
                    (string) $row['role_code'],
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
     * Return distinct permission codes granted
     * through all active roles.
     *
     * @return array<int, string>
     */
    public function getUserPermissions(
        int $userId
    ): array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT DISTINCT
                    p.permission_code
                 FROM user_role ur
                 INNER JOIN role_master r
                    ON r.role_id = ur.role_id
                   AND r.active_status = 1
                 INNER JOIN role_permission rp
                    ON rp.role_id = ur.role_id
                 INNER JOIN permission_master p
                    ON p.permission_id = rp.permission_id
                 WHERE ur.user_id = :user_id
                 ORDER BY p.permission_code'
            );

            $statement->execute([
                'user_id' => $userId,
            ]);

            return array_values(array_map(
                static fn (array $row): string =>
                    (string) $row['permission_code'],
                $statement->fetchAll()
            ));
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to load user permissions.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to load user permissions.'
            );
        }
    }

    /**
     * Return user facility and department scope.
     *
     * Expected table:
     * user_department_scope
     *
     * @return array{
     *     facility_ids: array<int, int>,
     *     department_ids: array<int, int>
     * }
     */
    public function getUserScope(
        int $userId
    ): array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    facility_id,
                    department_id
                 FROM user_department_scope
                 WHERE user_id = :user_id'
            );

            $statement->execute([
                'user_id' => $userId,
            ]);

            $facilityIds = [];
            $departmentIds = [];

            foreach ($statement->fetchAll() as $row) {
                if ($row['facility_id'] !== null) {
                    $facilityIds[] =
                        (int) $row['facility_id'];
                }

                if ($row['department_id'] !== null) {
                    $departmentIds[] =
                        (int) $row['department_id'];
                }
            }

            return [
                'facility_ids' => array_values(
                    array_unique($facilityIds)
                ),
                'department_ids' => array_values(
                    array_unique($departmentIds)
                ),
            ];
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to load user access scope.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to load user access scope.'
            );
        }
    }

    /**
     * Write an audit record.
     *
     * @param array<string, mixed>|null $oldValue
     * @param array<string, mixed>|null $newValue
     */
    public function writeAudit(
        ?int $userId,
        string $actionCode,
        ?string $entityType,
        ?string $entityId,
        ?array $oldValue,
        ?array $newValue,
        string $ipAddress,
        string $requestId
    ): void {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO audit_log
                    (
                        user_id,
                        action_code,
                        entity_type,
                        entity_id,
                        old_value,
                        new_value,
                        ip_address,
                        request_id,
                        created_at
                    )
                 VALUES
                    (
                        :user_id,
                        :action_code,
                        :entity_type,
                        :entity_id,
                        :old_value,
                        :new_value,
                        :ip_address,
                        :request_id,
                        NOW()
                    )'
            );

            $statement->bindValue(
                ':user_id',
                $userId,
                $userId === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_INT
            );

            $statement->bindValue(
                ':action_code',
                $actionCode,
                PDO::PARAM_STR
            );

            $statement->bindValue(
                ':entity_type',
                $entityType,
                $entityType === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $statement->bindValue(
                ':entity_id',
                $entityId,
                $entityId === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $statement->bindValue(
                ':old_value',
                $oldValue !== null
                    ? $this->encodeJson($oldValue)
                    : null,
                $oldValue === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $statement->bindValue(
                ':new_value',
                $newValue !== null
                    ? $this->encodeJson($newValue)
                    : null,
                $newValue === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $statement->bindValue(
                ':ip_address',
                $ipAddress,
                PDO::PARAM_STR
            );

            $statement->bindValue(
                ':request_id',
                $requestId,
                PDO::PARAM_STR
            );

            $statement->execute();
        } catch (Throwable $exception) {
            /*
             * Audit failure should be logged.
             * Whether it should stop the main action depends
             * on the security policy. AuthService currently
             * treats most authentication audit events as required.
             */
            Logger::error(
                'Unable to write audit record',
                [
                    'user_id' => $userId,
                    'action_code' => $actionCode,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'exception' =>
                        $exception->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Unable to write audit record.'
            );
        }
    }

    /**
     * Create a password-reset token.
     *
     * The database stores only the token hash.
     */
    public function createPasswordResetToken(
        int $userId,
        string $tokenHash,
        int $expiryMinutes
    ): int {
        try {
            /*
             * Invalidate earlier unused reset tokens.
             */
            $invalidateStatement =
                $this->pdo->prepare(
                    'UPDATE password_reset_token
                     SET used_at = NOW()
                     WHERE user_id = :user_id
                       AND used_at IS NULL'
                );

            $invalidateStatement->execute([
                'user_id' => $userId,
            ]);

            $statement = $this->pdo->prepare(
                'INSERT INTO password_reset_token
                    (
                        user_id,
                        token_hash,
                        expires_at,
                        used_at,
                        created_at
                    )
                 VALUES
                    (
                        :user_id,
                        :token_hash,
                        DATE_ADD(
                            NOW(),
                            INTERVAL :expiry_minutes MINUTE
                        ),
                        NULL,
                        NOW()
                    )'
            );

            $statement->bindValue(
                ':user_id',
                $userId,
                PDO::PARAM_INT
            );

            $statement->bindValue(
                ':token_hash',
                $tokenHash,
                PDO::PARAM_STR
            );

            $statement->bindValue(
                ':expiry_minutes',
                $expiryMinutes,
                PDO::PARAM_INT
            );

            $statement->execute();

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to create password reset token.',
                $exception,
                [
                    'user_id' => $userId,
                    'expiry_minutes' =>
                        $expiryMinutes,
                ]
            );

            throw new RuntimeException(
                'Unable to create password reset token.'
            );
        }
    }

    /**
     * Find an unused, unexpired reset token.
     *
     * @return array<string, mixed>|null
     */
    public function findValidPasswordResetToken(
        string $tokenHash
    ): ?array {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    reset_id,
                    user_id,
                    token_hash,
                    expires_at,
                    used_at,
                    created_at
                 FROM password_reset_token
                 WHERE token_hash = :token_hash
                   AND used_at IS NULL
                   AND expires_at > NOW()
                 LIMIT 1'
            );

            $statement->execute([
                'token_hash' => $tokenHash,
            ]);

            $record = $statement->fetch();

            return is_array($record)
                ? $record
                : null;
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to validate password reset token.',
                $exception
            );

            throw new RuntimeException(
                'Unable to validate password reset token.'
            );
        }
    }

    /**
     * Mark a password-reset token as used.
     */
    public function markPasswordResetTokenUsed(
        int $resetId
    ): void {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE password_reset_token
                 SET used_at = NOW()
                 WHERE reset_id = :reset_id
                   AND used_at IS NULL'
            );

            $statement->execute([
                'reset_id' => $resetId,
            ]);

            if ($statement->rowCount() !== 1) {
                throw new RuntimeException(
                    'Password reset token has already been used.'
                );
            }
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to mark password reset token as used.',
                $exception,
                [
                    'reset_id' => $resetId,
                ]
            );

            throw new RuntimeException(
                'Unable to update password reset token.'
            );
        }
    }

    /**
     * Revoke all active database-backed sessions
     * for a user.
     */
    public function revokeUserSessions(
        int $userId
    ): int {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE user_sessions
                 SET revoked_at = NOW()
                 WHERE user_id = :user_id
                   AND revoked_at IS NULL'
            );

            $statement->execute([
                'user_id' => $userId,
            ]);

            return $statement->rowCount();
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to revoke user sessions.',
                $exception,
                [
                    'user_id' => $userId,
                ]
            );

            throw new RuntimeException(
                'Unable to revoke user sessions.'
            );
        }
    }

    /**
     * Remove old login attempts.
     *
     * This may be run through a scheduled cleanup job.
     */
    public function deleteOldLoginAttempts(
        int $retentionDays = 90
    ): int {
        try {
            $statement = $this->pdo->prepare(
                'DELETE FROM login_attempts
                 WHERE attempt_time < DATE_SUB(
                     NOW(),
                     INTERVAL :retention_days DAY
                 )'
            );

            $statement->bindValue(
                ':retention_days',
                $retentionDays,
                PDO::PARAM_INT
            );

            $statement->execute();

            return $statement->rowCount();
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to delete old login attempts.',
                $exception,
                [
                    'retention_days' =>
                        $retentionDays,
                ]
            );

            throw new RuntimeException(
                'Unable to clean old login attempts.'
            );
        }
    }

    /**
     * Delete expired or already-used reset tokens.
     */
    public function deleteExpiredResetTokens(
        int $usedTokenRetentionDays = 7
    ): int {
        try {
            $statement = $this->pdo->prepare(
                'DELETE FROM password_reset_token
                 WHERE expires_at < NOW()
                    OR (
                        used_at IS NOT NULL
                        AND used_at < DATE_SUB(
                            NOW(),
                            INTERVAL :retention_days DAY
                        )
                    )'
            );

            $statement->bindValue(
                ':retention_days',
                $usedTokenRetentionDays,
                PDO::PARAM_INT
            );

            $statement->execute();

            return $statement->rowCount();
        } catch (PDOException $exception) {
            $this->logDatabaseError(
                'Unable to delete expired reset tokens.',
                $exception,
                [
                    'retention_days' =>
                        $usedTokenRetentionDays,
                ]
            );

            throw new RuntimeException(
                'Unable to clean password reset tokens.'
            );
        }
    }

    /**
     * Safely encode an audit JSON value.
     *
     * @param array<string, mixed> $value
     */
    private function encodeJson(
        array $value
    ): string {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        return $json;
    }

    /**
     * Write a structured database error.
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
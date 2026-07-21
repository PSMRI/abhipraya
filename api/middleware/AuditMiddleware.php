<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use PDO;
use Throwable;

final class AuditMiddleware
{
    public function __construct(
        private readonly string $actionCode,
        private readonly ?string $entityType = null,
        private readonly ?callable $entityIdResolver = null,
        private readonly ?callable $valueResolver = null
    ) {
    }

    public function handle(
        Request $request,
        callable $next,
        array $context = []
    ): mixed {
        $startedAt = microtime(true);
        $result = null;
        $status = 'SUCCESS';
        $errorMessage = null;

        try {
            $result = $next($request, $context);
            return $result;
        } catch (Throwable $exception) {
            $status = 'FAILED';
            $errorMessage = $exception->getMessage();
            throw $exception;
        } finally {
            $this->writeAudit(
                $request,
                $context,
                $result,
                $status,
                $errorMessage,
                $startedAt
            );
        }
    }

    private function writeAudit(
        Request $request,
        array $context,
        mixed $result,
        string $status,
        ?string $errorMessage,
        float $startedAt
    ): void {
        try {
            $entityId = null;

            if ($this->entityIdResolver !== null) {
                $resolved = ($this->entityIdResolver)(
                    $request,
                    $context,
                    $result
                );

                if ($resolved !== null) {
                    $entityId = (string) $resolved;
                }
            }

            $oldValue = null;
            $newValue = null;

            if ($this->valueResolver !== null) {
                $values = ($this->valueResolver)(
                    $request,
                    $context,
                    $result
                );

                if (is_array($values)) {
                    $oldValue = $values['old'] ?? null;
                    $newValue = $values['new'] ?? null;
                }
            }

            $metadata = [
                'status' => $status,
                'http_method' => $request->method(),
                'path' => $request->path(),
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'error' => $errorMessage,
            ];

            $statement = Database::connection()->prepare(
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

            $statement->execute([
                'user_id' => $context['user_id'] ?? null,
                'action_code' => $this->actionCode,
                'entity_type' => $this->entityType,
                'entity_id' => $entityId,
                'old_value' => $oldValue !== null
                    ? json_encode($oldValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'new_value' => json_encode(
                    [
                        'value' => $newValue,
                        'metadata' => $metadata,
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                'ip_address' => $request->ip(),
                'request_id' => $request->requestId(),
            ]);
        } catch (Throwable $exception) {
            Logger::error('Unable to write audit log', [
                'action_code' => $this->actionCode,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}

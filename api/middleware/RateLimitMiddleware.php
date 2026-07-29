<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use Closure;
use PDO;
use Throwable;

final class RateLimitMiddleware
{
    private readonly ?Closure $identityResolver;

    public function __construct(
        private readonly string $bucket,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        ?callable $identityResolver = null
    ) {
        $this->identityResolver = $identityResolver === null ? null : Closure::fromCallable($identityResolver);
    }

    public function handle(
        Request $request,
        callable $next,
        array $context = []
    ): mixed {
        $identity = $this->resolveIdentity($request, $context);
        $now = time();
        $windowStart = $now - ($now % $this->windowSeconds);
        $windowEnd = $windowStart + $this->windowSeconds;

        try {
            $count = Database::transaction(
                function (PDO $pdo) use (
                    $identity,
                    $windowStart,
                    $windowEnd
                ): int {
                    $select = $pdo->prepare(
                        'SELECT attempt_count
                         FROM api_rate_limits
                         WHERE bucket_key = :bucket
                           AND identity_key = :identity
                           AND window_start = FROM_UNIXTIME(:window_start)
                         FOR UPDATE'
                    );

                    $select->execute([
                        'bucket' => $this->bucket,
                        'identity' => $identity,
                        'window_start' => $windowStart,
                    ]);

                    $row = $select->fetch();

                    if ($row) {
                        $count = (int) $row['attempt_count'] + 1;

                        $update = $pdo->prepare(
                            'UPDATE api_rate_limits
                             SET attempt_count = :attempt_count,
                                 updated_at = NOW()
                             WHERE bucket_key = :bucket
                               AND identity_key = :identity
                               AND window_start = FROM_UNIXTIME(:window_start)'
                        );

                        $update->execute([
                            'attempt_count' => $count,
                            'bucket' => $this->bucket,
                            'identity' => $identity,
                            'window_start' => $windowStart,
                        ]);

                        return $count;
                    }

                    $insert = $pdo->prepare(
                        'INSERT INTO api_rate_limits
                            (
                                bucket_key,
                                identity_key,
                                window_start,
                                window_end,
                                attempt_count,
                                created_at,
                                updated_at
                            )
                         VALUES
                            (
                                :bucket,
                                :identity,
                                FROM_UNIXTIME(:window_start),
                                FROM_UNIXTIME(:window_end),
                                1,
                                NOW(),
                                NOW()
                            )'
                    );

                    $insert->execute([
                        'bucket' => $this->bucket,
                        'identity' => $identity,
                        'window_start' => $windowStart,
                        'window_end' => $windowEnd,
                    ]);

                    return 1;
                }
            );
        } catch (Throwable $exception) {
            Logger::error('Rate limit storage failed', [
                'bucket' => $this->bucket,
                'identity' => $identity,
                'exception' => $exception->getMessage(),
            ]);

            Response::error(
                'Unable to process the request at the moment.',
                503
            );
        }

        $remaining = max(0, $this->maxAttempts - $count);

        header('X-RateLimit-Limit: ' . $this->maxAttempts);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . $windowEnd);

        if ($count > $this->maxAttempts) {
            $retryAfter = max(1, $windowEnd - $now);

            header('Retry-After: ' . $retryAfter);

            Response::error(
                'Too many requests. Please try again later.',
                429,
                [],
                ['retry_after_seconds' => $retryAfter]
            );
        }

        $context['rate_limit'] = [
            'bucket' => $this->bucket,
            'identity' => $identity,
            'limit' => $this->maxAttempts,
            'remaining' => $remaining,
            'reset_at' => $windowEnd,
        ];

        return $next($request, $context);
    }

    private function resolveIdentity(
        Request $request,
        array $context
    ): string {
        if ($this->identityResolver !== null) {
            $identity = ($this->identityResolver)($request, $context);

            if (is_string($identity) && $identity !== '') {
                return hash('sha256', $identity);
            }
        }

        $parts = [
            $request->ip(),
            (string) ($context['user_id'] ?? ''),
            (string) $request->header('X-Device-ID', ''),
        ];

        return hash('sha256', implode('|', $parts));
    }
}

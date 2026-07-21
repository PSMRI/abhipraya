<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

final class JsonMiddleware
{
    public function __construct(
        private readonly int $maxPayloadBytes = 1048576,
        private readonly bool $requireBody = true
    ) {
    }

    public function handle(
        Request $request,
        callable $next,
        array $context = []
    ): mixed {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request, $context);
        }

        if ($request->contentType() !== 'application/json') {
            Response::error(
                'Content-Type must be application/json.',
                415
            );
        }

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

        if ($contentLength > $this->maxPayloadBytes) {
            Response::error(
                'Request payload is too large.',
                413
            );
        }

        try {
            $json = $request->json();
        } catch (\RuntimeException $exception) {
            Response::error(
                $exception->getMessage(),
                400
            );
        }

        if ($this->requireBody && $json === []) {
            Response::error(
                'JSON request body is required.',
                400
            );
        }

        $context['json'] = $json;

        return $next($request, $context);
    }
}

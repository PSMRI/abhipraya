<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Authorization;
use App\Core\Request;
use App\Core\Response;

final class ScopeMiddleware
{
    public function __construct(
        private readonly ?string $facilityKey = 'facility_id',
        private readonly ?string $departmentKey = 'department_id',
        private readonly string $source = 'auto'
    ) {
    }

    public function handle(
        Request $request,
        callable $next,
        array $context = []
    ): mixed {
        $facilityId = $this->facilityKey !== null
            ? $this->resolveId($request, $context, $this->facilityKey)
            : null;

        $departmentId = $this->departmentKey !== null
            ? $this->resolveId($request, $context, $this->departmentKey)
            : null;

        if ($facilityId !== null) {
            Authorization::requireFacility($facilityId);
            $context['facility_id'] = $facilityId;
        }

        if ($departmentId !== null) {
            Authorization::requireDepartment($departmentId);
            $context['department_id'] = $departmentId;
        }

        return $next($request, $context);
    }

    private function resolveId(
        Request $request,
        array $context,
        string $key
    ): ?int {
        $value = null;

        if ($this->source === 'route' || $this->source === 'auto') {
            $value = $context['route_params'][$key]
                ?? $context[$key]
                ?? null;
        }

        if (
            $value === null
            && ($this->source === 'query' || $this->source === 'auto')
        ) {
            $value = $request->query($key);
        }

        if (
            $value === null
            && ($this->source === 'json' || $this->source === 'auto')
            && in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)
        ) {
            $value = $request->input($key);
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
            Response::error(
                'Invalid scope identifier.',
                422,
                [$key => ['A positive integer is required.']]
            );
        }

        return (int) $value;
    }
}

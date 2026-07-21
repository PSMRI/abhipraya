<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Authorization;
use App\Core\Request;

final class PermissionMiddleware
{
    public function __construct(
        private readonly string|array $permissions,
        private readonly bool $requireAll = true
    ) {
    }

    public function handle(
        Request $request,
        callable $next,
        array $context = []
    ): mixed {
        $permissions = (array) $this->permissions;

        if ($this->requireAll) {
            foreach ($permissions as $permission) {
                Authorization::requirePermission($permission);
            }
        } else {
            $allowed = false;

            foreach ($permissions as $permission) {
                if (Authorization::hasPermission($permission)) {
                    $allowed = true;
                    break;
                }
            }

            if (!$allowed) {
                \App\Core\Response::error(
                    'You are not authorized to perform this action.',
                    403
                );
            }
        }

        $context['required_permissions'] = $permissions;

        return $next($request, $context);
    }
}

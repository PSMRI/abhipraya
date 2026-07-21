<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Authentication;
use App\Core\Request;

final class AuthMiddleware
{
    public function handle(
        Request $request,
        callable $next,
        array $context = []
    ): mixed {
        $user = Authentication::requireUser();

        $context['auth'] = $user;
        $context['user_id'] = (int) $user['user_id'];

        return $next($request, $context);
    }
}

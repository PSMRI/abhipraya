<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class RoleNotFoundException extends RuntimeException
{
    public function __construct(
        string $message = 'Role was not found.'
    ) {
        parent::__construct($message, 404);
    }
}
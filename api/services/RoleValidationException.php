<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class RoleValidationException extends RuntimeException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(
        string $message = 'Validation failed.',
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
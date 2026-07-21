<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class PublicSurveyValidationException extends RuntimeException
{
    public function __construct(
        string $message = 'Validation failed.',
        private readonly array $errors = []
    ) {
        parent::__construct($message, 422);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}

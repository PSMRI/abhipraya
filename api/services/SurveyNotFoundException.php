<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SurveyNotFoundException extends RuntimeException
{
    public function __construct(
        string $message = 'Survey was not found.'
    ) {
        parent::__construct($message, 404);
    }
}

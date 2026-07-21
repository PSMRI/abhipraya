<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class FacilityNotFoundException extends RuntimeException
{
    public function __construct(
        string $message = 'Facility was not found.'
    ) {
        parent::__construct($message, 404);
    }
}

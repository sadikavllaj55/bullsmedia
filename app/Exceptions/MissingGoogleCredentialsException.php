<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class MissingGoogleCredentialsException extends RuntimeException
{
    public function __construct(string $path)
    {
        parent::__construct("Google credentials not found at [{$path}]. See README → Google API setup.");
    }
}

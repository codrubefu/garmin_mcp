<?php

namespace App\Garmin;

use RuntimeException;

class GarminException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}

<?php

namespace App\Services\Payments;

class MoolreRequestException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $path,
        string $message,
    ) {
        parent::__construct($message);
    }
}

<?php

namespace App\Exceptions;

use Exception;

class InvalidOrderStatusException extends Exception
{
    public function __construct(string $currentStatus)
    {
        parent::__construct("Pesanan berstatus {$currentStatus}, hanya pesanan PENDING yang dapat dibayar", 409);
    }
}

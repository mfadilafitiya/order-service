<?php

namespace App\Exceptions;

use Exception;

class OrderNotFoundException extends Exception
{
    public function __construct(int|string $orderId)
    {
        parent::__construct("Pesanan dengan id {$orderId} tidak ditemukan", 404);
    }
}

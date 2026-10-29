<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(string $productName, int $requested, int $available)
    {
        parent::__construct("Stok {$productName} tidak mencukupi: diminta {$requested}, tersedia {$available}", 409);
    }
}

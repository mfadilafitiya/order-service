<?php

namespace App\Exceptions;

use Exception;

class CartEmptyException extends Exception
{
    public function __construct(string $message = 'Keranjang belanja kosong')
    {
        parent::__construct($message, 409);
    }
}

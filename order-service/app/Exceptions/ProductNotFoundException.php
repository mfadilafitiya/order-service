<?php

namespace App\Exceptions;

use Exception;

class ProductNotFoundException extends Exception
{
    public function __construct(string $message = 'Produk tidak ditemukan')
    {
        parent::__construct($message, 409);
    }
}

<?php

namespace App\Exceptions;

use Exception;

class ServiceUnavailableException extends Exception
{
    public function __construct(string $serviceName = 'Service')
    {
        parent::__construct("{$serviceName} tidak dapat dihubungi, coba lagi nanti", 503);
    }
}

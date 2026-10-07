<?php

namespace App\Exceptions;

use RuntimeException;

class WeatherUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Weather data is temporarily unavailable.');
    }
}

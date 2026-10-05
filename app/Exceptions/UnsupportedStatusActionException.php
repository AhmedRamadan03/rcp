<?php

namespace App\Exceptions;

class UnsupportedStatusActionException extends \InvalidArgumentException
{
    public function __construct(string $action)
    {
        parent::__construct("Unsupported status action: '{$action}'.");
    }
}
<?php

namespace App\Exceptions;

use Exception;

class ElmServiceExceptions extends Exception
{
    public static function notImplemented()
    {
        return new self(__('ElmServiceExceptions.notImplemented'));
    }
}

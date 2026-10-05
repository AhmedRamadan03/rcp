<?php

namespace App\Exceptions;

use Exception;

class WebExceptions extends Exception
{
    public static function currentPasswordIncorrect(): Exception
    {
        return new self('The current password is incorrect');
    }

    public static function confirmPasswordError(): Exception
    {
        return new self('The new password does not match the Confirm Password field');
    }
}

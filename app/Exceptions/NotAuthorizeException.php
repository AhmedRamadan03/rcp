<?php

namespace App\Exceptions;

use Exception;

class NotAuthorizeException extends Exception
{
    //
    public function render($request)
    {
        return response()->json(['error' => true, 'message' => $this->getMessage()]);
    }
}

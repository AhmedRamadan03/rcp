<?php

namespace App\Exceptions;

use Exception;

class AleardyApproved extends Exception
{
    //

    public function render($request)
    {
        return response()->json(['error' => true, 'message' => $this->getMessage()]);
    }
}

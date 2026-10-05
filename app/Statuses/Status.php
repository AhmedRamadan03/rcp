<?php

namespace App\Statuses;

interface Status
{
    public function handel(int $request_id);
}

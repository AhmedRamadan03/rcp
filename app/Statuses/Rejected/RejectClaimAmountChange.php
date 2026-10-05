<?php

namespace App\Statuses\Rejected;

use App\Statuses\Status;
use App\Enums\RequestStatus;
use App\Models\ChangeClaimAmountRequest;

class RejectClaimAmountChange implements Status
{
    public function handel(int $request_id)
    {


        $change_amount_request = ChangeClaimAmountRequest::where('request_id', $request_id)
            ->first();

        $change_amount_request->status = RequestStatus::REJECTED->value;
        $change_amount_request->save();

    }
}

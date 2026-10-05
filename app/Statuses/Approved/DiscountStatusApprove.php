<?php

namespace App\Statuses\Approved;

use App\Exceptions\RequiredSupervisorApprovalException;
use App\Statuses\Status;
use App\Enums\RequestStatus;
use App\Models\Discount;
use App\Models\StatusChangeRequest;
use App\Services\DiscountService;
use Exception;

class DiscountStatusApprove implements Status
{
    public function handel(int $request_id)
    {
        // TODO: Implement handel() method.
        try {
            $status_request = StatusChangeRequest::find($request_id);
            $user = $status_request->user;
            $discount = Discount::where('request_id', $request_id)->latest()->first();
            $note = $status_request->note;

            if ((new DiscountService)->executeOrRequestDiscount($discount, $note, $user)) {
                $status_request = StatusChangeRequest::find($request_id);
                $status_request->request_status = RequestStatus::APPROVED->value;
                $status_request->save();

            }else{
                throw new RequiredSupervisorApprovalException('this request has been sent to the next supervisor for further approval.');
            }
        } catch (RequiredSupervisorApprovalException $e) {
            throw $e;
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage());
        }

    }
}

<?php

namespace App\Enums;

use App\Models\PaymentLink;
use App\Models\RejectedClaim;
use App\Models\StatusChangeRequest;

enum EscalationModelType: string
{
    case REJECTED_CLAIM = RejectedClaim::class;
    case PAYMENT_LINK = PaymentLink::class;
    case STATUS_CHANGE_REQUEST = StatusChangeRequest::class;
}

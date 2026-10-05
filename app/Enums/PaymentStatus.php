<?php

namespace App\Enums;

enum PaymentStatus: string
{
    // case TRACKED = 'tracked'; // (should be in claim state)
    case PENDING = 'pending'; //  waiting for full payment calculated
    case PAID = 'paid';

    case SCHEDULED = 'scheduled'; //  for payments that have been scheduled at payment table only
    case DELAYED = 'delayed';
    case REJECTED = 'rejected';
    case CANCELED = 'canceled';
    case MODIFIED = 'modified'; // (remove)
    case UNPAID = 'unpaid';

    public static function color($status): string
    {
        return match ($status) {
            $status, $status => 'bg-red-50', $status => 'bg-red-50',
            $status => 'bg-cyan-50',
            $status => 'bg-orange-50',
            $status => 'bg-green-50',
            $status => 'bg-blue-50',
            $status => 'bg-gray-200',
        };
    }
}

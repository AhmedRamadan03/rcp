<?php

namespace App\Enums;

enum ClaimStatus: string
{
    case REGISTERED = 'registered';
    case ASSIGNED = 'assigned';
    case REJECTED = 'rejected';
    case APPROVED = 'approved';
    case FOLLOW_UP = 'follow_up';
    case DELAY = 'follow_up/delay';
    case PARTIAL = 'follow_up/partial';
    case LAW_FIRM = 'law_firm';
    case DEFAULT_IN_PAYMENT = 'default_in_payment';
    case DEFAULT_IN_PAYMENT_BY_SYSTEM = 'default_in_payment_by_system';
    case CLOSED = 'closed';
    case COLLECTED_OUT_OF_SYSTEM = 'collected_out_of_system';
    case DONE = 'done';

    case MANUAL_COLLECTED = 'manaual_collected';
    case MANUAL_COLLECTED_FOR_INSTALLMENT = 'manaual_collected_for_installment';
    case BANK_COLLECTED = 'bank_collected';
    case CREDITED = 'collected_credited';

    case DISCOUNT = 'discount';
    case CLAIM_AMOUNT_CHANGE = 'claim_amount_change';
    case TRANSFER_TO_ELM = 'transfer_to_elm';
    case DISPUTE_PAYMENT= 'dispute payment';
    case DELETE_INSTALLMENT = 'delete_installment';
    case BACK_TO_ORIGINAL_STATUS = 'back_to_original_status';
    public static function color($status)
    {
        return match ($status) {
            'registered' => 'bg-gray-600 text-white',
            'assigned' => 'bg-purple-100 text-black',
            'rejected' => 'bg-red-100 text-white',
            'approved' => 'bg-orange-400 text-black',
            'done' => 'bg-green-400 text-white',
            'follow_up' => 'bg-orange-700 text-white',
            'follow_up/delay' => 'bg-blue-200 text-white',
            'follow_up/partial' => 'bg-orange-400 text-white',
            'law_firm' => 'bg-red-200 text-white',
            'default_in_payment' => 'bg-red-500 text-white',
            'closed' => 'bg-purple-400 text-white',
            'manaual_collected' => 'bg-green-400 text-white',
            'bank_collected' => 'bg-purple-500 text-white',
            'default_in_payment_by_system' => 'bg-red-500 text-white',
            'collected_credited' => 'bg-green-800 text-white',
            'transfer_to_elm' => 'bg-cyan-800 text-white',
        };
    }

    public static function getString(string $status): string
    {
        return __('claim.status.'.str_replace('/', '_', $status));
        //        return match ($status) {
        //            self::REGISTERED->value => __('claim.status.'. self::REGISTERED->value) 'Outstanding',
        //            self::APPROVED->value => 'Pending',
        //            self::DONE->value => 'Collected \ By System',
        //            self::ASSIGNED->value => 'Assigned',
        //            self::FOLLOW_UP->value => 'Follow up',
        //            self::DELAY->value => 'Follow up \ Delay',
        //            self::PARTIAL->value => 'Follow up \ Partial',
        //            self::DEFAULT_IN_PAYMENT->value => 'Follow up \ Default In Payment \ Manual',
        //            self::CLOSED->value => 'Closed',
        //            self::REJECTED->value => 'Cancel',
        //            self::LAW_FIRM->value => 'Transferred to Legal Department',
        //            self::MANUAL_COLLECTED->value => 'Collected \ Manually',
        //            self::BANK_COLLECTED->value => 'Collected \ Bank',
        //            self::DEFAULT_IN_PAYMENT_BY_SYSTEM->value => 'Follow up \ Default In Payment \ System',
        //            self::CREDITED->value => 'Collected \ Credited',
        //        };
    }

    public static function defaultInPaymentStatuses(): array
    {
        return [
            self::DEFAULT_IN_PAYMENT->value,
            self::DEFAULT_IN_PAYMENT_BY_SYSTEM->value,
        ];
    }

}
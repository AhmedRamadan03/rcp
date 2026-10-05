<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum ReturnReason: int
{
    case USAGE_OUT_POLICY = 1;
    case HAVE_MORE_PASSANGER = 2;
    case MOVE_REVERSE_THE_DIRECTICION = 3;
    case DRIVE_UNDER_DRUG = 4;
    case DRIVE_UNDER_18 = 5;
    case LICENSE_ISSUES = 6;
    case DRIVER_NOT_FOUND_IN_ACCIDENT_LOCATION = 7;
    case CROSS_WHILE_STOP_LIGHT_IS_RED = 8;
    case GIVING_WRONG_INFORMATION_BEFORE_CREATION_OF_POLICY = 9;
    case MAKING_THE_ACCIDENT_INTENTIONALLY = 10;
    case NOT_REPORTING_ACCIDENT_TO_INSURANCE_COMPANY = 11;
    case STAIL_THE_CAR = 12;
    case FRODING = 13;
    case THE_LICENSE_WAS_EXPIRED = 14;
    case NO_VALID_INSURANCE = 15;
    case DRIVER_ESCAPE_FROM_ACCIDENT = 16;
    case DRIFTING = 17;
    case LICENSE_TYPE_NOT_ALLOWED = 18;
    case UNKNOWN = 19;

    /**
     * Return translation only if allowed for the client.
     */
    public static function getString($status, $lang = null): string
    {
        if (empty($status)) {
            return '';
        }

        // set language from header
        $lang = $lang ?? request()->header('Accept-Language', 'ar');

        $reason = self::from($status);

        if (!in_array($reason, self::clientReasons(), true)) {
            return '';
        }

        $caseName = Str::lower($reason->name);
        $clientName = config('app.client_name');
        $prefix = ($clientName === 'ART') ? 'art-return-reasons.' : 'return-reasons.';

        return __($prefix . $caseName, [], $lang);
    }

    /**
     * Client-specific mapping.
     */
    public static function clientReasons(): array
    {
        $client = config('app.client_name');

        if ($client === 'ART') {
            return [
                self::MOVE_REVERSE_THE_DIRECTICION,
                self::DRIVE_UNDER_DRUG,
                self::LICENSE_ISSUES,
                self::THE_LICENSE_WAS_EXPIRED,
                self::DRIVER_ESCAPE_FROM_ACCIDENT,
                self::CROSS_WHILE_STOP_LIGHT_IS_RED,
                self::MAKING_THE_ACCIDENT_INTENTIONALLY,
                self::STAIL_THE_CAR,
                self::NO_VALID_INSURANCE,
                self::GIVING_WRONG_INFORMATION_BEFORE_CREATION_OF_POLICY,
                self::NOT_REPORTING_ACCIDENT_TO_INSURANCE_COMPANY,
                self::DRIFTING,
                self::HAVE_MORE_PASSANGER,
                self::DRIVE_UNDER_18,
                self::LICENSE_TYPE_NOT_ALLOWED,
            ];
        }
        return [
            self::USAGE_OUT_POLICY,
            self::HAVE_MORE_PASSANGER,
            self::MOVE_REVERSE_THE_DIRECTICION,
            self::DRIVE_UNDER_DRUG,
            self::DRIVE_UNDER_18,
            self::LICENSE_ISSUES,
            self::DRIVER_NOT_FOUND_IN_ACCIDENT_LOCATION,
            self::CROSS_WHILE_STOP_LIGHT_IS_RED,
            self::GIVING_WRONG_INFORMATION_BEFORE_CREATION_OF_POLICY,
            self::MAKING_THE_ACCIDENT_INTENTIONALLY,
            self::NOT_REPORTING_ACCIDENT_TO_INSURANCE_COMPANY,
            self::STAIL_THE_CAR,
            self::FRODING,
            self::THE_LICENSE_WAS_EXPIRED,
        ];
    }
}

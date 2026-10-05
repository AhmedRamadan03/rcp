<?php

namespace App\ThirdParty\SMS;

interface SmsInterface
{
    public function sendSms($phone_number, $message): array;

    public function isSuccessful($message): bool;
}

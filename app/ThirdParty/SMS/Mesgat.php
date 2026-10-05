<?php

namespace App\ThirdParty\SMS;

use App\Enums\SmsFailureType;
use Illuminate\Support\Facades\Http;

class Mesgat implements SmsInterface
{
    private const BUSINESS_FAILURE_CODES = [
        //'M0002', // Invalid login info // technical failure
        'M0008', // mobile number has an incorrect prefix
        '1120',  // mobile number is not correct / international messaging not enabled
        '1061',  // message duplicated (duplicate/rapid-resend protection) // technical failure
    ];

    private const CODE_DESCRIPTIONS = [
        'M0000' => 'Success',
        'M0001' => 'Variables missing',
        'M0002' => 'Invalid login info',
        'M0022' => 'Exceed number of senders allowed',
        'M0023' => 'Sender Name is active or under activation or refused',
        'M0024' => 'Sender Name should be in English or number',
        'M0025' => 'Invalid Sender Name Length',
        'M0026' => 'Sender Name is already activated or not found',
        'M0027' => 'Activation Code is not Correct',
        'M0029' => 'Invalid Sender Name - Sender Name should contain only letters, numbers and the maximum length should be 11 characters',
        'M0030' => 'Sender Name should ended with AD',
        'M0031' => 'Maximum allowed size of uploaded file is 5 MB',
        'M0032' => 'Only pdf,png,jpg and jpeg files are allowed!',
        'M0033' => 'Sender Type should be normal or whitelist only',
        'M0034' => 'Please Use POST Method',
        'M0036' => 'There is no any sender',
        'M0008' => 'The mobile number has an incorrect prefix',
        'M0037' => 'Please send SMS by statist IP. (If you do not have a static IP, you can disable this feature from your account settings)',
        'M0090' => 'The Message you entered does not match any Template',
        'M0092' => 'There are no templates Exist for this user and this sender',
        'M0040' => 'The numbers exceeded the allowed limit',
        'M0041' => 'No ready templates are currently available',
        '1'     => 'Success',
        '1010'  => 'Variables missing',
        '1020'  => 'Invalid login info',
        '1050'  => 'MSG body is empty',
        '1060'  => 'Balance is not enough',
        '1061'  => 'MSG duplicated',
        '1064'  => 'Free OTP, Invalid MSG content you should use "Pin Code is: xxxx" or "Verification Code: xxxx" or "رمز التحقق: 1234", or upgrade your account and activate your sender to send any content',
        '1110'  => 'Sender name is missing or incorrect',
        '1120'  => 'Mobile numbers is not correct or international messaging is not enabled',
        '1140'  => 'MSG length is too long',
        '400'   => 'Code expired',
        '404'   => 'Code not found',
    ];

    public ?SmsFailureType $failure_type = null;
    public function __construct(public array $values)
    {
    }

    public function sendSms($phone_number, $message): array
    {
        $request = [
            'userName' => $this->values['username'],
            'numbers' => $phone_number,
            'userSender' => $this->values['sender_name'],
            'apiKey' => $this->values['api_key'],
            'msg' => $message,
        ];

        $response = Http::post('https://www.msegat.com/gw/sendsms.php', $request);

        $data = json_decode($response->getBody(), true);

        $this->phone_number = $phone_number;
        $isSuccess = $this->isSuccessfulCode($data['code'] ?? null);
        $this->status = $isSuccess ? 'success' : 'failed';
        $this->status_message = $isSuccess ? null : $this->resolveStatusMessage($data['code'] ?? null, $data['message'] ?? null);
        $this->failure_type = $isSuccess ? null : $this->classifyFailure($data['code'] ?? null);

        return [
            'request' => $request,
            'response' => $data,
        ];
    }

    public function isSuccessful($message): bool
    {
        $code = $message['response']['code'];

        return $this->isSuccessfulCode($code);
    }

    private function isSuccessfulCode($code): bool
    {
        return in_array($code, ['M0000', '1', 1], true);
    }

    private function classifyFailure($code): SmsFailureType
    {
        // Msegat returns some codes as strings ('M0008') and some as
        // bare numbers (1120, 1061) depending on the code family, so
        // compare as strings rather than requiring an exact type match.
        return in_array((string) $code, self::BUSINESS_FAILURE_CODES, true)
            ? SmsFailureType::BUSINESS
            : SmsFailureType::TECHNICAL;
    }

    private function resolveStatusMessage($code, ?string $message): string
    {
        // Msegat sometimes returns an empty "message" field (e.g. code 1061),
        // so fall back to our own description table keyed by code.
        if (! empty($message)) {
            return $message;
        }

        return self::CODE_DESCRIPTIONS[(string) $code] ?? 'Unknown status (code: ' . ($code ?? 'null') . ')';
    }

}
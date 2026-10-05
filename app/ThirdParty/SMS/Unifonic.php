<?php

namespace App\ThirdParty\SMS;

use App\Enums\SmsFailureType;
use Illuminate\Support\Facades\Http;

class Unifonic implements SmsInterface
{
    private const BUSINESS_FAILURE_CODES = [
        'ER-02', // invalid destination number
        'ER-03', // invalid destination number (too long)
        'ER-04', // invalid destination number (too short)
        'ER-05', // no route found for destination number (unreachable/landline)
        'ER-09', // duplicated message (duplicate/rapid-resend protection)
        //'ER-14', // destination is in blacklist // technical failure
    ];

    public ?SmsFailureType $failure_type = null;
    public function __construct(public array $values) {}

    public function sendSms($phone_number, $message): array
    {
        $request = [
            'AppSid' => $this->values['AppSid'],
            'Recipient' => $phone_number,
            'SenderID ' => $this->values['SenderID'],
            'Body' => $message,
            'ResponseType' => 'json,',
            'CorrelationID ' => $this->values['CorrelationID'],
        ];

        $response = Http::withoutVerifying()
            ->withOptions(['verify' => false])
            ->post('https://el.cloud.unifonic.com/rest/SMS/messages', $request);

        $data = json_decode($response->getBody(), true);
        
        $isSuccess = (bool) ($data['success'] ?? false);

        $this->phone_number = $phone_number;
        $this->status_message = '';
        $this->status = $isSuccess ? 'success' : 'failed';
        $this->failure_type = $isSuccess ? null : $this->classifyFailure($data['errorCode'] ?? null);

        return [
            'request' => $request,
            'response' => $data,
        ];
    }

    public function isSuccessful($message): bool
    {
        return $message['response']['success'];
    }

    private function classifyFailure($errorCode): SmsFailureType
    {
        return in_array($errorCode, self::BUSINESS_FAILURE_CODES, true)
            ? SmsFailureType::BUSINESS
            : SmsFailureType::TECHNICAL;
    }

}

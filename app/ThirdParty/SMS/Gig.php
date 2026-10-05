<?php

namespace App\ThirdParty\SMS;

use App\Enums\SmsFailureType;
use Http;

class Gig implements SmsInterface
{
    public ?SmsFailureType $failure_type = null;
    public function __construct(public array $values) {}

    public function sendSms($phone_number, $message): array
    {
        try {
            $accessToken = $this->authenticate();

            $array = $this->sendSingleSms($phone_number, $message, $accessToken);
            $request = $array['request'];
            $response = $array['response'];
            $status = $array['status'];

            if ($status !== 200) {
                $this->phone_number = $phone_number;
                $this->status_message = $response['Exception'];
                $this->status = 'failed';
                $this->failure_type = SmsFailureType::TECHNICAL;
            }

            if ($response['msqStatus'] === 'Failed') {
                $this->phone_number = $phone_number;
                $this->status_message = $response['validationMsg'];
                $this->status = 'failed';
                $this->failure_type = SmsFailureType::BUSINESS;
            } else {
                $this->phone_number = $phone_number;
                $this->status_message = $response['msqStatus'];
                $this->status = 'success';
                $this->failure_type = null;
            }

            return [
                'request' => $request,
                'response' => $response,
            ];

        } catch (\Exception $e) {
            $this->phone_number = $phone_number;
            $this->status_message = $e->getMessage();
            $this->status = 'failed';
            $this->failure_type = SmsFailureType::TECHNICAL;

            return [
                'request' => null,
                'response' => null,
            ];
        }

    }

    public function isSuccessful($message): bool
    {

        return count($message['response']) > 0 && $message['response']['msqStatus'] === 'Queued';
    }

    /**
     * @throws \Exception
     */
    public function authenticate(): string
    {
        $request = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->values['client_id'],
            'client_secret' => $this->values['client_secret'],
        ];

        $response = Http::post($this->values['url'].'invoke/pub.apigateway.oauth2/getAccessToken', $request);

        if ($response->status() == 400) {
            $data = json_decode($response->body(), true);
            throw new \Exception($data['error_description']);
        }

        $data = json_decode($response->getBody(), true);

        return $data['access_token'];

    }

    public function sendSingleSms($phone_number, $message, $token): array
    {
        $request = [
            'msgBody' => $message,
            'recipient' => $phone_number,
        ];
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Source' => $this->values['source'],
        ];
        $response = Http::withHeaders($headers)
            ->post($this->values['url'].'gateway/smsGateway/1.0/sendsinglesms', $request);

        $data = json_decode($response->body(), true);

        return [
            'request' => $request,
            'response' => $data,
            'status' => $response->status(),
        ];

    }
}

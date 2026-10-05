<?php

namespace App\ThirdParty\SMS;

use App\Enums\SmsFailureType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Http\Client\PendingRequest;

class AlRajhiTakful implements SmsInterface
{
    private const USER_AGENT = 'taheiya.sa';

    public ?string $phone_number = null;
    public ?string $status_message = null;
    public ?string $status = null;
    public ?SmsFailureType $failure_type = null;

    public function __construct(public array $values) {}

    public function sendSms($phone_number, $payload): array
    {
        try {
            $authCode = $this->getAuthorizationCode();

            $accessToken = $this->getAccessToken($authCode);

            $array = $this->SendSmsRequest($payload, $accessToken);

            $request = $array['request'];
            $response = $array['response'];
            $status = $array['status'];

            if ($status !== 200) {
                $this->phone_number = $phone_number;
                $this->status_message = $response['Response']['ErrorMessage'] ?? ($response['Error']['Message'] ?? 'Unknown error');
                $this->status = 'failed';
                $this->failure_type = SmsFailureType::TECHNICAL;

                return [
                    'request' => $request,
                    'response' => $response,
                ];
            }

            $item = $response[0] ?? [];

            if (($item['statusCode'] ?? null) !== 200) {
                $this->phone_number = $phone_number;
                $this->status_message = $item['message'] ?? 'Unknown error';
                $this->status = 'failed';
                $this->failure_type = SmsFailureType::BUSINESS;
            } else {
                $this->phone_number = $phone_number;
                $this->status_message = $item['message'] ?? 'Data Processed successfully';
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
        $item = $message['response'][0] ?? [];

        return ($item['statusCode'] ?? null) === 200;
    }

    /**
     * Base URL for the provider, resolved live from config on each call
     * since class constants cannot hold the result of a function call.
     */
    private static function baseUrl(): string
    {
        return config('app.art_sms_provider.base_url');
    }

    /**
     * OAuth redirect URI, resolved live from config for the same reason
     * as baseUrl() above.
     */
    private static function redirectUri(): string
    {
        return config('app.art_sms_provider.redirect_uri');
    }

    /**
     * Base HTTP client shared by all provider requests — carries the
     * required User-Agent header so callers don't repeat it.
     */
    private function client(): PendingRequest
    {
        return Http::withHeader('User-Agent', self::USER_AGENT)
            ->withOptions([
                'handler' => HandlerStack::create(new StreamHandler()),
                'stream_context' => ['socket' => ['bindto' => '0.0.0.0:0']],
            ]);
    }

    /**
     * generate Authorization Code
     *
     * @throws \Exception
     */
    private function getAuthorizationCode(): string
    {
        $response = $this->client()
            ->get(self::baseUrl() . '/dcp-oauth/authorizationcode', [
                'response_type' => 'code',
                'client_id' => $this->values['client_id'],
                'redirect_uri' => self::redirectUri(),
            ]);

        $data = json_decode($response->body(), true);

        if ($response->status() !== 200) {
            throw new \Exception($data['Error']['Message'] ?? 'Failed to obtain authorization code');
        }

        return $data['Authorization_code'];
    }

    /**
     * generate Access Token
     *
     * @throws \Exception
     */
    private function getAccessToken(string $authCode): string
    {
        $credentials = base64_encode($this->values['client_id'] . ':' . $this->values['client_secret']);

        $request = [
            'code' => $authCode,
            'redirect_uri' => self::redirectUri(),
        ];

        $queryParams = http_build_query([
            'client_id' => $this->values['client_id'],
            'grant_type' => 'authorization_code',
        ]);

        $response = $this->client()
            ->asForm()
            ->withHeader('Authorization', 'Basic ' . $credentials)
            ->post(self::baseUrl() . '/dcp-oauth/accesstoken?' . $queryParams, $request);

        $data = json_decode($response->body(), true);

        if ($response->status() !== 200) {
            throw new \Exception($data['Error']['Message'] ?? 'Failed to obtain access token');
        }

        return $data['Access_token'];
    }

    /**
     * send the SMS payload
     */
    private function SendSmsRequest(array $payload, string $token): array
    {
        $request = [$this->buildRequestBody($payload)];

        $response = $this->client()
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Content-Type', 'application/json')
            ->post(self::baseUrl() . '/v1/art/lemnisk/renewal-push', $request);

        $data = json_decode($response->body(), true);

        return [
            'request' => $request,
            'response' => $data,
            'status' => $response->status(),
        ];
    }

    private function buildRequestBody(array $providerPayload): array
    {
        $body = [];

        foreach ($providerPayload['variables'] as $key => $value) {
            Arr::set($body, $key, $value);
        }

        if (Arr::has($body, 'properties') && !Arr::has($body, 'properties.language')) {
            Arr::set($body, 'properties.language', 'en');
        }

        $body['SMSID'] = null;
        $body['eventname'] = $providerPayload['provider_template_code'];
        $body['type'] = 'track';

        return $body;
    }
}
<?php

namespace App\ThirdParty\IVR;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Unifonic implements IvrInterface
{
    public function __construct(public array $values) {}

    public function isSuccessful($response): bool
    {
        // TODO: Implement isSuccessful() method.
    }

    public function call($request)
    {
        $phone = '+966' . substr($request['phone'], -9);

        $messageId = $request['messageId'];

        $referenceId = $request['referenceId'];
        $requestDetails = [
            'recipient' => $phone,
            'type' => 'ivr',
            'callerId' => $this->values['CallerId'],
            'referenceId' => $referenceId,
            'ivr' => [

                'play' => $messageId,
                'loop' => '1',
                'digitsLimit' => '1',
            ],
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'AppsId' => $this->values['AppSid'],
        ])
            ->withBody(json_encode($requestDetails, JSON_UNESCAPED_UNICODE))
            ->post('https://voice.unifonic.com/v1/calls');

        return $response->json();
    }

    public function check($callId)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'AppsId' => $this->values['AppSid'],
        ])->get('https://voice.unifonic.com/v1/providers/voice-call-log/' . $callId);

        return $response->json();
    }
}

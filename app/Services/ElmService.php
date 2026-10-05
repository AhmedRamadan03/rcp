<?php

namespace App\Services;

use App\Enums\SmsFailureType;
use App\Enums\AbsherErrorCode;
use App\Enums\ElmTemplates;
use App\Enums\MessageTemplates;
use App\Enums\SmsStatus;
use App\Exceptions\ElmServiceExceptions;
use App\Models\ElmRequest;
use App\Models\PaymentLink;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class ElmService
{
    public function __construct(public PaymentLink $link) {}

    public function sendFirstMessage($claim)
    {
        $configs = $this->wsdlOptions();

        try {

            $soapClient = new \SoapClient(config('app.elm.wsdl'), $configs);
            return $soapClient->SubmitRequest((object) $this->buildBody());
        } catch (\SoapFault $e) {

            $errorCode = $e->detail?->AbsherNotificationFault?->ErrorCode;
            $code = AbsherErrorCode::tryFrom((string) $errorCode);

            $failureType = $code?->failureType() ?? SmsFailureType::TECHNICAL;

            Mail::to($failureType->recipientEmail())
                ->send(new \App\Mail\FailedMessageMail($claim, $code?->message() ?? 'Something went wrong with elm service.'));
            throw new \Exception(
                $code ? $code->clientMessage() : 'Something went wrong with elm service.'
            );
        } catch (\Exception $e) {
            Mail::to(SmsFailureType::TECHNICAL->recipientEmail())
                ->send(new \App\Mail\FailedMessageMail($claim, 'Unable to connect to ELM service.'));
            throw new \Exception('Unable to connect to ELM service.');
        }
    }

    public function getStatus(ElmRequest $request)
    {
        $configs = $this->wsdlOptions();
        $soapClient = new \SoapClient(config('app.elm.wsdl'), $this->wsdlOptions());
        $response = $soapClient->GetStatus($this->statusRequest($request->batch_number));

        $request->batch_identifier = $response->StatusResult->BatchIdentifier;

        try {
            $response2 = $soapClient->GetDetailedStatus($this->detailedStatusRequest($request->batch_identifier));

            return $response2;
        } catch (\Throwable $e) {
            ElmServiceExceptions::notImplemented();
        }
    }

    public function createElmRequest($response): ElmRequest
    {
        $claim = $this->link->payment->claim;

        return ElmRequest::create([
            'batch_number' => $response?->BatchNumber,
            'claim_id'     => $claim->id,
            'national_id'  => $claim->debtor_nid,
            'link'         => $this->link->link,
            'message_id' => $response?->elm_message_id,
        ]);
    }

    public function sendElmMessage($message_body)
    {
        $claim  = $this->link->payment->claim;
        $elmMessageStatus = $claim->is_msg_skip ? SmsStatus::SKIPPED : SmsStatus::PENDING;

        $elm_message = MessageService::store([
            'claim_id' => $claim->id,
            'body' => $message_body,
            'send_date' => Carbon::now(),
            'status' => $elmMessageStatus,
            'status_message' => $elmMessageStatus,
            'message_status' => $elmMessageStatus,
            'request' => null,
            'response' => null,
            'phone_number' => null,
            'is_elm_message' => true,
            'national_id' => $claim->debtor_nid,
            'template_id' => MessageTemplates::APPROVE->value,
            'payment_link_id' => $this->link->id,
        ]);

        if ($claim->is_msg_skip) {
            return;
        }

        // send first message
        $response = $this->sendFirstMessage($claim);

        // pass elm message id to elm request
        $response->elm_message_id = $elm_message->id;

        $elmRequest = $this->createElmRequest($response);

        $elm_request_data = [
            'id' => $elmRequest->id,
            'claim_id' => $elmRequest->claim_id,
            'batch_number' => $elmRequest->batch_number,
            'status' => $elmRequest->status,
            'batch_identifier' => $elmRequest->batch_identifier,
            'national_id' => $elmRequest->national_id,
            'sms_description' => $elmRequest->sms_description,
        ];

        // Update message with real request data
        $elm_message->update([
            'request' => json_encode($elm_request_data),
        ]);
    }

    private function buildBody(): array
    {
        $uniqueCode = rand(100000, 999999);
        $link = $this->link?->payment?->link ??$this->link->link;
        $url = config('app.frontend_url') . '/claim-info/' . $link; // $this->link->link;
        return [
            'ClientId' => config('app.elm.client_id'),
            'ClientAuthorization' => config('app.elm.authorization'),
            'TemplateId' => ElmTemplates::getTemplateId(ElmTemplates::SUBMIT_REQUEST->value),
            'Recipients' => [
                'Recipient' => [
                    'NationalOrIqamaId' => $this->link->payment->claim->debtor_nid,
                    'Language' => 'AR',
                    'Params' => [
                        'Param' => [
                            ['Name' => 'VAR01', 'Value' => $this->link->payment->claim->claim_number],
                            ['Name' => 'VAR02', 'Value' => $this->link->payment->claim->amount],
                            ['Name' => 'VAR03', 'Value' => $url],
                        ],
                    ],
                ],
            ],

        ];
    }

    private function wsdlOptions(): array
    {
        $wsdl = config('app.elm.wsdl');
        $endpoint = config('app.elm.url');
        $certificate = config('app.elm.certificate_path');

        $password = config('app.elm.password');

        return [
            'location' => $endpoint,
            'keep_alive' => true,
            'trace' => true,
            'local_cert' => $certificate,
            'passphrase' => $password,
            'cache_wsdl' => 0, // WSDL_CACHE_NONE
        ];
    }

    private function statusRequest($batchNumber): array
    {
        return [
            'ClientId' => config('app.elm.client_id'),
            'ClientAuthorization' => config('app.elm.authorization'),
            'BatchNumber' => $batchNumber,

        ];
    }

    private function detailedStatusRequest($batchIdentifier): array
    {
        return [
            'ClientId' => config('app.elm.client_id'),
            'ClientAuthorization' => config('app.elm.authorization'),
            'BatchIdentifier' => $batchIdentifier,

        ];
    }

    public function buildMessageFromTemplate(): ?string
    {
        $tmpId = ElmTemplates::getTemplateId(ElmTemplates::SUBMIT_REQUEST->value);
        $template = ElmTemplates::getTemplateText($tmpId);

        if (!$template) {
            return null; // التمبلت مش TMPL-03 ولا TMPL-04
        }

        $claim = $this->link->payment->claim;
        $link = $this->link?->payment?->link ??$this->link->link;
        $url = config('app.frontend_url') . '/claim-info/' . $link;

        $values = [
            'VAR01' => $claim->claim_number,
            'VAR02' => $claim->amount,
            'VAR03' => $url,
            'VAR04' => $claim->claim_number,
        ];

        foreach (ElmTemplates::getTemplateVarsMap($tmpId) as $var) {
            $template = str_replace('#' . $var, $values[$var], $template);
        }

        return $template;
    }
}

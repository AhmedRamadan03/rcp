<?php

namespace App\Services;

use App\Enums\SmsFailureType;
use App\Enums\ResendMessageType;
use App\Enums\SmsStatus;
use App\Models\Claim;
use App\Models\CustomTemplate;
use App\Models\Message;
use App\Models\ThirdParty;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class SmsService
{
    public function sendSms($phone_number, $message, $claim_id, $template_id = null,  $payment_link_id = null, $is_manual = false, $template_model_id = null)
    {
        $claim = Claim::find($claim_id);


        $customTemplate = $template_id
            ? CustomTemplate::with('thirdParty')
                ->where('template_id', $template_id)
                ->where('is_active', '1')
                ->first()
            : null;

        $activeSmsThirdParty = $customTemplate?->thirdParty
            ?? SettingsService::getActiveThirdParty('sms');

        $messageData = [
            'claim_id' => $claim_id,
            'body' => $message,
            'send_date' => Carbon::now(),
            'phone_number' => $phone_number,
            'template_id' => $template_id,
            'template_model_id' => $template_model_id,
            'payment_link_id' => $payment_link_id,
        ];


        if ($claim?->is_msg_skip && !$is_manual) {
            $messageData['status'] = SmsStatus::SKIPPED->value;
            $messageData['status_message'] = SmsStatus::SKIPPED->value;
            $messageData['message_status'] = SmsStatus::SKIPPED->value;
            return MessageService::store($messageData);
        }

        if (!$activeSmsThirdParty || !$activeSmsThirdParty->is_active) {
            $messageData['status'] = SmsStatus::PENDING_TO_HAVE_PROVIDER->value;
            $messageData['is_elm_message'] = false;
            return MessageService::store($messageData);
        }


        $className = "App\\ThirdParty\\SMS\\" . ucfirst($activeSmsThirdParty->vendor_name);

        if (!class_exists($className)) {
            throw new \RuntimeException("Class {$className} does not exist.");
        }

        // create the message record before routing to a provider
        $messageData['status'] = SmsStatus::PENDING->value;
        $storedMessage = MessageService::store($messageData);

        if ($activeSmsThirdParty->isApiProvider()) {
            return $this->sendSmsViaApiProvider(
                $storedMessage,
                $activeSmsThirdParty,
                $className,
                $message,
                $phone_number,
                $claim,
                $template_id,
                $template_model_id
            );
        }

        return $this->sendSmsViaInternal(
            $storedMessage,
            $activeSmsThirdParty,
            $className,
            $message,
            $phone_number,
            $claim
        );
    }

    private function sendSmsViaInternal(Message $storedMessage, ThirdParty $thirdParty, string $className, $message, $phone_number, ?Claim $claim)
    {
        $class = new $className($thirdParty->values);
        $request_response = $class->sendSms($phone_number, $message);

        $storedMessage->update([
            'status' => $class->isSuccessful($request_response) ? SmsStatus::SENDED->value : SmsStatus::NO_SENDED->value,
            'status_message' => $class->status_message,
            'message_status' => $class->status,
            'request' => $request_response['request'],
            'response' => $request_response['response'],
            'phone_number' => $class->phone_number,
            'sent_count' => $storedMessage->sent_count + 1,
        ]);

        if ($class->status === 'failed') {
            $failureType = $class->failure_type ?? SmsFailureType::TECHNICAL;

            Mail::to($failureType->recipientEmail())
                ->send(new \App\Mail\FailedMessageMail($claim, $class->status_message));
        }

        return $storedMessage->fresh();
    }

    private function sendSmsViaApiProvider(Message $storedMessage, ThirdParty $thirdParty, string $className, $message, $phone_number, ?Claim $claim, $template_id, $template_model_id = null)
    {
        $payload = $template_model_id
            ? app(MessageService::class)->buildProviderPayload($template_id, $template_model_id)
            : ['provider_template_code' => null, 'variables' => []];

        $provider = new $className($thirdParty->values);

        $request_response = $provider->sendSms(
            $phone_number,
            $payload
        );

        $storedMessage->update([
            'status' => $provider->isSuccessful($request_response) ? SmsStatus::SENDED->value : SmsStatus::NO_SENDED->value,
            'status_message' => $provider->status_message,
            'message_status' => $provider->status,
            'request' => $request_response['request'],
            'response' => $request_response['response'],
            'phone_number' => $phone_number,
            'sent_count' => $storedMessage->sent_count + 1,
        ]);

        if ($provider->status === 'failed') {
            $failureType = $provider->failure_type ?? SmsFailureType::TECHNICAL;

            Mail::to($failureType->recipientEmail())
                ->send(new \App\Mail\FailedMessageMail($claim, $provider->status_message));
        }

        return $storedMessage->fresh();
    }

    public function reSendSms(int $message_id, string $type = ResendMessageType::CURRENT_MSG->value)
    {
        $message = Message::findOrFail($message_id);
        $message_template_id = $message->template_id;
        $template_model_id = $message->template_model_id ?? null;
        $claim = $message->claim;
        if ($type == ResendMessageType::CURRENT_MSG->value) {
            $messageBody =  $message->body;
        } else {
            if (! $message_template_id) {
                throw new \Exception('Cannot resend with template: message has no template');
            }

            if ($template_model_id === null || $template_model_id === '') {
                throw new \Exception(
                    'Cannot resend with template: this message has no template model reference. Resend the current message instead.'
                );
            }

            $messageService = new MessageService;

            $messageBody = $messageService->convertTemplate($message_template_id, $template_model_id);
        }

        // handle elm messages

        if($message->is_elm_message){
            // if the message is an elm message and there is a valid phone number
            if(!empty($claim->debtor_phone)){
                return $this->sendSms(
                    $claim->debtor_phone,
                   $messageBody,
                    $message->claim_id,
                    $message_template_id,
                    $message->payment_link_id,
                    true,
                    $template_model_id
                );
            }

            // if there is no deptor phone number
            return (new ElmService($message->paymentLink))->sendElmMessage($messageBody);
        }

        return $this->sendSms(
            $claim->debtor_phone,
            $messageBody,
            $message->claim_id,
            $message_template_id,
            $message->payment_link_id,
            true,
            $template_model_id
        );
    }

    public function payment($payment_link)
    {

        $className = $this->returnClassName('payment');

        return $className->pay($payment_link);
    }

    public function returnClassName($type)
    {
        $service = SettingsService::getActiveThirdParty($type);

        if (!$service) {
            throw new \RuntimeException("No active {$type} provider configured.");
        }

        $className = "App\ThirdParty\\" . strtoupper($type) . '\\' . (ucfirst($service->vendor_name));

        if (!class_exists($className)) {
            throw new \RuntimeException("Class {$className} does not exist.");
        }

        $class = new $className($service->values);

        return $class;
    }

    public function ivr($payment_link, $messageId)
    {
        $service = SettingsService::getActiveThirdParty('ivr');
        if (!empty($messageId) && $service && $payment_link->payment?->claim?->is_msg_skip === false) {
            $className = $this->returnClassName('ivr');
            # code...

            $referenceId = $payment_link->link;

            $data = [
                'phone' => $payment_link->payment->claim->debtor_phone,
                'messageId' => $messageId,
                'referenceId' => $referenceId,
            ];

            return $className->call($data);
        } else {
            return ['error' => true];
        }
    }

}
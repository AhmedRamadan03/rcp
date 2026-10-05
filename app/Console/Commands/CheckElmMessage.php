<?php

namespace App\Console\Commands;

use App\Enums\SmsFailureType;
use App\Enums\SmsStatus;
use App\Models\ElmRequest;
use App\Services\ElmService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CheckElmMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check-elm-message';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $elmRequests = ElmRequest::whereNull('status')->get();

        foreach ($elmRequests as $elmRequest) {
            if ($elmRequest->claim?->is_msg_skip) {
                $elmRequest->status = SmsStatus::SKIPPED->value;
                $elmRequest->save();
            } else {
                # code...
                $service = new ElmService($elmRequest->claim->payments()->first()->links()->first());

                $response = $service->getStatus($elmRequest);
                $recipient = $response->StatusDetailResult->Recipients->Recipient;
                $isSuccess = $recipient->Status === '0';

                $elmRequest->batch_identifier = $response->StatusDetailResult->BatchIdentifier;
                $elmRequest->status = $isSuccess ? 'success' : 'failure';
                $elmRequest->sms_description = $recipient->StatusDescription;
                $elmRequest->save();

                $elm_request_data = [
                    'id' => $elmRequest->id,
                    'claim_id' => $elmRequest->claim_id,
                    'batch_number' => $elmRequest->batch_number,
                    'status' => $elmRequest->status,
                    'batch_identifier' => $elmRequest->batch_identifier,
                    'national_id' => $elmRequest->national_id,
                    'sms_description' => $elmRequest->sms_description,
                ];

                // Update the existing message
                \App\Models\Message::where('id', $elmRequest->message_id)
                    ->update([
                        'status' => $isSuccess ? SmsStatus::SENDED->value : SmsStatus::NO_SENDED->value,
                        'message_status' => $isSuccess ? 'success' : 'failed',
                        'status_message' => $isSuccess ? null : $recipient->StatusDescription,
                        'sent_count' => DB::raw('sent_count + 1'),
                        'request'=> json_encode($elm_request_data),
                        'response' => json_encode($response),
                    ]);

                    if ($elmRequest->status === 'failure') {
                    Mail::to(SmsFailureType::TECHNICAL->recipientEmail())
                        ->send(new \App\Mail\FailedMessageMail($elmRequest->claim, $recipient->StatusDescription));
                    }
            }
        }
    }
}

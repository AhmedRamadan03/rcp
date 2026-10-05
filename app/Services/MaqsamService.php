<?php

namespace App\Services;

use App\Models\MaqsamCallback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class MaqsamService
{
    // API base URL
    private $apiUrl = 'https://api.maqsam.com/v1/inquiries';

    // Authentication credentials
    private $accessKeyId;

    private $accessSecret;

    public function __construct()
    {
        $this->accessKeyId = config('app.maqsam.maqsam_access_key_id') ?? 'FXNBuNGRA3nxFVeju4L1';
        $this->accessSecret = config('app.maqsam.maqsam_access_secret') ?? 'LQM5Hh4KUOgwr3OpiVUt';
    }

    /**
     * Make an inquiry request to the Maqsam API.
     *
     * @param  array  $data
     * @return \Illuminate\Http\Client\Response
     */
    public function inquiry($data)
    {
        // Set up the authentication header
        $auth = base64_encode($this->accessKeyId.':'.$this->accessSecret);

        $timeoutPeriod = $this->getIvrDuration($data['InquiryUrl']);

        // Prepare the request data
        $payload = [
            'To' => $data['To'],
            'InquiryUrl' => $data['InquiryUrl'] ?? config('app.maqsam.maqsam_inquiry_url'),
            'MaxTrials' => config('app.maqsam.maqsam_max_trials') ?? 3,
            'RetryDelay' => config('app.maqsam.maqsam_retry_delay') ?? 0,
            'CallbackUrl' => config('app.maqsam.maqsam_callback_url') ?? null,
            'ValidEntries' => config('app.maqsam.maqsam_valid_entries') ?? '0,1,2,3,4,5,6,7,8,9,*,#',
            'ValidEntriesUrls' => config('app.maqsam.maqsam_valid_entries_urls') ?? null,
            'InvalidEntryUrl' => config('app.maqsam.maqsam_invalid_entry_url') ?? null,
            'TimeoutPeriod' => $timeoutPeriod ?? config('app.maqsam.maqsam_timeout_period') ?? 600,
            'TimeoutUrl' => config('app.maqsam.maqsam_timeout_url') ?? null,
            'RecordMessageEntries' => config('app.maqsam.maqsam_record_message_entries') ?? null,
            'MaxRecordingLength' => config('app.maqsam.maqsam_max_recording_length') ?? 60,
        ];

        // Filter out null values
        $payload = array_filter($payload, function ($value) {
            return ! is_null($value);
        });

        try {
            // Send POST request
            $response = Http::withHeaders([
                'Authorization' => 'Basic '.$auth,
            ])
                ->asForm()
                ->post($this->apiUrl, $payload);

            return $response;
        } catch (\Exception $e) {
            Log::error('Maqsam Inquiry API error: '.$e->getMessage());

            return null;
        }
    }

    public function handleCallback(Request $request)
    {
        // Validate the incoming callback data
        $request->validate([
            'InquiryId' => 'required|string',
            'Event' => 'required|string',
            'FromState' => 'required|string',
            'ToState' => 'required|string',
            'Trials' => 'required|integer',
            'UnreachableReason' => 'nullable|string',
            'Length' => 'nullable|integer',
            'Result' => 'nullable|string',
            'Entries' => 'nullable|string',
            'RecordingUrl' => 'nullable|url',
        ]);

        // Log the incoming callback data to the Laravel log
//        Log::info('Maqsam Callback Received:', $request->all());

        // Store the callback data in the database
        MaqsamCallback::updateOrCreate(
            ['InquiryId' => $request->input('InquiryId')],
            $request->all()
        );

        // If the event is "terminated", log it as a final callback
        if ($request->input('ToState') === 'terminated') {
//            Log::info('Final Callback - Terminated:', $request->all());
            MaqsamCallback::updateOrCreate(
                ['InquiryId' => $request->input('InquiryId')],
                $request->all()
            );
        }
    }

    public function getIvrDuration($path) {
        $media = FFMpeg::openUrl($path);

        $durationInSeconds = $media->getDurationInSeconds() + 1; // returns an int

        FFMpeg::cleanupTemporaryFiles();

        return $durationInSeconds;
    }

    public function getStatus($states)
    {
        if (collect($states)->contains('unreachable') && collect($states)->contains('awaiting-connect') && collect($states)->contains('busy')) {
            $status = 'busy';
        } else if(collect($states)->contains('unreachable') && collect($states)->contains('awaiting-connect') && collect($states)->contains('no-answer')){
            $status = 'no-answer';
        } else if(collect($states)->contains('unreachable') && collect($states)->contains('awaiting-connect') && collect($states)->contains('failed')){
            $status = 'failed';
        } else if(collect($states)->contains('timeout') && collect($states)->contains('awaiting-entry')){
            $status = 'completed';
        } else if(collect($states)->contains('hangup') && collect($states)->contains('closing')){
            $status = 'completed';
        } else {
            $status = 'un-known';
        }

        return $status;
    }
}

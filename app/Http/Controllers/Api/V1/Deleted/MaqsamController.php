<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Services\MaqsamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MaqsamController extends Controller
{
    protected $maqsamService;

    public function __construct(MaqsamService $maqsamService)
    {
        $this->maqsamService = $maqsamService;
    }

    public function createInquiry(Request $request)
    {
        // Validate incoming request data
        $validator = Validator::make($request->all(), [
            'To' => 'required|regex:/^\+?[1-9]\d{1,14}$/', // International phone format
            'InquiryUrl' => 'required|url',
            'MaxTrials' => 'nullable|integer|min:1|max:10',
            'RetryDelay' => 'nullable|integer|min:0|max:600',
            'CallbackUrl' => 'nullable|url',
            'ValidEntries' => 'nullable|string',
            'ValidEntriesUrls' => 'nullable|string',
            'InvalidEntryUrl' => 'nullable|url',
            'TimeoutPeriod' => 'nullable|integer|min:1|max:3600',
            'TimeoutUrl' => 'nullable|url',
            'RecordMessageEntries' => 'nullable|string',
            'MaxRecordingLength' => 'nullable|integer|min:1|max:600',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Prepare data to send to the service

        $data = $request->only([
            'To', 'InquiryUrl', 'MaxTrials', 'RetryDelay', 'CallbackUrl', 'ValidEntries',
            'ValidEntriesUrls', 'InvalidEntryUrl', 'TimeoutPeriod', 'TimeoutUrl',
            'RecordMessageEntries', 'MaxRecordingLength',
        ]);

        // Make the inquiry API call
        $response = $this->maqsamService->inquiry($data);

        if ($response && $response->successful()) {
            return response()->json(['message' => 'Inquiry created successfully', 'data' => $response->json()], 200);
        } else {
            return response()->json(['error' => 'Failed to create inquiry'], 500);
        }
    }

    public function handleCallback(Request $request)
    {
        // Delegate the callback handling to the service
        $this->maqsamService->handleCallback($request);

        // Return a response acknowledging receipt of the callback
        return response()->json(['message' => 'Callback received and stored successfully'], 200);
    }
}

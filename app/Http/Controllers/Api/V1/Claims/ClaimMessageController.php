<?php

namespace App\Http\Controllers\Api\V1\Claims;


use App\BusinessLogic\PaymentReminder\PaymentReminder;
use App\Models\Claim;
use App\Models\Message;

use App\Services\AssigningService;
use App\Services\ClaimService;
use App\Services\IvrService;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\SmsService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Traits\ApiResponse;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Http\Resources\Api\V1\MessageResource;

class ClaimMessageController extends Controller
{
    use ApiResponse;
    public function __construct(
        public ClaimService $claimService,
        public AssigningService $assigningService,
        public NotificationService $notificationService,
        public MessageService $messageService,
        public IvrService $ivrService,
        public SmsService $smsService
    ) {
    }


    public function claimMessages(Request $request, $claimId)
    {

        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1',
        ]);

        $perPage = $validated['per_page'] ?? 10;

        $messages = Message::where('claim_id', $claimId)
            ->orderBy('send_date', 'asc')
            ->paginate($perPage);


        return response()->json(
            new Collection($messages, 'Claim Messages information', MessageResource::class)
        );

    }

    /**
     * Re-send SMS message.
     */
    public function reSend(Request $request, $claimId, $messageId)
    {
        try {
            $validatedData = $request->validate([
                'type' => "required|in:".implode(',', array_map(fn($status) => $status->value, \App\Enums\ResendMessageType::cases())),
            ]);
            $message = Message::where('id', $messageId)
                ->where('claim_id', $claimId)
                ->firstOrFail();

            $this->smsService->reSendSms($message->id,$validatedData['type']);

            return response()->json([
                'success' => true,
                'message' => 'Message resent successfully',
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found',
            ], 404);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend message ' . $e->getMessage(),
            ], 422);
        }
    }

    public function paymentReminder($claimId)
    {
        try {
            $claim = Claim::findOrFail($claimId);

            if ($claim->payment_reminders_is_disabled) {
                (new PaymentReminder)->activate($claimId);
            } else {
                (new PaymentReminder)->deactivate($claimId);
            }

            return response()->json([
            'success' => true,
            'message' => 'Payment reminder updated successfully',
            'payment_reminders_is_disabled' => $claim->payment_reminders_is_disabled,
        ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Claim not found',
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }


}
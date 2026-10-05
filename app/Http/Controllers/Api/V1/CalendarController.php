<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LinkSendStatus;
use App\Enums\PaymentType;
use App\Enums\Roles;
use App\Http\Helpers\ApiHelper;
use App\Http\Resources\CalendarPaymentLinkResource;
use App\Models\ClaimComment;
use App\Models\PaymentLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CalendarController extends Controller
{
    /**
     * Get calendar data including payment links and reminders
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $startMonth = $request->input('startMonth') ?? now()->startOfMonth()->startOfDay()->toDateString();
        $endMonth = $request->input('endMonth') ?? now()->endOfMonth()->endOfDay()->toDateString();
        $collectorId = $request->input('collector_id');
        if (auth()->user()->role_id == Roles::SENIOR_COLLECTOR->value) {
            $collectorIds = User::where('supervisor_id', auth()->user()->id)->pluck('id')->toArray();
            $collectorId = array_merge((array)$collectorId, $collectorIds);
            $collectorId = array_merge($collectorId, [auth()->user()->id]);
        }
        if (!$collectorId) {
            $collectorId = (array) $collectorId; // cast to array
        }
        // Get payment links data

        $claims = $this->getUserClaims($collectorId, $startMonth, $endMonth);

        // Get reminders data
        $reminders = $this->getUserReminders($collectorId, $startMonth, $endMonth);
        // Get collectors data

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => [
                'claims' => $claims,
                'reminders' => $reminders,
            ]
        ]);
    }

    /**
     * Mark reminders as seen or unseen.
     *
     * @param Request $request
     * @param int|null $reminderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleReminderStatus($reminderId): \Illuminate\Http\JsonResponse
    {
        $reminder = ClaimComment::find($reminderId);

        if (!$reminder) {
            return response()->json([
                'success' => false,
                'message' => 'Reminder not found'
            ], 404);
        }

        if (!$reminder->seen) {
            $reminder->seen = true;
        }

        $reminder->save();

        return response()->json([
            'success' => true,
            'message' => "Reminder marked as " . ($reminder->seen ? 'seen' : 'unseen') . " successfully"
        ]);
    }

    private function getUserClaims($collectorId, $startMonth, $endMonth)
    {

        $claims = PaymentLink::with('payment.claim')
            ->where('send_status', '!=', LinkSendStatus::CANCELED->value)
            ->whereBetween('due_date', [$startMonth, $endMonth])
            ->when(ApiHelper::isCollectorOrExternal(), function ($query) {
                $query->where(function ($query) {
                    ApiHelper::applyAssigneeOrDelegationFilter($query, [auth()->user()->id], 'payment.claim.assignees');
                });
            })
            ->when($collectorId, function ($query) use ($collectorId) {
                $query->whereHas('payment.claim.assignees', function ($q) use ($collectorId) {
                    $q->whereIn('to', $collectorId)->where('is_active', 1);
                });
            })
            ->when(request()->filled('status_filter'), function ($query) {
                $statuses = (array) request('status_filter'); // ممكن يكون string أو array
                $query->where(function ($q) use ($statuses) {
                    foreach ($statuses as $status) {
                        switch ($status) {
                            case 'scheduled_partial':
                                $q->orWhere(function ($q2) {
                                    $q2->where('type', PaymentType::INSTALLMENT_PAYMENT->value)
                                        ->where('send_status', LinkSendStatus::NOT_SENT->value);
                                });
                                break;
                            case 'sent_partial':
                                $q->orWhere(function ($q2) {
                                    $q2->where('type', PaymentType::INSTALLMENT_PAYMENT->value)
                                        ->where('send_status', LinkSendStatus::SENT->value);
                                });
                                break;
                            case 'scheduled_delay':
                                $q->orWhere(function ($q2) {
                                    $q2->where('type', PaymentType::DELAY_PAYMENT->value)
                                        ->where('send_status', LinkSendStatus::NOT_SENT->value);
                                });
                                break;
                            case 'sent_delay':
                                $q->orWhere(function ($q2) {
                                    $q2->where('type', PaymentType::DELAY_PAYMENT->value)
                                        ->where('send_status', LinkSendStatus::SENT->value);
                                });
                                break;
                            case 'paid':
                                $q->orWhere('send_status', LinkSendStatus::PAID->value);
                                break;
                            case 'notification':
                                // شرط 1st Notification Text
                                break;
                            case 'reminder':
                                // شرط Reminder
                                break;
                        }
                    }
                });
            })
            ->get();


        return CalendarPaymentLinkResource::collection($claims);
    }



    private function getUserReminders($collectorIds, $startMonth, $endMonth)
    {
        return ClaimComment::with('claim')
            ->whereNotNull('reminder_date')
            ->whereBetween('reminder_date', [$startMonth, $endMonth])
            ->when(ApiHelper::isCollectorOrExternal(), function ($query) {
                $query->where(function ($query) {
                    ApiHelper::applyAssigneeOrDelegationFilter($query, [auth()->user()->id], 'claim.assignees');
                });
            })
            ->when($collectorIds, function ($query) use ($collectorIds) {
                $query->where(function ($query) use ($collectorIds) {
                    ApiHelper::applyAssigneeOrDelegationFilter($query, $collectorIds, 'claim.assignees');
                });
            })
            ->get();
    }
    private function getUserCollectors()
    {
        if (auth()->user()->delegationsTo()->count() > 0) {
            $collectors = auth()->user()->delegationsTo()->where('is_active', 1)->get()->pluck('fromUser')->unique();
            $collectors->push(auth()->user());
        } else {
            $collectors = User::whereIn('role_id', [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value, Roles::LEGAL_DEPARTMENT->value])->get();
        }

        return $collectors;
    }
}

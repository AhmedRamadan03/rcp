<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Enums\Roles;
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
        $collectorId = $request->input('collector_id', 'all');

        // Get payment links data
        $claims = CalendarPaymentLinkResource::collection(
            PaymentLink::with('payment.claim')
                ->when(in_array(auth()->user()->role_id, [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value]), function ($query) {
                    $query->where(function ($query) {
                        $query->orWhereHas('payment.claim.assignees', function ($query) {
                            $query->where('to', auth()->user()->id)
                                ->where('is_active', 1);
                        })->orWhereHas('payment.claim.assignees', function ($query) {
                            $query->where('is_active', 1)
                                ->whereHas('toUser.delegationsFrom', function ($query) {
                                    $query->where('to', auth()->user()->id)
                                        ->where('is_active', 1);
                                });
                        });
                    });
                })
                ->when($collectorId != 'all', function ($query) use ($collectorId) {
                    $query->whereHas('payment.claim.assignees', function ($query) use ($collectorId) {
                        $query->where('to', $collectorId)
                            ->where('is_active', 1);
                    });
                })
                ->get()
        );

        // Get reminders data
        $reminders = ClaimComment::with('claim')
            ->where('reminder_date', '!=', null)
            ->when(in_array(auth()->user()->role_id, [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value]), function ($query) {
                $query->where(function ($query) {
                    $query->orWhereHas('claim.assignees', function ($query) {
                        $query->where('to', auth()->user()->id)
                            ->where('is_active', 1);
                    })
                        ->orWhere(function ($delegation_query) {
                            $delegation_query->whereHas('claim.assignees', function ($query) {
                                $query->where('is_active', 1)
                                    ->whereHas('toUser.delegationsFrom', function ($query) {
                                        $query->where('to', auth()->user()->id)
                                            ->where('is_active', 1);
                                    });
                            });
                        });
                });
            })
            ->when($collectorId != 'all', function ($query) use ($collectorId) {
                $query->where(function ($query) use ($collectorId) {
                    $query->orWhereHas('claim.assignees', function ($query) use ($collectorId) {
                        $query->where('to', $collectorId)
                            ->where('is_active', 1);
                    })
                        ->orWhere(function ($delegation_query) use ($collectorId) {
                            $delegation_query->whereHas('claim.assignees', function ($query) use ($collectorId) {
                                $query->where('is_active', 1)
                                    ->whereHas('toUser.delegationsFrom', function ($query) use ($collectorId) {
                                        $query->where('to', $collectorId)
                                            ->where('is_active', 1);
                                    });
                            });
                        });
                });
            })
            ->get();

        // Get collectors data
        if (auth()->user()->delegationsTo()->count() > 0) {
            $collectors = auth()->user()->delegationsTo()->where('is_active', 1)->get()->pluck('fromUser')->unique();
            $collectors->push(auth()->user());
        } else {
            $collectors = User::whereIn('role_id', [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value, Roles::LEGAL_DEPARTMENT->value])->get();
        }

        return response()->json([
            'error' => false,
            'message' => 'Calendar data retrieved successfully',
            'data' => [
                'claims' => $claims,
                'reminders' => $reminders,
                'collectors' => $collectors
            ]
        ]);
    }

    /**
     * Mark a reminder as seen
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function makeReminderSeen(Request $request)
    {
        $id = $request->input('id');

        if (!$id) {
            return response()->json([
                'error' => true,
                'message' => 'Reminder ID is required'
            ], 400);
        }

        ClaimComment::where('id', $id)->update(['seen' => true]);

        return response()->json([
            'error' => false,
            'message' => 'Reminder marked as seen successfully'
        ]);
    }
}

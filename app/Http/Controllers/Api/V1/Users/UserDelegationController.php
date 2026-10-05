<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserDelegationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function delegate(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'delegate_to' => [
                'required',
                'exists:users,id',
                'different:user_id',
                function ($attribute, $value, $fail) use ($request) {
                    $user = User::find($request->user_id);
                    $delegateTo = User::find($value);

                    if ($user->isAdmin() || $delegateTo->isAdmin()) {
                        $fail('Cannot delegate to or from admin users.');
                    }
                },
            ],
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'reason' => 'required|string|max:1000',
            'notify_users' => 'nullable|boolean',
            'claims' => 'nullable|array',
            'claims.*' => 'exists:claims,id',
        ]);

        try {
            DB::beginTransaction();

            $user = User::findOrFail($validated['user_id']);
            $delegateTo = User::findOrFail($validated['delegate_to']);

            // Create delegation record
            $delegation = $user->delegations()->create([
                'delegated_to' => $delegateTo->id,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'reason' => $validated['reason'],
                'status' => 'active',
            ]);

            // Delegate claims if any
            if (!empty($validated['claims'])) {
                $claims = Claim::whereIn('id', $validated['claims'])
                    ->where('assigned_to', $user->id)
                    ->get();

                foreach ($claims as $claim) {
                    $claim->update(['assigned_to' => $delegateTo->id]);

                    // Record claim delegation history
                    $claim->delegations()->create([
                        'from_user_id' => $user->id,
                        'to_user_id' => $delegateTo->id,
                        'delegation_id' => $delegation->id,
                    ]);
                }
            }

            // Send notifications if requested
            if ($validated['notify_users'] ?? false) {
                $this->sendDelegationNotifications($user, $delegateTo, $delegation, $validated);
            }

            DB::commit();

            return response()->json([
                "succes" => true,
                'message' => 'Delegation created successfully',
                'data' => [
                    'delegation_id' => $delegation->id,
                    'delegated_claims_count' => $claims->count() ?? 0,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "succes" => false,
                'message' => 'Failed to create delegation: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function listDelegations($userId, Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:active,pending,expired,cancelled',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = User::findOrFail($userId);
        $query = $user->with(['delegationTo'])
            ->latest();

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        $perPage = $request->input('per_page', 15);
        $delegations = $query->paginate($perPage);

        return response()->json([
            "succes" => true,
            'message' => 'User delegations retrieved successfully',
            'data' => $delegations
        ]);
    }

    protected function sendDelegationNotifications($user, $delegateTo, $delegation, $data)
    {
        // Notification to the user being delegated from
        $this->notificationService->create([
            'title_ar' => 'تم تفويض بعض المطالبات الخاصة بك',
            'title_en' => 'Some of your claims have been delegated',
            'description_ar' => 'تم تفويض بعض المطالبات الخاصة بك إلى ' . $delegateTo->name . ' حتى ' . $delegation->end_date->format('Y-m-d'),
            'description_en' => 'Some of your claims have been delegated to ' . $delegateTo->name . ' until ' . $delegation->end_date->format('Y-m-d'),
            'regarding' => NotificationType::DELEGATION_CREATED->value,
            'to' => json_encode([$user->id]),
            'links' => json_encode([
                [
                    'label_ar' => 'عرض التفويض',
                    'label_en' => 'View Delegation',
                    'url' => 'delegations/' . $delegation->id,
                ],
            ]),
        ]);

        // Notification to the user being delegated to
        $this->notificationService->create([
            'title_ar' => 'تم تفويضك بمطالبات',
            'title_en' => 'You have been delegated claims',
            'description_ar' => 'تم تفويضك بمطالبات من ' . $user->name . ' حتى ' . $delegation->end_date->format('Y-m-d'),
            'description_en' => 'You have been delegated claims from ' . $user->name . ' until ' . $delegation->end_date->format('Y-m-d'),
            'regarding' => NotificationType::DELEGATION_RECEIVED->value,
            'to' => json_encode([$delegateTo->id]),
            'links' => json_encode([
                [
                    'label_ar' => 'عرض التفويض',
                    'label_en' => 'View Delegation',
                    'url' => 'delegations/' . $delegation->id,
                ],
            ]),
        ]);
    }
}

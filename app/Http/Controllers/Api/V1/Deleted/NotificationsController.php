<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Enums\SupervisorActions;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Services\PendingActionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationsController extends Controller
{
    /**
     * Get notifications based on regarding parameter
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $regarding = $request->input('regarding', 'all');
        $viewStatus = $request->input('view_status', 0);
        $userId = auth()->user()->id;

        try {
            if ($regarding != 'all') {
                $notifications = Notification::where('regarding', $regarding)
                    ->where('viewed', $viewStatus)
                    ->whereJsonContains('to', $userId)
                    ->latest()
                    ->orderBy('id', 'desc')
                    ->get();
            } else {
                $notifications = Notification::where('viewed', $viewStatus)
                    ->whereJsonContains('to', $userId)
                    ->latest()
                    ->orderBy('id', 'desc')
                    ->get();
            }

            return response()->json([
                'error' => false,
                'message' => 'Notifications retrieved successfully',
                'data' => $notifications
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notifications as read
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request)
    {
        $notificationIds = $request->input('notification_ids', []);

        try {
            NotificationService::asRead($notificationIds);

            return response()->json([
                'error' => false,
                'message' => 'Notifications marked as read successfully'
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification color based on type
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getColor(Request $request)
    {
        $notificationType = $request->input('notification_type');

        $color = '';
        switch ($notificationType) {
            case 'general':
                $color = 'green';
                break;
            case 'new_claim_created':
                $color = 'blue';
                break;
            case 'claim_assigned':
                $color = 'green';
                break;
            case 'delegations':
                $color = 'green';
                break;
            case 'law':
                $color = 'green';
                break;
            default:
                $color = 'red';
                break;
        }

        return response()->json([
            'error' => false,
            'color' => $color
        ]);
    }

    /**
     * Send notification to the collector
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendNotificationToCollector(Request $request)
    {
        $modelId = $request->input('model_id');
        $modelClass = $request->input('model_class');

        try {
            $modelData = $modelClass::find($modelId);

            if (!$modelData) {
                return response()->json([
                    'error' => true,
                    'message' => 'Model data not found'
                ], 404);
            }

            $pendingActionService = new PendingActionService;
            $pendingActionService->toUser($modelData);

            return response()->json([
                'error' => false,
                'message' => __('claim.claims_assigned_successfully')
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    /**
     * Handle success for collector
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function successCollector(Request $request)
    {
        $modelId = $request->input('model_id');
        $modelClass = $request->input('model_class');

        try {
            $pendingAction = $modelClass::find($modelId);

            if (!$pendingAction) {
                return response()->json([
                    'error' => true,
                    'message' => 'Pending action not found'
                ], 404);
            }

            $pendingAction->status = SupervisorActions::SEND_IT_OTHER_COLLECTOR_OR_EXTERNAL_OFFICE->value;
            $pendingAction->save();

            return response()->json([
                'error' => false,
                'message' => __('claim.claims_assigned_successfully')
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    /**
     * Handle success for law department
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function successLaw(Request $request)
    {
        $modelId = $request->input('model_id');
        $modelClass = $request->input('model_class');

        try {
            $pendingAction = $modelClass::find($modelId);

            if (!$pendingAction) {
                return response()->json([
                    'error' => true,
                    'message' => 'Pending action not found'
                ], 404);
            }

            $pendingAction->status = SupervisorActions::SEND_IT_LEGAL->value;
            $pendingAction->save();

            return response()->json([
                'error' => false,
                'message' => __('claim.claims_assigned_successfully')
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationType;
use App\Events\NotificationEvent;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    /**
     * Get calendar data including payment links and notifications.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'regarding' => ['nullable'],
            'status' => ['nullable', Rule::in([0, 1])],
            'per_page' => ['nullable', 'integer', 'min:1'], // Results per page
            'page' => ['nullable', 'integer', 'min:1'],     // Page number
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $regarding = $request->input('regarding', 'all');
        $status =  $request->input('status');
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $query = Notification::whereJsonContains('to', auth()->id())
            //->where('regarding', '!=', NotificationType::EXPORT->value)
            ->when($status !== null, fn($query) => $query->where('viewed', $status))
            ->when(
                $regarding !== 'all',
                function ($query) use ($regarding) {

                    if ($regarding == 'payments') {
                        # code...
                        $query->whereIn('regarding', [NotificationType::PAID_INSTALLMENT->value, NotificationType::DISCHARGE_LETTER->value, NotificationType::VOUCHER_LETTER->value]);
                    } else {
                        $query->where('regarding', $regarding);
                    }

                }
            )
            ->latest();

        $notifications = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform the links field to an array
        $notifications->getCollection()->transform(function ($notification) {
            $notification->links = json_decode($notification->links);
            return $notification;
        });

        $response = [
            'success' => $notifications->isNotEmpty(),
            'message' => $notifications->isEmpty()
                ? 'No notifications found.'
                : 'Notifications data retrieved successfully.',
            'data' => $notifications->items(), // Current page data
            'meta' => [
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'from' => $notifications->firstItem(),
                'to' => $notifications->lastItem(),
            ],
        ];

        return response()->json($response);
    }


    /**
     * Mark notifications as seen or unseen.
     *
     * @param Request $request
     * @param int|null $notificationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleNotificationStatus(Request $request, $notificationId = null): \Illuminate\Http\JsonResponse
    {
        if ($notificationId) {
            $notification = Notification::find($notificationId);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->viewed = true;
            $notification->save();

            return response()->json([
                'success' => true,
                'message' => "Reminder marked as " . ($notification->viewed ? 'viewed' : 'not viewed') . " successfully"
            ]);
        }


        // If no ID, apply to all
        $data = Notification::whereJsonContains('to', auth()->id());
        $data->update(['viewed' => true]);

        return response()->json([
            'success' => true,
            'message' => "All notifications marked as viewed successfully"
        ]);
    }
    public function deleteNotification($notificationId)
    {
        $notification = Notification::find($notificationId);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => "notification deleted successfully"
        ]);
    }
}

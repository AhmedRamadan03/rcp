<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Models\Target;
use App\Services\TargetService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class TargetManagementController extends Controller
{
    /**
     * Get targets and collectors data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $targetService = new TargetService;
        $userService = new UserService;

        $collectors = $userService->getCollectorsForSupervisor(auth()->user()->id);
        $userTargets = $targetService->getCurrentTarget();
        $targetsHistory = $targetService->getTargetHistory();
        $hasPeriod = (bool) $targetService->getActivePeriod();

        return response()->json([
            'error' => false,
            'message' => 'Target management data retrieved successfully',
            'data' => [
                'collectors' => $collectors,
                'userTargets' => $userTargets,
                'targetsHistory' => $targetsHistory,
                'hasPeriod' => $hasPeriod
            ]
        ]);
    }

    /**
     * Add a new target
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addTarget(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target' => 'required|string',
            'collector_ids' => 'required|array',
            'collector_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $targetService = new TargetService;
            $allTargetIsCreated = true;

            foreach ($request->collector_ids as $collector_id) {
                try {
                    $targetService->createTarget([
                        'userId' => $collector_id,
                        'target' => $request->target,
                    ]);
                } catch (\Exception $e) {
                    $allTargetIsCreated = false;
                }
            }

            if (!$allTargetIsCreated) {
                return response()->json([
                    'error' => true,
                    'message' => __('target.some_users_already_has_a_setup_target')
                ]);
            } else {
                return response()->json([
                    'error' => false,
                    'message' => __('target.created_successfully')
                ]);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    /**
     * Edit an existing target
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function editTarget(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target_id' => 'required|exists:targets,id',
            'current_target' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $targetService = new TargetService;

            $targetService->editTarget([
                'targetId' => $request->target_id,
                'target' => $request->current_target,
                'userId' => auth()->user()->id,
            ]);

            return response()->json([
                'error' => false,
                'message' => __('target.updated_successfully')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a new target period
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addPeriod(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $targetService = new TargetService;
            $targetService->createPeriod([
                'startDate' => $request->start_date,
                'endDate' => $request->end_date,
            ]);

            return response()->json([
                'error' => false,
                'message' => __('target.period_created_successfully')
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a target
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteTarget(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'target_id' => 'required|exists:targets,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $targetService = new TargetService;
            $target = Target::findOrFail($request->target_id);

            if ($targetService->deleteTarget($target)) {
                $targetService->writeTargetHistory(
                    $target->id,
                    $target->user_id,
                    $target->target,
                    $target->target,
                    'delete',
                    auth()->user()->id
                );

                return response()->json([
                    'error' => false,
                    'message' => __('target.deleted_successfully')
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Failed to delete target'
                ], 500);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage()
            ], 500);
        }
    }
}

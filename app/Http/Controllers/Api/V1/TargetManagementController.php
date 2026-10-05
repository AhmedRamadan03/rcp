<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DataHistoryEnum;
use App\Enums\Roles;
use App\Exceptions\TargetAlreadyExistsException;
use App\Exports\TargetExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTargetRequest;
use App\Http\Resources\Api\V1\Target\TargetResource;
use App\Models\DataHistory;
use App\Models\Target;
use App\Models\User;
use App\Services\TargetService;
use App\Services\UserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Enums\Role;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TargetManagementController extends Controller
{
    private TargetService $targetService;
    private UserService $userService;

    public function __construct(TargetService $targetService, UserService $userService)
    {
        $this->targetService = $targetService;
        $this->userService = $userService;
    }

    public function index(): JsonResponse
    {
        $search = request()->query('search');
        $status = request()->query('status');

        $collectorsQuery = $this->userService
            ->getTargetCollectorsForSupervisor(auth()->user()->id)
            ->with(['currentTarget', 'previousTarget'])
            ->where(function ($q) {
                $q->whereHas('currentTarget')
                    ->orWhereHas('previousTarget');
            });

        // -------------------------
        // Search Filter
        // -------------------------
        if ($search) {
            $this->searchFilter($collectorsQuery, $search);
        }

        // --------------------
        //  Status Filter
        // --------------------
        if ($status) {
            $this->statusFilter($collectorsQuery, $status);
        }

        // -------------------------
        // تنفيذ + Paginate
        // -------------------------
        $collectorsWithTargets = $collectorsQuery->paginate(request()->per_page ?? 10);

        return response()->json([
            'success'   => true,
            'message'   => 'Target information retrieved successfully',
            'data'      => TargetResource::collection($collectorsWithTargets),
            'pagination' => [
                'current_page' => $collectorsWithTargets->currentPage(),
                'last_page'    => $collectorsWithTargets->lastPage(),
                'per_page'     => $collectorsWithTargets->perPage(),
                'total'        => $collectorsWithTargets->total(),
            ],
        ]);
    }

    public function usersAssignedToTarget()
    {
        $users = User::whereHas('currentTarget')
            ->whereIn('role_id', [
                Roles::COLLECTOR->value,
                Roles::EXTERNAL_OFFICE->value,
            ])
            ->pluck('id');

        return response()->json([
            'success' => true,
            'message' => 'users assigned to target',
            'data' => [
                'ids' => $users,
            ],
        ]);
    }

    public function export()
    {
        $fileName = 'targets_export_' . time() . '.xlsx';
        $filters = request()->only(['search', 'status']);
        $history = DataHistory::createDataHistory(DataHistoryEnum::EXPORT_TARGETS->value, DataHistoryEnum::EXPORT->value, '',$filters);
        \App\Jobs\ExportTargetOpenSpout::dispatch(auth()->user(), $fileName, $history->ref, $filters);

        return $this->successResponse('Your file is being prepared. You will be notified once it is ready.');
        // $collectorsQuery = $this->userService
        //     ->getTargetCollectorsForSupervisor(auth()->user()->id)
        //     ->with(['currentTarget', 'previousTarget'])
        //     ->where(function ($q) {
        //         $q->whereHas('currentTarget')
        //             ->orWhereHas('previousTarget');
        //     });
        // // -------------------------
        // //  فلتر البحث
        // // -------------------------
        // if ($search) {
        //     $this->searchFilter($collectorsQuery, $search);
        // }
        // // --------------------
        // // فلتر الـ STATUS
        // // --------------------
        // if ($status) {
        //     $this->statusFilter($collectorsQuery, $status);
        // }
        // // تنفيذ الكويري
        // $collectorsWithTargets = $collectorsQuery
        //     ->get()
        //     // ->filter(fn($c) => $c->currentTarget && $c->previousTarget)
        //     ->values();


        // return Excel::download(new TargetExport($collectorsWithTargets), 'collectors_targets.xlsx');
    }

    public function store(StoreTargetRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $allTargetIsCreated = true;

            if(auth()->user()->role_id == Roles::SENIOR_COLLECTOR->value) {
                if(!(new UserService())->belongToSupervisor($validated['collector_ids'], auth()->user()->id)){
                    throw new \Exception('One or more collectors are not under your supervision');
                }
            }

            foreach ($validated['collector_ids'] as $collector_id) {
                try {
                    $this->targetService->createTarget([
                        'userId' => $collector_id,
                        'target' => $validated['target'],
                        'target_type' => $validated['target_type'],
                        'is_reoccurring' => $validated['is_reoccurring'] ?? false,
                    ]);
                } catch (TargetAlreadyExistsException $e) {
                    $allTargetIsCreated = false;
                } catch (\Exception $e) {
                    Log::error('Target creation failed: '.$e->getMessage(), ['collector_id' => $collector_id]);
                    $allTargetIsCreated = false;
                }
            }

            return response()->json([
                'success' => $allTargetIsCreated ? true : false,
                'message' => $allTargetIsCreated
                    ? __('target.created_successfully')
                    : __('target.some_users_already_has_a_setup_target'),
                'data' => null
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'errors' => $exception->validator->errors(),
                'message' => $exception->getMessage(),
                'data' => []
            ],422);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => []
            ],400);
        }
    }

    public function show(Request $request, int $targetId): JsonResponse
    {
        try {
            $resource = User::where('id', $targetId)->with(['currentTarget', 'previousTarget'])->first();
            return response()->json([
                'success' => true,
                'message' => __('target.updated_successfully'),
                'data' => new TargetResource($resource, true)
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'errors' => $exception->validator->errors(),
                'message' => $exception->getMessage(),
                'data' => []
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'data' => []
            ],400);
        }
    }
    public function update(Request $request, int $targetId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'target' => 'required|numeric|min:1'
            ]);

            $this->targetService->editTarget([
                'targetId' => $targetId,
                'target' => $validated['target'],
                'userId' => auth()->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => __('target.updated_successfully'),
                'data' => null
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'errors' => $exception->validator->errors(),
                'message' => $exception->getMessage(),
                'data' => []
            ],422);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => []
            ],400);
        }
    }

    public function destroy(Target $target): JsonResponse
    {
        try {
            if ($this->targetService->deleteTarget($target)) {
                $this->targetService->writeTargetHistory(
                    $target->id,
                    $target->user_id,
                    $target->target,
                    $target->target,
                    'delete',
                    auth()->user()->id
                );

                return response()->json([
                    'success' => true,
                    'message' => __('target.deleted_successfully'),
                    'data' => null
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete target',
                'data' => null
            ], 422);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null
            ], 422);
        }
    }

    public function storePeriod(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'startDate' => 'required|date|after_or_equal:today',
                'endDate' => 'required|date|after:startDate',
            ]);

            $this->targetService->createPeriod([
                'startDate' => $validated['startDate'],
                'endDate' => $validated['endDate'],
            ]);

            return response()->json([
                'success' => true,
                'message' => __('target.period_created_successfully'),
                'data' => null
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'errors' => $exception->validator->errors(),
                'message' => $exception->getMessage(),
                'data' => []
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => []
            ]);
        }
    }


    private function searchFilter($collectorsQuery, $search)
    {
        $collectorsQuery->where(function ($q) use ($search) {

            // البحث في اسم المستخدم
            $q->where('name', 'ILIKE', "%{$search}%")

                // البحث في currentTarget
                ->orWhereHas('currentTarget', function ($t) use ($search) {
                    $t->where('target', 'like', "%{$search}%")
                        ->orWhere('achieved', 'like', "%{$search}%")
                        ->orWhere('remaining', 'like', "%{$search}%");
                })

                // البحث في previousTarget
                ->orWhereHas('previousTarget', function ($t) use ($search) {
                    $t->where('target', 'like', "%{$search}%")
                        ->orWhere('achieved', 'like', "%{$search}%")
                        ->orWhere('remaining', 'like', "%{$search}%");
                });
        });
    }
    private function statusFilter($collectorsQuery, $status)
    {
        $collectorsQuery->where(function ($q) use ($status) {

            //1) Target Collected
            if ($status === 'collected') {
                $q->whereHas('currentTarget', function ($t) {
                    $t->whereColumn('achieved', '>=', 'target');
                });
            }

            //2) Target On Progress
            if ($status === 'progress') {
                $q->whereHas('currentTarget', function ($t) {
                    $t->whereColumn('achieved', '<', 'target');
                });
            }

            //3) Achieved Last Target
            if ($status === 'achieved_last') {
                $q->whereHas('previousTarget', function ($t) {
                    $t->whereColumn('achieved', '>=', 'target');
                });
            }

            //4) Missed Last Target
            if ($status === 'missed_last') {
                $q->whereHas('previousTarget', function ($t) {
                    $t->whereColumn('achieved', '<', 'target');
                });
            }
        });
    }
}
<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DataHistoryEnum;
use App\Enums\ExportType;
use App\Enums\Roles;
use App\Exports\IvrExport;
use App\Http\Requests\V1\ExportIvrsRequest;
use App\Models\Assigning;
use App\Models\DataHistory;
use App\Models\Ivr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IvrController extends Controller
{
    /**
     * Get calendar data including payment links and reminders
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(ExportIvrsRequest $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $user = auth()->user();

        // Handle permissions safely
        $permissions = safe_json_decode($user->permission->first()->reports ?? '{}');
        $showReport = !empty($permissions->show_reports) && $permissions->show_reports == 1;

        $query = Ivr::with('paymentLink.payment.claim')->filter();

        if (!$showReport) {
            $claimIds = $this->getUserClaimIds($user);

            if (!empty($claimIds)) {
                $query->whereHas('paymentLink.payment', function ($q) use ($claimIds) {
                    $q->whereIn('claim_id', $claimIds);
                });
            } else {
                // لو مفيش claims رجّع empty result بسرعة
                $query->whereRaw('1 = 0');
            }
        }

        $messages = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform links
        $messages->getCollection()->transform(function ($item) {
            $item->links = json_decode($item->links);
            return $item;
        });

        return response()->json([
            'success' => $messages->isNotEmpty(),
            'message' => $messages->isEmpty() ? 'No Data found.' : 'Data retrieved successfully.',
            'data' => $messages->items(),
            'meta' => [
                'total' => $messages->total(),
                'per_page' => $messages->perPage(),
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'from' => $messages->firstItem(),
                'to' => $messages->lastItem(),
            ],
        ]);
    }

    //  public function export(Request $request): \Illuminate\Http\JsonResponse|BinaryFileResponse
    // {
    //     return Excel::download(new IvrExport(), 'ivrs.xlsx');
    // }

    public function export(Request $request)
    {
        $fileName = 'ivrs_' . time() . '.xlsx';

        Excel::queue(new IvrExport(), 'exports/' . $fileName, 'public')->chain([
            new \App\Jobs\MarkExportAsReady($fileName, auth()->user(), ExportType::EXPORT_IVRS),
        ]);
        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }

    public function OpenSpoutexportIvrs(ExportIvrsRequest $request)
    {
        $fileName = 'ivrs_' . time() . '.xlsx';
        
        $filters = $request->filters();
        $history = DataHistory::createDataHistory(DataHistoryEnum::EXPORT_IVRS->value, DataHistoryEnum::EXPORT->value, '',$filters);
        \App\Jobs\ExportIvrsOpenSpout::dispatch(auth()->user(), $fileName, $history->ref,$request->columns ?? [],$filters);

        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }


    private function getUserClaimIds($user): array
    {
        if ($user->role_id === Roles::SENIOR_COLLECTOR->value) {
            $subordinateIds = $user->subordinates()->pluck('id')->toArray();
            $userIds = array_merge([$user->id], $subordinateIds);

            return Assigning::whereIn('to', $userIds)
                ->where('is_active', 1)
                ->pluck('claim_id')
                ->toArray();
        }

        return Assigning::where('to', $user->id)
            ->where('is_active', 1)
            ->pluck('claim_id')
            ->toArray();
    }
}

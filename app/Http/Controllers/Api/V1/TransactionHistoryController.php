<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TransactionEnquiryAction;
use App\Enums\DataHistoryEnum;
use App\Exports\Transactions;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\PaymentTransactions\TransactionHistoryFilterRequest;
use App\Jobs\ExportPaymentTransactionsOpenSpout;
use App\Models\DataHistory;
use App\Models\PaymentTransaction;
use App\Services\SmsService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionHistoryController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function index(TransactionHistoryFilterRequest $request): JsonResponse
    {
        $serviceList =$this->transactionService->list($request->filters(), $request->perPage());
        return response()->json([
            "succes" => true,
            'message' => 'Transaction history retrieved successfully',
            'data' =>  $serviceList->items(),

            'pagination' => [
                'total' => $serviceList->total(),
                'per_page' => $serviceList->perPage(),
                'current_page' => $serviceList->currentPage(),
                'last_page' => $serviceList->lastPage(),
                'from' => $serviceList->firstItem(),
                'to' => $serviceList->lastItem()
            ]
        ]);
    }

    public function export(TransactionHistoryFilterRequest $request): JsonResponse
    {
        $filters = $request->filters();
        $columns = $request->columns();

        $fileName = 'transactions_' . time() . '.xlsx';

        $history = DataHistory::createDataHistory(
            DataHistoryEnum::EXPORT_TRANSACTION_HISTORY->value,
            DataHistoryEnum::EXPORT->value,
            $fileName,
            $filters
        );

        ExportPaymentTransactionsOpenSpout::dispatch($filters, $columns, auth()->user(), $fileName, $history->ref);

        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }

    public function enquiry(int $transactionId): JsonResponse
    {
        try {
            $service = new SmsService;
            $transaction = PaymentTransaction::findOrFail($transactionId);

            $className = $service->returnClassName('payment');
            $transactionEnquiryAction = new TransactionEnquiryAction($className, $transaction);
            $transactionEnquiryAction->execute();

            return response()->json([
                'message' => __('transaction.enquiry_has_been_done_successfully'),
                'status' => 'success'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'status' => 'error'
            ], 500);
        }
    }
}

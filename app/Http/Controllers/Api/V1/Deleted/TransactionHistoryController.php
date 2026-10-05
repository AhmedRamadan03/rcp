<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Actions\TransactionEnquiryAction;
use App\Exports\Transactions;
use App\Models\PaymentTransaction;
use App\Services\SmsService;
use App\Services\TransactionService;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TransactionHistoryController extends Controller
{
    /**
     * Get transaction history with filters
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $filters = [
            'claim_id' => $request->input('claim_id', ''),
            'claim_number' => $request->input('claim_number', ''),
            'manual' => $request->boolean('manual', false),
            'card' => $request->input('card', ''),
            'card_type' => $request->input('card_type', ''),
            'collector_id' => $request->input('collector_id', ''),
            'transaction_status_success' => $request->boolean('transaction_status_success', false),
            'transaction_status_failed' => $request->boolean('transaction_status_failed', false),
            'debtor_phone' => $request->input('search_number', ''),
            'policy_number' => $request->input('search_number', ''),
            'amount' => $request->input('search_number', ''),
            'sub_claim_number' => $request->input('search_number', ''),
            'debtor_nid' => $request->input('search_number', ''),
        ];

        $paginate = $request->input('paginate', 10);

        $serviceList = (new TransactionService)->list($filters, $paginate);

        return response()->json([
            'error' => false,
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

    /**
     * Export transactions to Excel
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        $filters = [
            'claim_id' => $request->input('claim_id', ''),
            'claim_number' => $request->input('claim_number', ''),
            'manual' => $request->boolean('manual', false),
            'card' => $request->input('card', ''),
            'card_type' => $request->input('card_type', ''),
            'collector_id' => $request->input('collector_id', ''),
            'transaction_status_success' => $request->boolean('transaction_status_success', false),
            'transaction_status_failed' => $request->boolean('transaction_status_failed', false),
            'debtor_phone' => $request->input('search_number', ''),
            'policy_number' => $request->input('search_number', ''),
            'amount' => $request->input('search_number', ''),
            'sub_claim_number' => $request->input('search_number', ''),
            'debtor_nid' => $request->input('search_number', ''),
        ];

        $paginate = $request->input('paginate', 10);

        $serviceList = (new TransactionService)->listExport($filters, $paginate, false);
        $transactions = $serviceList->get();

        return Excel::download(new Transactions($transactions), 'transactions.xlsx');
    }

    /**
     * Perform transaction enquiry
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function enquiry(Request $request)
    {
        $transactionId = $request->input('transaction_id');

        if (!$transactionId) {
            return response()->json([
                'error' => true,
                'message' => 'Transaction ID is required'
            ], 400);
        }

        try {
            $service = new SmsService;
            $transaction = PaymentTransaction::find($transactionId);

            if (!$transaction) {
                return response()->json([
                    'error' => true,
                    'message' => 'Transaction not found'
                ], 404);
            }

            $className = $service->returnClassName('payment');
            $transactionEnquiryAction = new TransactionEnquiryAction($className, $transaction);
            $transactionEnquiryAction->execute();

            return response()->json([
                'error' => false,
                'message' => __('transaction.enquiry_has_been_done_successfully')
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => true,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}

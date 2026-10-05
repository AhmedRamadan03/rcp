<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Enums\BankAccountStatusEnum;
use App\Http\Requests\V1\Settings\StoreBankAccountRequest;
use App\Models\BankAccount;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class BankTransfersController extends Controller
{
    use ApiResponse;

    public function createAccount(StoreBankAccountRequest $request)
    {
        DB::transaction(function () use ($request) {

            // Make all bank accounts inactive
            $this->makeAllBankAccountsInactive();
            // Create new bank account
            $inputs = $request->validated();
            $inputs['status'] = BankAccountStatusEnum::ACTIVE->value;

            BankAccount::create($inputs);
        });

        return $this->successResponse('Bank account created successfully');
    }

    public function updateAccount(StoreBankAccountRequest $request, $id)
    {
        DB::transaction(function () use ($request, $id) {

           $inputs=$request->validated();

            BankAccount::where('id', $id)
                ->update($inputs);});

        return $this->successResponse('Bank account Updated successfully');
    }

    public function activateAccount( $id)
    {
        $resource = BankAccount::find($id);

        if (!$resource) {
            return $this->apiErrorResponse('Bank account not found', 404);
        }
       // Make all bank accounts inactive
        $this->makeAllBankAccountsInactive();

        BankAccount::where('id', $id)
            ->update(['status' => BankAccountStatusEnum::ACTIVE->value]);

        return $this->successResponse('Bank account Activated successfully');
    }

    public function getAccount( $id)
    {
        $resource = BankAccount::find($id);

        if (!$resource) {
            return $this->apiErrorResponse('Bank account not found', 404);
        }

        return $this->successResponse('Bank account retrieved successfully', $resource);
    }

    private function makeAllBankAccountsInactive()
    {
       return  BankAccount::where('status', BankAccountStatusEnum::ACTIVE->value)
                ->update(['status' => BankAccountStatusEnum::INACTIVE->value]);

    }
}

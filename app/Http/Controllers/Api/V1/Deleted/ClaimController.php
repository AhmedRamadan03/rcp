<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Exceptions\AleardyApproved;
use App\Exceptions\NotAuthorizeException;
use App\Http\Requests\V1\ClaimFileUploadedRequest;
use App\Http\Requests\V1\CreateClaimRequest;
use App\Http\Resources\Api\V1\ClaimResource;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Models\Claim;
use App\Services\ClaimService;
use App\Services\RejectionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ClaimController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __construct(public ClaimService $service) {}

    public function index()
    {
        return response()->json(
            new Collection($this->service->list(), $message = 'claim list', $resourceClass = ClaimResource::class)
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateClaimRequest $request)
    {

        // TODO add automatic assigning
        return response()->json([
            'error' => false,
            'message' => 'created succssfully',
            'data' => ClaimResource::make($this->service->create($request->validated())),
        ]);

    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $claim = Claim::findOrFail($id);

            return response()->json([
                'error' => false,
                'message' => 'show one claim',
                'data' => ClaimResource::make($this->service->info($claim)),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'claim with this id does not found',
            ]);
        }

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Claim $claim)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update($request, Claim $Claim)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Claim $Claim)
    {
        //
    }

    public function acceptClaim($id)
    {
        try {
            $claim = Claim::findOrFail($id);

            return response()->json([
                'error' => false,
                'message' => 'claim accepted succssfully',
                'data' => ClaimResource::make($this->service->accept($claim)),
            ]);
        } catch (NotAuthorizeException $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage(),
            ]);

        } catch (AleardyApproved $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage(),
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'error' => true,
                'message' => 'claim with this id does not found',
            ]);
        }

    }

    public function uploadFile(ClaimFileUploadedRequest $request)
    {

        // return (auth()->user()->role_id);

        $claim = Claim::find($request->claim_id);

        if (auth()->user()->cannot('upload', $claim)) {
            return response()->json([
                'error' => true,
                'message' => 'You does not have access to this operation',
            ]);
        }

        try {

            return response()->json([
                'error' => false,
                'message' => 'file uploaded succssfully',
                'data' => ClaimResource::make($this->service->uploadClaimFile($request->validated())),
            ]);

        } catch (NotAuthorizeException $exception) {
            return response()->json([
                'error' => true,
                'message' => $exception->getMessage(),
            ]);
        }

    }

    public function store_rejection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'claim_id' => 'required|numeric',
            'files' => 'array',
            'reason' => 'required',
            'files.*' => 'sometimes|file|max:2048',
        ],
            [
                'files.*.max' => 'يجيب ان لا يتجاوز حجم الملف المقصود 2 ميقابايت',
                'reason.required' => 'يرجي ادخال السبب',
            ]
        );

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }
        $rejection_service = new RejectionService;
        $rejection_service->createDebtorRejection($validator->validated());

        return back()->with('success', 'Rejection created successfully');
    }
}

<?php


use App\Http\Controllers\Api\V1\ClaimController;
use App\Http\Controllers\Api\V1\Claims\ClaimInfoController;
use App\Http\Controllers\Api\V1\Claims\ClaimMessageController;
use App\Models\Claim;
use App\Models\User;

Route::get('/', [ClaimController::class, 'index'])
    ->name('claims.index')
    ->middleware('can:viewAny,' . Claim::class);

Route::post('/check-duplicate', [ClaimController::class, 'checkDuplicateClaim'])
    ->name('claims.check-duplicate')
    ->middleware('can:viewAny,' . Claim::class);
Route::post('/check-external-reference', [ClaimController::class, 'checkExternalReferenceExists'])
    ->name('claims.check-external-reference')
    ->middleware('can:viewAny,' . Claim::class);

Route::get('/recovery-reasons', [ClaimController::class, 'getRecoveryReasons'])
    ->name('claims.recovery-reasons')
    ->middleware('can:viewAny,' . Claim::class);

Route::get('/list-backlog-templates', [ClaimController::class, 'AvailableClaimStatusTemplate'])
    ->name('claims.list-backlog-templates');

Route::post('/import', [ClaimController::class, 'import'])
    ->name('claims.import')
    ->middleware('can:import,' . Claim::class);

Route::post('/backlog-import', [ClaimController::class, 'backlogImport'])
    ->name('claims.backlog-import')
    ->middleware('can:import,' . Claim::class);


Route::get('/import/result/{fileId}', [ClaimController::class, 'importResult'])
    ->name('claims.import.result')
    ->middleware('auth:sanctum');

Route::get('/export', [ClaimController::class, 'exportOpenSpout'])
    ->name('claims.export')
    ->middleware('can:export,' . Claim::class);

Route::get('/import-template', [ClaimController::class, 'downloadImportClaimsTemplate'])
    ->name('claims.import-template')
    ->middleware('can:viewAny,' . Claim::class);

Route::get('/backlog-import-template', [ClaimController::class, 'downloadBacklogTemplate'])
    ->name('claims.backlog-import-template');

Route::get('/excel-bulk-assign-template', [ClaimInfoController::class, 'downloadBulkAssignClaimsTemplate'])
    ->name('claims.excelBulkAssignClaimsTemplate')
    ->middleware('can:assign_claim,App\Models\Claim');

// Risk Assessment Routes
Route::get("/{claimId}/risk", [App\Http\Controllers\Api\V1\RiskController::class, "show"])->name("claims.risk.show")->middleware("auth:sanctum");
Route::post("/{claimId}/risk/recalculate", [App\Http\Controllers\Api\V1\RiskController::class, "recalculate"])->name("claims.risk.recalculate")->middleware("auth:sanctum");
Route::get("/risk-summary", [App\Http\Controllers\Api\V1\RiskController::class, "summary"])->name("claims.risk.summary")->middleware("auth:sanctum");
Route::get('/{claimId}', [ClaimController::class, 'show'])
    ->name('claims.show')
    ->middleware('can:view,' . Claim::class);

Route::post('/create', [ClaimController::class, 'store'])
    ->name('claims.store')
    ->middleware('can:create,' . Claim::class);

Route::put('/{claimId}', [ClaimController::class, 'update'])
    ->name('claims.update')
    ->middleware('can:update,' . Claim::class . ',claimId');

Route::put('/{claimId}/skip-msg', [ClaimController::class, 'skipMsg'])
    ->name('claims.skipMsg')
    ->middleware('can:update,' . Claim::class . ',claimId');

Route::put('/{claimId}/proof-debt-enable', [ClaimController::class, 'proofDebtEnable'])
    ->name('claims.proofDebtEnable')
    ->middleware('can:update,' . Claim::class . ',claimId');


Route::put('/{claimId}/change-amount', [ClaimController::class, 'updateAmount'])
    ->name('claims.change-amount')
    ->middleware('can:update_claim_amount,' . Claim::class . ',claimId');

Route::patch('/{claim}/toggle-client-requests', [ClaimController::class, 'toggleClientRequests'])
    ->name('claims.toggle-client-requests')
    ->middleware('can:manage_client_requests,claim');


// Info routes
Route::get('/{claimId}', [ClaimInfoController::class, 'show'])->name('claims.info')->middleware('can:view,App\Models\Claim,claimId');
Route::get('/{claimId}/files', [ClaimInfoController::class, 'claimFiles'])->name('claims.claimFiles')->middleware('can:showClaimFiles,App\Models\Claim,claimId');
Route::get('/{claimId}/additional-files', [ClaimInfoController::class, 'claimAdditionalFiles'])->name('claims.claimAdditionalFiles')->middleware('can:view,App\Models\Claim,claimId');
Route::get('/{claimId}/calls', [ClaimInfoController::class, 'claimCalls'])->name('claims.claimCalls')->middleware('can:view,App\Models\Claim,claimId');
Route::get('/{claimId}/comments', [ClaimInfoController::class, 'claimComments'])->name('claims.claimComments')->middleware('can:view,App\Models\Claim,claimId');
Route::post('/{claimId}/comments', [ClaimInfoController::class, 'createClaimComment'])->name('claims.createClaimComment')->middleware('can:view,App\Models\Claim,claimId');
Route::get('/{claimId}/payments', [ClaimInfoController::class, 'claimPayments'])->name('claims.claimPayments')->middleware('can:view,App\Models\Claim,claimId');
Route::get('/{claimId}/links', [ClaimInfoController::class, 'claimPaymentLinks'])->name('claims.claimPaymentLinks')->middleware('can:view,App\Models\Claim,claimId');
Route::get('/{claimId}/canceled-links', [ClaimInfoController::class, 'getCanceledLinks'])->name('claims.getCanceledLinks')->middleware('can:view,App\Models\Claim,claimId');
Route::get('/{claimId}/transactions/export', [ClaimInfoController::class, 'exportClaimTransactions'])->name('claims.claimTransactions.export')->middleware('can:view,App\Models\Claim,claimId');
Route::get('/{claimId}/transactions', [ClaimInfoController::class, 'claimTransactions'])->name('claims.claimTransactions')->middleware('can:view,App\Models\Claim,claimId');

// route to get claim status history by claimId
Route::get('/{claimId}/status-history', [ClaimInfoController::class, 'claimStatusHistory'])->name('claims.claimStatusHistory')->middleware('can:view,App\Models\Claim,claimId');

// route to get claim login attemps
Route::get('/{claimId}/login-attempts', [ClaimInfoController::class, 'claimLoginAttempts'])->name('claims.claimLoginAttempts')->middleware('can:view,App\Models\Claim,claimId');


// route to get claim installments
Route::get('/{claimId}/installments', [ClaimInfoController::class, 'claimInstallments'])->name('claims.claimInstallments')->middleware('can:view,App\Models\Claim,claimId');


// route to get list of assigned users by claimId
Route::get('/{claimId}/assigned-users', [ClaimInfoController::class, 'claimAssignedUsers'])->name('claims.claimAssignedUsers')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:assign_claim,App\Models\Claim'
    ]);

Route::post('/{claimId}/assign', [ClaimInfoController::class, 'assign'])
    ->name('claims.info.assign')
    ->middleware('can:assign_claim,App\Models\Claim');

Route::post('/bulk-assign', [ClaimInfoController::class, 'bulkAssignClaims'])
    ->name('claims.bulkAssignClaims')
    ->middleware('can:assign_claim,App\Models\Claim');

Route::post('/excel-bulk-assign', [ClaimInfoController::class, 'excelBulkAssignClaimsOpenSpout'])
    ->name('claims.excelBulkAssignClaims')
    ->middleware('can:assign_claim,App\Models\Claim');

    
Route::post('/{claimId}/files', [ClaimInfoController::class, 'uploadFile'])
    ->name('claims.info.upload')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:create,App\Models\ClaimFile',
    ]);

// route to delete file by fileId
Route::delete('/{claimId}/files/{fileId}', [ClaimInfoController::class, 'deleteFile'])
    ->name('claims.info.deleteFile')
    ->middleware([
        'can:deleteClaimFile,App\Models\Claim,claimId'
    ]);

// route to update file visibility
Route::post('/{claimId}/files/{fileId}', [ClaimInfoController::class, 'updateClaimFileAccess'])
    ->name('claims.info.updateClaimFileAccess')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:delete,App\Models\ClaimFile',
    ]);


// route to change status of claim to be accepted
Route::post('/{claimId}/accept', [ClaimInfoController::class, 'claimAccept'])
    ->name('claims.info.accept')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:approve_claim,App\Models\Claim,claimId',
    ]);
    
Route::post("/{claimId}/generate-voucher", [ClaimInfoController::class, 'generateVoucher'])
    ->name('claims.generateVoucher')
    ->middleware(['can:view,App\Models\Claim,claimId', 'can:generate_voucher_letter,App\Models\Claim,claimId']);

// payment links routes
Route::post('/{claimId}/links/{linkId}/bank-transfer', [ClaimInfoController::class, 'linkBankTransfer'])
    ->name('claims.claimPaymentLinks.bankTransfer')
    ->middleware('can:view,App\Models\Claim,claimId');


Route::post("/{claimId}/links/{linkId}/generate-voucher", [ClaimInfoController::class, 'linkGenerateVoucher'])
    ->name('claims.claimPaymentLinks.generateVoucher')
    ->middleware(['can:view,App\Models\Claim,claimId', 'can:generate_voucher_letter,App\Models\Claim,claimId']);

Route::post('/{claimId}/links/{linkId}/send-voucher', [ClaimInfoController::class, 'linkSendVoucher'])
    ->name('claims.claimPaymentLinks.sendVoucher')
    ->middleware(['can:view,App\Models\Claim,claimId', 'can:send_voucher_letter,App\Models\Claim,claimId']);

// route for generate discharge letter for claim
Route::post('/{claimId}/generate-discharge-letter', [ClaimInfoController::class, 'generateDischargeLetter'])
    ->name('claims.info.generate-discharge-letter')
    ->middleware(['can:view,App\Models\Claim,claimId', 'can:generate_discharge_letter,App\Models\Claim,claimId']);

// route for download discharge letter for claim
Route::get('/{claimId}/download-discharge-letter', [ClaimInfoController::class, 'downloadDischargeLetter'])
    ->name('claims.info.download-discharge-letter')
    ->middleware('can:view,App\Models\Claim,claimId');

// route for send discharge letter for claim
Route::post('/{claimId}/send-discharge-letter', [ClaimInfoController::class, 'sendDischargeLetter'])
    ->name('claims.info.send-discharge-letter')
    ->middleware(['can:view,App\Models\Claim,claimId', 'can:send_discharge_letter,App\Models\Claim,claimId']);

// route for add installments for claim
Route::post('/{claimId}/installments', [ClaimInfoController::class, 'makeInstallments'])
    ->name('claims.info.add-installments')
    ->middleware(['can:view,App\Models\Claim,claimId', 'can:installmentPayment,App\Models\Payment']);

// route for update installments for claim
Route::put('/{claimId}/installments', [ClaimInfoController::class, 'updateInstallments'])
    ->name('claims.info.update-installments')
    ->middleware(['can:view,App\Models\Claim,claimId', 'can:installmentPayment,App\Models\Payment']);

// route for delete installments for claim
Route::delete('/{claimId}/installments', [ClaimInfoController::class, 'deleteInstallments'])
    ->name('claims.info.delete-installments')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:installmentPayment,App\Models\Payment',
    ]);

Route::post('/{claimId}/delay-payment', [ClaimInfoController::class, 'makeDelayPayment'])
    ->name('claims.info.delay-payment')
    ->middleware(['can:view,App\Models\Claim,claimId', 'can:delayPayment,App\Models\Payment']);

Route::post('/{claimId}/discount', [ClaimInfoController::class, 'makeDiscount'])
    ->name('claims.info.make-discount')
    ->middleware([
        'can:view,App\Models\Claim,claimId'
    ]);

Route::post('/{claimId}/discount/cancel', [ClaimInfoController::class, 'cancelDiscount'])
    ->name('claims.info.cancel-discount')
    ->middleware([
        'can:view,App\Models\Claim,claimId'
    ]);

Route::post('/{claimId}/transfer-to-legal', [ClaimInfoController::class, 'transferClaimToLegal'])
    ->name('claims.info.transfer-to-legal')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:transfer_to_legal,App\Models\Claim,claimId'
    ]);

Route::post('/{claimId}/go-back-to-original-status', [ClaimInfoController::class, 'goBackToOriginalStatus'])
    ->name('claims.info.go-back-to-original-status')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:back_claim_to_original_status,App\Models\Claim,claimId'
    ]);
Route::post('/{claimId}/dispute-payment', [ClaimInfoController::class, 'disputePayment'])
    ->name('claims.info.dispute-payment')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:dispute_payment,App\Models\Claim,claimId'
    ]);

Route::post('/{claimId}/default-on-payment', [ClaimInfoController::class, 'defaultOnPayment'])
    ->name('claims.info.default-on-payment')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:default_in_payment_manual,App\Models\Claim,claimId'
    ]);

Route::post('/{claimId}/bank-transfer', [ClaimInfoController::class, 'claimBankTransfer'])
    ->name('claims.info.bankTransfer')
    ->middleware([
        'can:view,App\Models\Claim,claimId'
    ]);

Route::post('/{claimId}/credited', [ClaimInfoController::class, 'claimCredited'])
    ->name('claims.info.claimCredited')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:collected_credited,App\Models\Claim'
    ]);

Route::post('/{claimId}/transfer-to-elm', [ClaimInfoController::class, 'transferClaimToElm'])
    ->name('claims.info.transfer_to_elm')
    ->middleware([
        'can:view,App\Models\Claim,claimId'
    ]);

Route::post('/{claimId}/close', [ClaimInfoController::class, 'ClaimClose'])
    ->name('claims.info.close')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:close_claim,App\Models\Claim,claimId',
    ]);
Route::post('/{claimId}/collected-out-system', [ClaimInfoController::class, 'CollectedOutSystem'])
    ->name('claims.info.CollectedOutSystem')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:collected_out_of_system,App\Models\Claim,claimId',
    ]);

Route::post('/{claimId}/cancel', [ClaimInfoController::class, 'ClaimCancel'])
    ->name('claims.info.cancel')
    ->middleware([
        'can:view,App\Models\Claim,claimId',
        'can:cancel,App\Models\Claim,claimId',
    ]);

Route::post('/{claimId}/to-outstanding', [ClaimInfoController::class, 'toOutstanding'])
    ->name('claims.info.to-outstanding')
    ->middleware([
        'can:view,App\Models\Claim,claimId'
    ]);

Route::post('/info/{claimId}/calculate-installments', [ClaimInfoController::class, 'calculateInstallments'])
    ->name('claims.info.calculate-installments');


// Claim messages
Route::get('/{claimId}/messages', [ClaimMessageController::class, 'claimMessages'])->name('claims.claimMessages')->middleware('can:view,App\Models\Claim,claimId');
Route::post('/{claimId}/messages/{messageId}', [ClaimMessageController::class, 'reSend'])->name('claims.reSend')->middleware('can:view,App\Models\Claim,claimId');
Route::post('/{claimId}/payment-reminder', [ClaimMessageController::class, 'paymentReminder'])->name('claims.paymentReminder')->middleware('can:view,App\Models\Claim,claimId');
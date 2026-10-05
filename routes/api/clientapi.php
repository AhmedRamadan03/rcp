<?php

use App\Http\Controllers\Api\V1\Partner\PartnerClaimController;

Route::prefix('client-api/v1')->middleware(['throttle:60,1', 'partner.auth'])->group(function () {
    Route::get('/claims', [PartnerClaimController::class, 'index'])->name('client-api.claims.index');    
    Route::get('/claims/{reference}', [PartnerClaimController::class, 'show'])->name('client-api.claims.show');    
    Route::post('/claims', [PartnerClaimController::class, 'store'])->name('client-api.claims.store');
    Route::patch('/claims', [PartnerClaimController::class, 'patch'])->name('client-api.claims.patch');
    
    Route::get('/claims/{reference}/show-installment', [PartnerClaimController::class, 'showInstallment'])->name('client-api.claims.getInstallment');
    Route::post('/claims/{reference}/make-installment', [PartnerClaimController::class, 'makeInstallment'])->name('client-api.claims.makeInstallment');
    Route::put('/claims/{reference}/update-installment', [PartnerClaimController::class, 'updateInstallment'])->name('client-api.claims.updateInstallment');
    Route::delete('/claims/{reference}/delete-installment', [PartnerClaimController::class, 'deleteInstallment'])->name('client-api.claims.deleteInstallment');
    
    Route::post('/claims/{reference}/installments/{installment_id}/pay', [PartnerClaimController::class, 'payInstallment'])->name('client-api.claims.payInstallment');

});
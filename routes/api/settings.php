<?php

use App\Http\Controllers\Api\V1\Partner\PartnerApiCredentialController;
use App\Http\Controllers\Api\V1\Settings\BankTransfersController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\Settings\MessagesTemplatesController;
use App\Http\Controllers\Api\V1\Settings\ThirdPartyController;
use App\Models\Setting;
use App\Models\ThirdParty;

Route::get('/', [SettingsController::class, 'index'])
    ->name('settings.index')
    ->middleware('can:viewAny,App\Models\Setting');

Route::get('/third-parties/providers', [ThirdPartyController::class, 'providers'])
    ->name('settings.third-parties.providers')
    ->middleware('can:viewAny,App\Models\ThirdParty');
Route::get('/third-parties', [SettingsController::class, 'getThirdParties'])
    ->name('settings.third-parties')
    ->middleware('can:viewAny,App\Models\ThirdParty');
Route::get('/third-parties/{id}', [SettingsController::class, 'getThirdParty'])
    ->name('settings.third-party')
    ->middleware('can:viewAny,App\Models\ThirdParty');

Route::post('/third-parties', [ThirdPartyController::class, 'store'])->middleware('can:create,App\Models\ThirdParty');
Route::put('/third-parties', [ThirdPartyController::class, 'update'])->middleware('can:update,App\Models\ThirdParty');
Route::delete('/third-parties/{id}', [ThirdPartyController::class, 'destroy'])->middleware('can:delete,App\Models\ThirdParty');
//Route::delete('/third-parties/{id}/{type}', [SettingsController::class, 'deleteThirdParty'])->middleware('can:delete,App\Models\ThirdParty');
Route::post('/third-parties/{id}/default', [ThirdPartyController::class, 'setDefault'])->middleware('can:activate,App\Models\ThirdParty');
//toggle active
Route::post('/third-parties/{id}/toggle', [ThirdPartyController::class, 'toggle'])->middleware('can:activate,App\Models\ThirdParty');

Route::put('/payment-reminders', [SettingsController::class, 'updatePaymentReminders'])
    ->name('settings.payment-reminders')
    ->middleware('can:update,App\Models\Setting');
Route::put('/letters-generation', [SettingsController::class, 'updateLettersGeneration'])
    ->name('settings.letters-generation')
    ->middleware('can:update,App\Models\Setting');
Route::put('/letters-generation-notification', [SettingsController::class, 'updateLettersGenerationNotification'])
    ->name('settings.letters-generation-notification')
    ->middleware('can:update,App\Models\Setting');

Route::put('/supervisor-email', [SettingsController::class, 'updateSupervisorEmail'])
    ->name('settings.supervisor-email')
    ->middleware('can:update,App\Models\Setting');

Route::put('/config', [SettingsController::class, 'updateCompanySetting'])
    ->name('settings.config')
    ->middleware('can:update,App\Models\Setting');

//bank transfers
Route::post('/create-account', [BankTransfersController::class, 'createAccount'])
    ->name('bank-transfers.create-account')
    ->middleware('can:create,App\Models\Setting');

Route::put('/update-account/{id}', [BankTransfersController::class, 'updateAccount'])
    ->name('bank-transfers.update-account')
    ->middleware('can:update,App\Models\Setting');

Route::put('/activate-account/{id}', [BankTransfersController::class, 'activateAccount'])
    ->name('bank-transfers.activate-account')
    ->middleware('can:update,App\Models\Setting');

Route::get('/get-account/{id}', [BankTransfersController::class, 'getAccount'])
    ->name('bank-transfers.get-account')
    ->middleware('can:viewAny,App\Models\Setting');

// Message Templates Routes

Route::get('/message-templates', [MessagesTemplatesController::class, 'index'])
    ->name('message-templates.index')
    ->middleware('can:viewAny,App\Models\Setting');

Route::get('/message-templates/{id}', [MessagesTemplatesController::class, 'show'])
    ->name('message-templates.show')
    ->middleware('can:viewAny,App\Models\Setting');

Route::post('/custom-templates', [MessagesTemplatesController::class, 'store'])
    ->name('message-templates.store')
    ->middleware('can:create,App\Models\Setting');

Route::put('/custom-templates', [MessagesTemplatesController::class, 'update'])
    ->name('message-templates.update')
    ->middleware('can:update,App\Models\Setting');

Route::put('/custom-templates/toggle-active', [MessagesTemplatesController::class, 'activeCustomTemplate'])
    ->name('message-templates.toggle-active')
    ->middleware('can:update,App\Models\Setting');

Route::delete('/custom-templates/{id}', [MessagesTemplatesController::class, 'destroy'])
    ->name('message-templates.destroy')
    ->middleware('can:delete,App\Models\Setting');


Route::get('/partner-api-credentials', [PartnerApiCredentialController::class, 'allApiCredential'])
    ->name('partner-api-credentials.allApiCredential')
    ->middleware('can:showApiCredential,App\Models\PartnerApiCredential');

Route::post('/generate-api-credentials', [PartnerApiCredentialController::class, 'generateApiCredential'])
    ->name('partner-api-credentials.generateApiCredential')
    ->middleware('can:manageApiCredential,App\Models\PartnerApiCredential');

Route::delete('/delete-api-credentials/{partnerApiCredential}', [PartnerApiCredentialController::class, 'deleteApiCredential'])
    ->name('partner-api-credentials.destroy')
    ->middleware('can:manageApiCredential,App\Models\PartnerApiCredential');

Route::post('/revoke-api-credentials/{partnerApiCredential}', [PartnerApiCredentialController::class, 'revokeApiCredential'])
    ->name('partner-api-credentials.revoke')
    ->middleware('can:manageApiCredential,App\Models\PartnerApiCredential');

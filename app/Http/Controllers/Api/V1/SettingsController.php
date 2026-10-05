<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CompanySettings;
use App\Enums\EmailSending;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Settings\ThirdPartyResource;
use App\Models\Setting;
use App\Models\ThirdParty;
use App\Services\SettingsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Enums\LetterGeneration;
use App\Enums\TwoFactorAuthentication;
use App\Enums\LetterNotification;
use App\Enums\ProofOfDebt;
use App\Models\BankAccount;

class SettingsController extends Controller
{
    private SettingsService $service;

    public function __construct(SettingsService $service)
    {
        $this->service = $service;
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Settings retrieved successfully',
            'data' => [
                'settings' => $this->service->settings(),
                'third_parties' => $this->service->thirdParties(),
                'payment_reminders' => Setting::paymentReminder(),
                'letters_generation' => Setting::lettersGeneration()->value,
                'letters_generation_notification' => Setting::lettersGenerationNotification()->value,
                'two_factor_authentication' => Setting::twoFactorGeneration()->value,
                'email_sending' => Setting::emailSending()->value,
                'hide_phone_number' => Setting::hidePhoneNumber()->value,
                'proof_of_debt' => Setting::proofOfDebt()->value,
                'bank_accounts' => BankAccount::get(),
                'company_supervisor_email' => Setting::supervisorEmail()->value ?: null,
            ]
        ]);
    }

    public function getThirdParties(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Third parties retrieved successfully',
            'data' => ThirdPartyResource::collection($this->service->thirdParties())
        ]);
    }

    public function getThirdParty(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Third party retrieved successfully',
            'data' => new ThirdPartyResource($this->service->thirdParty($id))
        ]);
    }

    public function storeThirdParty(Request $request): JsonResponse
    {
        if (!auth()->user()->can('create', ThirdParty::class)) {
            return response()->json([
                'success' => false,
                'message' => __('claim.you_are_not_authorize_to_perform_this_action'),
                'data' => null
            ], 403);
        }

        $thirdPartyData = $request->validate([
            'type' => 'required|string',
            'vendor_name' => 'required|string',
            'vendor_label' => 'required|string',
            'is_active' => 'required|boolean',
        ]);

        $vendorValues = $request->validate([
            'vendor_values' => 'required|array'
        ])['vendor_values'];

        if ($this->service->saveThirdPartyNew($thirdPartyData, $vendorValues)) {
            return response()->json([
                'success' => true,
                'message' => __('settings.third_party_has_been_added_successfully'),
                'data' => [
                    "thirdPartyData" => $thirdPartyData,
                    "vendorValues" => $vendorValues
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __('claim.you_are_not_authorize_to_perform_this_action'),
            'data' => null
        ], 403);
    }

    public function updateThirdParty(Request $request): JsonResponse
    {
        if (!auth()->user()->can('update', ThirdParty::class)) {
            return response()->json([
                'success' => false,
                'message' => __('claim.you_are_not_authorize_to_perform_this_action'),
                'data' => null
            ], 403);
        }

        $thirdPartyData = $request->validate([
            'id' => 'required|integer',
            'vendor_label' => 'required|string',
            'values' => 'required|array'
        ]);

        if ($this->service->updateThirdPartyInfo($thirdPartyData)) {
            return response()->json([
                'success' => true,
                'message' => __('settings.third_party_has_been_updated_successfully'),
                'data' => $thirdPartyData
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __('settings.invalid_third_party_information'),
            'data' => null
        ], 422);
    }

    public function deleteThirdParty(int $id, string $type): JsonResponse
    {
        if (!auth()->user()->can('delete', ThirdParty::class)) {
            return response()->json([
                'success' => false,
                'message' => __('claim.you_are_not_authorize_to_perform_this_action'),
                'data' => null
            ], 403);
        }

        if (!$this->service->deleteThirdParty($id, $type)) {
            return response()->json([
                'success' => false,
                'message' => __('settings.this_third_party_is_active_and_it_can_not_be_deleted'),
                'data' => null
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('settings.third_party_has_been_deleted_successfully'),
            'data' => null
        ]);
    }

    public function setDefaultThirdParty(int $id): JsonResponse
    {
        if (!auth()->user()->can('activate', ThirdParty::class)) {
            return response()->json([
                'success' => false,
                'message' => __('claim.you_are_not_authorize_to_perform_this_action'),
                'data' => null
            ], 403);
        }

        $thirdParty = $this->service->setDefaultThirdParty($id);
        return response()->json([
            'success' => true,
            'message' => __('settings.third_party_has_been_set_as_default_successfully'),
            'data' => $thirdParty
        ]);
    }

    public function updatePaymentReminders(Request $request): JsonResponse
    {
        try {
            $values = $request->validate([
                'reminders' => ['required', 'array'],
                'reminders.closed' => ['required', 'numeric', 'between:0,1'],
                'reminders.manaual_collected' => ['required', 'numeric', 'between:0,1'],
                'reminders.collected_credited' => ['required', 'numeric', 'between:0,1'],
                'reminders.follow_up' => ['required', 'numeric', 'between:0,1'],
                'reminders.default_in_payment_by_system' => ['required', 'numeric', 'between:0,1'],
                'reminders.default_in_payment' => ['required', 'numeric', 'between:0,1'],
                'reminders.law_firm' => ['required', 'numeric', 'between:0,1'],])['reminders'];
            Setting::where('key', CompanySettings::CLAIMS_PAYMENT_REMINDER->value)
                ->update(['value' => $values]);

            return response()->json([
                'success' => true,
                'message' => __('settings.reminder_changed_successfully'),
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

    public function updateLettersGeneration(Request $request): JsonResponse
    {
        try {

            $value = $request->validate(['value' => ['required', 'integer', Rule::enum(LetterGeneration::class), ]])['value'];
            Setting::where('key', CompanySettings::CLAIMS_LETTERS_GENERATION->value)
                ->update(['value' => json_encode((int) $value)]);

            return response()->json([
                'success' => true,
                'message' => __('settings.letters_generation_changed_successfully'),
                'data' => [
                    "setting"=>"settings.letters_generation",
                    "value"=>$value
                ]
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


     public function updateLettersGenerationNotification(Request $request): JsonResponse
    {
        if (!auth()->user()->can('update', Setting::class)) {
            return response()->json([
                'success' => false,
                'message' => __('claim.you_are_not_authorize_to_perform_this_action'),
                'data' => null
            ], 403);
        }

        try {

            $value = $request->validate(['value' => ['required', 'integer', Rule::enum(LetterNotification::class), ]])['value'];
            Setting::where('key', CompanySettings::CLAIMS_LETTERS_GENERATION_NOTIFICATION->value)
                ->update(['value' => json_encode((int) $value)]);

            return response()->json([
                'success' => true,
                'message' => __('settings.letters_generation_notification_changed_successfully'),
                'data' => [
                    "setting"=>"settings.letters_generation_notification",
                    "value"=>$value
                ]
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

    public function updateSupervisorEmail(Request $request): JsonResponse
    {

        try {

            $email = $request->validate(['email' => ['required', 'string', 'email:filter']])['email'];
            $value = strtolower($email);
            Setting::updateOrCreate(
                ['key' => CompanySettings::SUPERVISOR_EMAIL->value],
                ['value' => $value]
            );

            return response()->json([
                'success' => true,
                'message' => ('supervisor_email_changed_successfully'),
                'data' => [
                    "setting" => "settings.supervisor_email",
                    "value" => $value
                ]
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'errors' => $exception->validator->errors(),
            ], 422);

        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 400);
        }
    }

    public function updateCompanySetting(Request $request)
    {
        try {
            $validSettingKeys = array_column(CompanySettings::cases(), 'value');

            $validated = $request->validate([
                'key' => ['required', 'string', Rule::in($validSettingKeys)],
                'value' => ['required', 'integer'],
            ]);

            $settingValueMap = [
                CompanySettings::TWO_FACTOR_AUTH->value => TwoFactorAuthentication::class,
                CompanySettings::EMAIL_SENDING->value => EmailSending::class,
                CompanySettings::HIDE_PHONE_NUMBER->value => EmailSending::class,
                CompanySettings::PROOF_OF_DEBT->value => ProofOfDebt::class,
            ];

            $enumClass = $settingValueMap[$validated['key']];
            $allowedValues = array_column($enumClass::cases(), 'value');

            if (!in_array($validated['value'], $allowedValues, strict: true)) {
                throw ValidationException::withMessages([
                    'value' => ['The selected value is invalid for the given setting key.'],
                ]);
            }

            Setting::where('key', $validated['key'])
                ->update(['value' => json_encode((int) $validated['value'])]);


            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully.',
                'data' => [
                    'setting' => $validated['key'],
                    'value' => $validated['value'],
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->validator->errors(),
                'message' => $e->getMessage(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }


}
<?php

namespace App\Services\Claim;

use App\BusinessLogic\PaymentReminder\PaymentReminder;
use App\Enums\ClaimStatus;
use App\Enums\FileVisibility;
use App\Enums\MessageTemplates;
use App\Enums\NotificationType;
use App\Models\AdditionalFile;
use App\Models\Claim;
use App\Models\Setting;
use App\Models\User;
use App\Services\AssigningService;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\SmsService;
use App\Services\Claim\Support\ClaimStatusActionExecutor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LawFirmService
{
    public function __construct(
        private readonly ClaimStatusActionExecutor $executor,
    ) {}

    public function execute(
        Claim $claim,
        array $data,
        ?AdditionalFile $additionalFile,
        int $actingUserId,
        ClaimActionOptions $options,
    ): bool {

        $user = User::findOrFail($actingUserId);
        if ($this->executor->createApprovalRequest(
            claim: $claim,
            status: ClaimStatus::LAW_FIRM->value,
            data: $data,
            additionalFile: $additionalFile,
            user: $user,
            permissionKey: 'transfer_to_legal',
            options: $options,
        )) {
            return false;
        }

        $assignTo = $data['selected_legal_user'] ?? $actingUserId;

        DB::transaction(function () use ($claim, $additionalFile, $actingUserId, $data, $user, $assignTo) {
            $this->executor->updateStatus(
                claim: $claim,
                status: ClaimStatus::LAW_FIRM->value,
                additionalFile: $additionalFile,
                actingUserId: $actingUserId,
                data: $data,
            );

            (new AssigningService)->create([
                'to' => [$assignTo],
                'claims_id' => [$claim->id],
                'supervisor_id' => $actingUserId,
            ]);

            NotificationService::create([
                'title_ar' => 'تم تحويل مطالبة إلى الإدارة القانونية',
                'title_en' => 'Claim transferred to legal department',
                'description_ar' => 'تم تحويل المطالبة برقم ' . $claim->claim_number . ' و بمبلغ ' . $claim->amount . ' للإدارة القانونية.',
                'description_en' => 'Claim ' . $claim->claim_number . ' transferred to legal department with amount ' . $claim->amount . ' SAR',
                'regarding' => NotificationType::LAW->value,
                'user_id' => $user->id,
                'to' => json_encode(array_map('intval', [$assignTo])),
                'links' => json_encode([
                    [
                        'label_ar' => 'عرض تفاصيل الطلب',
                        'label_en' => 'Open Claim Info',
                        'url' => 'claims/' . $claim?->id . '/info',
                    ],
                ]),
            ]);
        });

        try {
            $messageService = new MessageService;
            $message = $messageService->convertTemplate(MessageTemplates::TRANSFER_TO_LAWYER->value, $claim->id);
            (new SmsService)->sendSms($claim->debtor_phone, $message, $claim->id, MessageTemplates::TRANSFER_TO_LAWYER->value, template_model_id: $claim->id);
        } catch (Throwable $e) {
            Log::error('LawFirmService: failed to send transfer-to-lawyer SMS after committed status change', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::LAW_FIRM->value), $claim->id);
        } catch (Throwable $e) {
            Log::error('LawFirmService: failed to schedule payment reminder after committed status change', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }
}
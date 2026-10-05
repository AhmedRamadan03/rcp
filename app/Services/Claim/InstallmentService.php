<?php

namespace App\Services\Claim;

use App\Enums\AdditionalFileType;
use App\Enums\ClaimStatus;
use App\Enums\FileSource;
use App\Enums\FileVisibility;
use App\Enums\LinkSendStatus;
use App\Models\AdditionalFile;
use App\Models\Claim;
use App\Models\PaymentLink;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\FilesUploader;
use App\Services\PaymentService;
use Carbon\Carbon;
use Exception;
use RuntimeException;
use Illuminate\Support\Str;

class InstallmentService
{
    public function __construct(
        private readonly ClaimService $claimService,
        private readonly PaymentService $paymentService,
        private readonly FilesUploader $filesUploader,
    ) {
    }

    public function create(Claim $claim, array $installments, ?string $note, int $actingUserId, ?ClaimActionOptions $options = null): bool
    {
        $this->validateClaim($claim);

        $installments = $this->normalizeInstallments($installments);

        $payment = $claim->payment;

        if (!$payment) {
            throw new RuntimeException(
                'Cannot create installments because the claim has no payment.'
            );
        }

        return $this->claimService->makeInstallments(
            $claim,
            $installments,
            $payment->id,
            $note,
            $actingUserId,
            $options
        );
    }

    /**
     * NOTE: this now calls ClaimService::updateInstallments() — NOT
     * makeInstallments(). They are different original site methods with
     * different behavior: updateInstallments() cancels any pending
     * PARTIAL approval request and routes through
     * PaymentService::updateInstallmentsPlan(), which makeInstallments()
     * does not do at all. Calling the wrong one silently drops that
     * behavior.
     *
     * Also restores ALL the cross-field validation that used to live in
     * ClaimController::updateInstallments() and was missing here
     * entirely: existing-link ownership/paid checks, duplicate/gap-free
     * order validation, strictly-increasing dates per order, and the
     * total-must-equal-remaining-amount check. These are business rules
     * that belong in the service (so both the website and the Partner
     * API get them), not request-parsing rules.
     */
    public function update(Claim $claim, array $installments, ?string $note, int $actingUserId, ?ClaimActionOptions $options = null): bool
    {
        $this->validateClaim($claim);

        $payment = $claim->payment;

        if (!$payment) {
            throw new RuntimeException(
                'Cannot update installments because the claim has no payment.'
            );
        }

        $links = $payment->links;

        if (!$links || $links->isEmpty()) {
            throw new RuntimeException(
                'Claim payment has no links, cannot update installments.'
            );
        }
        
        $paidLinksCount = $links->where('send_status', LinkSendStatus::PAID->value)->count();
        if ($links->count() === $paidLinksCount) {
            throw new RuntimeException(
                'Cannot update installments because the claim payment is already paid.'
            );
        }

        // --- Restored from the original controller ---

        $newInstallments = collect($installments)->filter(
            fn ($inst) => !isset($inst['payment_link_id']) || (int) $inst['payment_link_id'] === 0
        );
        $existingInstallmentIds = collect($installments)
            ->filter(fn ($inst) => isset($inst['payment_link_id']) && (int) $inst['payment_link_id'] > 0)
            ->pluck('payment_link_id')
            ->unique();

        
        if ($existingInstallmentIds->count() > 0) {
            $existingLinks = PaymentLink::where('payment_id', $payment->id)
                ->where('send_status', '<>', LinkSendStatus::CANCELED->value)
                ->whereIn('id', $existingInstallmentIds)
                ->get();

            if ($existingLinks->count() !== $existingInstallmentIds->count()) {
                throw new RuntimeException('Some installment IDs do not belong to this payment.');
            }

            $paidLinks = $existingLinks->where('send_status', LinkSendStatus::PAID->value);
            if ($paidLinks->count() > 0) {
                throw new RuntimeException(__('claim.some_installment_is_already_paid'));
            }
        }

        $normalized = $this->normalizeInstallments($installments);

        $sorted = collect($normalized)->sortBy('order')->values();

        if ($sorted->pluck('order')->duplicates()->isNotEmpty()) {
            throw new RuntimeException('Duplicate installment order values are not allowed.');
        }

        for ($i = 1; $i < $sorted->count(); $i++) {
            $prev = Carbon::parse($sorted[$i - 1]['date']);
            $curr = Carbon::parse($sorted[$i]['date']);

            if (!$curr->gt($prev)) {
                throw new RuntimeException(
                    'Installment with order ' . $sorted[$i]['order']
                    . ' must have a date after order ' . $sorted[$i - 1]['order'] . '.'
                );
            }

            if ((int) $sorted[$i]['order'] !== ((int) $sorted[$i - 1]['order'] + 1)) {
                throw new RuntimeException(
                    'Installment order must be sequential without gaps. Expected '
                    . ((int) $sorted[$i - 1]['order'] + 1) . ' but got ' . (int) $sorted[$i]['order'] . '.'
                );
            }
        }

        $totalAmount = round(collect($normalized)->sum('amount'), 2);
        $paidAmount = (float) PaymentLink::where('payment_id', $payment->id)
            ->where('send_status', LinkSendStatus::PAID->value)
            ->sum('amount');
        $remainingAmount = round(((float) $payment->amount) - $paidAmount, 2);

        if ($totalAmount != $remainingAmount) {
            throw new RuntimeException(
                'The sum of installment amounts (' . $totalAmount
                . ') does not match the remaining amount (' . $remainingAmount . ').'
            );
        }

        // --- End restored validation ---
        return $this->claimService->updateInstallments(
            $claim,
            $normalized,
            $payment->id,
            $note,
            $actingUserId,
            $options
        );
    }


    public function delete(Claim $claim, int $actingUserId, ?ClaimActionOptions $options = null): bool
    {
        $this->validateClaim($claim);


        $payment = $claim->payment;

        if (!$payment) {
            throw new RuntimeException(
                'Cannot create installments because the claim has no payment.'
            );
        }
        $user = User::find($actingUserId);

        return $this->paymentService->deleteInstallments($payment->id, $user, $options);
    }
    
    public function payInstallment(Claim $claim, int $linkId, array $data, int $actingUserId): bool
    {
        $paymentLink = PaymentLink::find($linkId);
        if (!$paymentLink) {
            throw new RuntimeException(
                "Cannot pay installment because the payment link does not exist."
            );
        }


        if ($paymentLink->send_status === LinkSendStatus::PAID->value) {
           throw new RuntimeException(
                "Cannot pay installment because the payment link is already paid."
            );
        }
        $requestArray = [
            'note' => $data['note'],
            'link_id' => $linkId,
        ];

         try {
            $file_title = ClaimStatus::getString(ClaimStatus::MANUAL_COLLECTED->value)
                . ' - Installment No. ' . $paymentLink->order;
            $url_title = Str::slug($file_title, '_');

            $file = $this->filesUploader->upload($data['invoice_file'], [
                'source'      => FileSource::ADDITIONAL_FILES,
                'directory'   => 'manual/' . Carbon::today()->year . '/' . $claim->id . '/' . $url_title,
                'uploaded_by' => $actingUserId,
            ]);
            $relativePath = $file->local_path ?? $file->cloud_path;

            $requestArray['url'] = [$relativePath];
            $additional_file = AdditionalFile::create([
                'title'             => $file_title,
                'url'               => $relativePath,
                'file_id'           => $file->id,
                'claim_id'          => $claim->id,
                'note'              => $data['note']?? "",
                'uploaded_by'       => $actingUserId,
                'attachment_status' => FileVisibility::PUBLISHED->value,
                'type' => AdditionalFileType::INSTALLMENT_MANUAL_COLLECTED->value,
            ]);

            $this->claimService->manualCollectionForInstallment($claim->id, $requestArray, $additional_file, $actingUserId);
           return true;
            
        } catch (Exception $e) {
            throw new RuntimeException(
                $e->getMessage()
            );
            
        }
    }
    
    private function normalizeInstallments(array $installments): array
    {
        return collect($installments)
            ->map(function ($installment) {
                return [
                    'amount' => $installment['amount'],
                    'date' => $installment['date'],
                    'order' => $installment['order'],
                    'id' => $installment['id'] ?? $installment['payment_link_id'] ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    private function validateClaim(Claim $claim): void
    {
        $invalidStatuses = [
            ClaimStatus::REGISTERED->value,
            ClaimStatus::ASSIGNED->value,
            ClaimStatus::REJECTED->value,
        ];

        if (in_array($claim->status, $invalidStatuses, true)) {
            throw new RuntimeException(
                "Installments cannot be created when claim status is {$claim->status}."
            );
        }

        if (
            $claim->status === ClaimStatus::TRANSFER_TO_ELM->value
            && !$claim->debtor_phone
        ) {
            throw new RuntimeException(
                "Installments cannot be created when claim status is {$claim->status}, and debtor phone is empty."
            );
        }

        if ($claim->is_collected) {
            throw new RuntimeException(
                'Installments cannot be created because the claim is already collected.'
            );
        }

        if ($claim->is_close == 1) {
            throw new RuntimeException(
                'Installments cannot be created because the claim is closed.'
            );
        }
        $links=  $claim?->payment?->links->where('send_status','!=','canceled')->count();
        if ($links >1&& request()->routeIs('client-api.claims.makeInstallment')) {
            throw new RuntimeException(
                'Installments cannot be created because the claim already has an installment plan.'
            );
        }
    }
}
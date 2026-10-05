<?php

namespace App\Services;

use App\Actions\GenerateLetters;
use App\Enums\AdditionalFileType;
use App\Enums\ClaimStatus;
use App\Enums\Clients;
use App\Enums\FileSource;
use App\Enums\FileVisibility;
use App\Enums\IVRs;
use App\Enums\LinkSendStatus;
use App\Enums\MessageTemplates;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Enums\RequestStatus;
use App\Enums\SmsStatus;
use App\Events\ClaimInfo\UpdateClaimInstallmentEvent;
use App\Events\Payment\PaymentSuccess;
use App\Exceptions\NotAuthorizeException;
use App\Helpers\PaymentLinkHelper;
use App\Services\Payment\CollectorTargetSideEffect;
use App\Models\AdditionalFile;
use App\Models\Claim;
use App\Models\Discount;
use App\Models\ElmRequest;
use App\Models\Ivr;
use App\Models\MaqsamCallback;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Models\StatusChangeRequest;
use App\Services\Claim\ClaimActionOptions;
use App\ThirdParty\PAYMENT\PaymentInterface;
use Carbon\Carbon;
use Exception;
use http\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class PaymentService
{
    public function createPayment(Claim $claim)
    {

        try {
           $link = generateUniqueCode(Payment::class, 'link', 7);
            // $link = substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 6)), 0, 6) ;
            $payment = Payment::create([
                'claim_id' => $claim->id,
                'amount' => $claim->amount,
                'verification_code' => random_int(100000, 999999),
                'link' => $link,

            ]);

            $request = [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'due_date' => Carbon::today(),
            ];

            $this->createPaymentLink($request);
        } catch (Exception $e) {
            throw new NotAuthorizeException($e->getMessage());
        }
    }

    public function createPaymentLink($request, $partial_payment = false, $order = 1)
    {

        try {
            $verificationCode = random_int(100000, 999999);

            // Create a new payment link with the 6-character link
            $payment_link = PaymentLinkHelper::createUnique(
                paymentId: $request['payment_id'],
                amount: $request['amount'],
                dueDate: $request['due_date'],
                verificationCode: $verificationCode
            );

            if (! $partial_payment) {
                $messageService = new MessageService;
                $message = $messageService->convertTemplate(MessageTemplates::APPROVE->value, $payment_link->id);
                $claim = $payment_link->payment->claim;

                if (!empty(trim($claim->debtor_phone))) {

                    $smsService = new SmsService;
                    $smsService->sendSms($claim->debtor_phone, $message, $claim->id, MessageTemplates::APPROVE->value, $payment_link->id, template_model_id: $payment_link->id);

                    try {
                        if (config('app.have_ivr') === true) {
                            try {
                                $response = $smsService->ivr($payment_link, getIVR(IVRs::APPROVE->value));
                                if (!isset($response['error'])) {
                                    IVR::create([
                                        'payment_link_id' => $payment_link->id,
                                        'status' => $response['status'],
                                        'callId' => $response['callId'],
                                        'referenceId' => $response['referenceId'],
                                        'audio_id' => getIVR(IVRs::APPROVE->value),
                                        'type' => IVRs::APPROVE->value,
                                    ]);
                                }
                            } catch (Exception $e) {
                                $msg = $e->getMessage() . ' ' . json_encode($response);
                                throw new NotAuthorizeException($msg);
                            }
                        }
                    } catch (Exception $e) {
                        throw new NotAuthorizeException($e->getMessage());
                    }
                } else {
                    // send elm message
                    $elmService = new ElmService($payment_link);
                    $elmService->sendElmMessage($message);
                   
                }

                $payment_link->send_status = LinkSendStatus::SENT->value;
            }

            $payment_link->save();

            return $payment_link;
        } catch (Exception $e) {
            
            throw new NotAuthorizeException($e->getMessage());
        }
    }

    public function updatePaymentLink($request)
    {

        try {

            $payment = Payment::find($request['payment_id']);

            $payment_link = PaymentLink::find($request['link_id']);

            $old_date = Carbon::createFromDate($payment_link->due_date);

            $remaining_amount = $payment->amount - $payment->paid_amount;

            if ($remaining_amount < $request['amount']) {

                throw new NotAuthorizeException('the amount is greater than');
            }

            $payment_link->payment_id = $request['payment_id'];

            $new_due_date = Carbon::createFromDate($request['due_date']);

            $payment_link->due_date = $new_due_date;

            $payment_link->amount = $request['amount'];

            $payment_link->save();

            $remaining_amount -= $payment_link->amount;

            $links = PaymentLink::where('payment_id', $request['payment_id'])
                ->whereIn('send_status', [LinkSendStatus::NOT_SENT->value,  LinkSendStatus::SENT->value])
                ->whereNot('id', $request['link_id'])
                ->get();

            if ($links->count() != 0) {
                $installment = $remaining_amount / $links->count();

                foreach ($links as $link) {
                    $link->amount = $installment;
                    $link->save();
                }

                $claim = $payment_link->payment->claim;
                $claim->status = ClaimStatus::PARTIAL->value;
                $claim->save();
            } else {
                if ($new_due_date->ne($old_date)) {
                    $messageService = new MessageService;
                    $message = $messageService->convertTemplate(MessageTemplates::DELAY_PAYEMNT->value, $payment_link->id);

                    (new SmsService)->sendSms($payment_link->payment->claim->debtor_phone, $message, $payment_link->payment->claim_id, MessageTemplates::DELAY_PAYEMNT->value, $payment_link->id, template_model_id: $payment_link->id);

                    $payment_link->send_status = LinkSendStatus::NOT_SENT->value;
                    $payment_link->save();

                    $claim = $payment_link->payment->claim;
                    $claim->status = ClaimStatus::DELAY->value;

                    $claim->save();
                }
            }

            $claim_service = new ClaimService;

            return $claim_service->info($payment->claim_id);
        } catch (Exception $e) {
            throw new NotAuthorizeException($e->getMessage());
        }
    }

    // TODO add payments gateway and send sms
    // TODO create event to make claim done
    public function payPayment($request)
    {
        try {
            $payment_link = PaymentLink::find($request->query('UserField1'));

            $service = new SmsService;

            /** @var PaymentInterface $className */
            $className = $service->returnClassName('payment');
            $is_successful = $className->isSuccessful($request->query());
            $transactionRef = $className->transactionRef();

            $transaction = PaymentTransaction::where('transaction_ref', $request->query()[$transactionRef])
                ->first();
            $transaction->update([
                'send_status' => $is_successful ? PaymentTransactionStatus::PAID->value : PaymentTransactionStatus::FAILED->value,
                'amount' => $payment_link->amount,
                'response' => json_encode($request->query()),
            ]);

            if ($payment_link->send_status == LinkSendStatus::CANCELED->value && ! $is_successful) {
                return false;
            }

            $payment = Payment::where('id', $payment_link->payment_id)
                ->first();

            $claim = Claim::where('id', $payment->claim_id)->first();

            if ($is_successful) {
                $payment->paid_amount = $payment->paid_amount + $payment_link->amount;

                if ($payment->amount <= $payment->paid_amount) {
                    $payment->payment_status = PaymentStatus::PAID->value;

                    $claim->status = ClaimStatus::DONE->value;

                    $claim->save();

                    StatusChangeRequest::query()->where('claim_id', $claim->id)->update([
                        'request_status' => RequestStatus::REJECTED->value,
                    ]);
                } else {
                    $claim->status = ClaimStatus::PARTIAL->value;

                    $claim->save();
                }
            }

            // this mean the user pay all the amount
            $payment_link->send_status = $is_successful ? LinkSendStatus::PAID : LinkSendStatus::FAILED;
            $payment_link->views_count = $payment_link->views_count + 1;
            $payment_link->last_access = Carbon::today()->format('Y-m-d');
            $payment_link->collector_id = $claim->assignee?->to ?? null; // store the current collector user

            $payment_link->save();

            $payment->save();

            if ($is_successful) {
                CollectorTargetSideEffect::apply(
                    $claim,
                    (int) $payment_link->amount,
                    'pay_payment'
                );
            }

            // raise payment success event if transaction is success
            if ($is_successful) {
                $generateLetters = new GenerateLetters($transaction);
                $generateLetters->execute();

                NotificationService::create([
                    'title_ar' => __('notification.paid_installment', [], 'ar'),
                    'title_en' => __('notification.paid_installment', [], 'en'),
                    'description_ar' => __('notification.paid_installment_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
                    'description_en' => __('notification.paid_installment_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
                    'regarding' => NotificationType::PAID_INSTALLMENT->value,
                    'user_id' => $claim->assignee?->to,
                    'to' => json_encode([$claim->assignee?->to]),
                    'links' => json_encode([
                        [
                            'title_ar' => ' انشاء فاتورة',
                            'title_en' => ' letter generation ',
                            'url' => 'claims/' . $claim?->id . '/info',
                        ],
                    ]),
                ]);
            }

            // TODO raise payment failed event if transaction is failed

            return $payment;
        } catch (Exception $e) {
            throw new NotAuthorizeException($e->getMessage());
        }
    }

    public function listTransaction($claim_id)
    {
        return Payment::with('links.transactions')
            ->where('claim_id', $claim_id)
            ->orderBy('id', 'DESC')
            ->paginate(10);
    }

    /**
     * @deprecated 1.0.0 No longer used by internal code and not recommended.
     */
    public function createInstallments($request)
    {
        $installment_count = $request['installment_count'];

        $payment_id = $request['payment_id'];

        $payment = Payment::find($payment_id);

        $has_links = PaymentLink::where('payment_id', $payment_id)->count() > 1;

        if ($has_links) {
            throw new NotAuthorizeException('payment installments already created');
        }

        $date = Carbon::today();

        PaymentLink::where('payment_id', $payment_id)->delete();

        $installments = [];

        try {

            for ($i = 0; $i < $installment_count; $i++) {

                $installment = [];

                $installment['payment_id'] = $payment_id;

                $installment['due_date'] = $date;

                $installment['amount'] = $payment->amount / $installment_count;

                $installments[] = $this->createPaymentLink($installment, true, $i + 1);

                $date->addMonth();
            }

            $message = hasMessages() ? 'عزيزي العميل نفيدكم انه تم الموافقة على التسوية بواقع (' . $installment_count . ') دفعات حيث سيتم ارسال رابط الدفع لكم دوريا ابتداء من تاريخ ( ' . $date->subMonths($installment_count)->format('d-m-Y') . ' ) '
                : 'test';

            (new SmsService)->sendSms($payment->claim->debtor_phone, $message, $payment->claim_id);

            $claim = $payment->claim;
            $claim->status = ClaimStatus::PARTIAL->value;
            $claim->save();

            $claim_service = new ClaimService;

            return $claim_service->info($payment->claim_id);

            // return $installments;
        } catch (Exception $e) {
            throw new NotAuthorizeException($e->getMessage());
        }
    }

    public function updateDueDateInstallment($request)
    {

        try {

            $payment_link = PaymentLink::where('id', $request['link_id'])->first();
            $payment_link->due_date = $request['due_date'];

            $payment_link->save();

            $messageService = new MessageService;
            $message = $messageService->convertTemplate(MessageTemplates::DELAY_PAYEMNT->value, $payment_link->id);

            (new SmsService)->sendSms($payment_link->payment->claim->debtor_phone, $message, $payment_link->payment->claim_id, $payment_link);

            (new SmsService)->sendSms($payment_link->payment->claim->debtor_phone, $message, $payment_link->payment->claim_id);

            $claim = $payment_link->payment->claim;
            $claim->status = ClaimStatus::DELAY->value;
            $claim->save();

            return response()->json([
                'error' => false,
                'message' => 'updated successfully',
            ]);
        } catch (Exception $e) {
            throw new NotAuthorizeException($e->getMessage());
        }
    }

    public function updateInstallments($request)
    {
        $request_amount = collect($request['links_id'])->sum('amount');

        $links = collect($request['links_id']);

        $link = Payment::find($links->first()['payment_id']);

        if ($link->amount != $request_amount) {
            throw new NotAuthorizeException("installments amount doesn't match claim amount");
        }

        if ($links->unique('payment_id')->count() > 1) {
            throw new NotAuthorizeException("there are some payments installment doesn't belong to this installment");
        }

        $links = [];
        foreach ($request['links_id'] as $link) {
            $link_model = PaymentLink::where('id', $link['id'])
                ->first();

            $link_model->update([
                'amount' => $link['amount'],
                'due_date' => $link['due_date'],
            ]);

            $theUpdatedData = $link_model->refresh();

            $links[] = $theUpdatedData;
        }

        return $links;
    }

    public function createNewInstallments($installments, $payment_id)
    {
        try {
            $payment = Payment::findOrFail($payment_id);
            $amountDoesMatch = round(collect($installments)->sum('amount'), 2) == round($payment->amount, 2);
            if (collect($installments)->isEmpty() || !$amountDoesMatch) {
                throw new Exception('There is no installments or amount does not match payment amount');
                
                return false;
            }
                
            $is_full_payment = PaymentLink::where('payment_id', $payment_id)->where('send_status', '<>', LinkSendStatus::CANCELED->value)->max('order') == 1;


            DB::beginTransaction();
            PaymentLink::where('payment_id', $payment_id)->whereNotIn('id', collect($installments)
                ->pluck('id'))->update([
                'send_status' => LinkSendStatus::CANCELED->value,
            ]);
            foreach ($installments as $installment) {
                if (isset($installment['amount']) && $installment['amount'] != 0) {
                    $verificationCode = $payment->links()->where('type', PaymentType::FULL_PAYMENT->value)->first()?->verification_code;
                    if ($installment['id'] == 0) {
                        $payment_link = PaymentLinkHelper::createUnique(
                            paymentId: $payment_id,
                            amount: $installment['amount'],
                            dueDate: $installment['date'],
                            type: PaymentType::INSTALLMENT_PAYMENT->value,
                            order: $installment['order'],
                            verificationCode: $verificationCode,
                        );
                    } else {
                        if ($installment['status'] != LinkSendStatus::PAID->value) {
                            $payment_link = PaymentLink::findOrFail($installment['id']);

                            if ($payment_link->send_status == LinkSendStatus::PAID->value) {
                                throw new Exception(__('claim.some_installment_is_already_paid'));

                                return false;
                            }

                            $payment_link->update([
                                'due_date' => $installment['date'],
                                'amount' => $installment['amount'],
                                'order' => $installment['order'],
                                'send_status' => LinkSendStatus::NOT_SENT->value,
                                'type' => PaymentType::INSTALLMENT_PAYMENT->value,
                                'verification_code' => $verificationCode,
                            ]);
                        }
                    }
                }
            }

            if ($is_full_payment) {
                $messageService = new MessageService;
                $message = $messageService->convertTemplate(MessageTemplates::PARTIAL_APPROVE->value, $payment->claim_id);
                $smsService = new SmsService;
                $smsService->sendSms($payment->claim->debtor_phone, $message, $payment->claim_id, MessageTemplates::PARTIAL_APPROVE->value, template_model_id: $payment->claim_id);

                if (config('app.have_ivr') === true) {
                    $payment_link = PaymentLink::where('payment_id', $payment_id)->latest()->first();
                    $response = $smsService->ivr($payment_link, getIVR(IVRs::PARTIAL_ACCEPT->value));
                    if (!isset($response['error'])) {
                        IVR::create([
                            'payment_link_id' => $payment_link->id,
                            'status' => $response['status'],
                            'callId' => $response['callId'],
                            'referenceId' => $response['referenceId'],
                            'audio_id' => getIVR(IVRs::PARTIAL_ACCEPT->value),
                            'type' => IVRs::PARTIAL_ACCEPT->value,
                        ]);
                    }
                }

                $claim = $payment->claim;
                $claim->status = ClaimStatus::PARTIAL->value;
                $claim->save();

                // Ensure payment status stays PENDING
                $payment->paid_amount = 0;
                $payment->payment_status = PaymentStatus::PENDING->value;
                $payment->save();
            }
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            throw new Exception($exception->getMessage());
        }
    }

    public function updateInstallmentsPlan(array $installments, int $payment_id): void
    {
        try {
            $payment = Payment::findOrFail($payment_id);

            $submittedTotal = round(collect($installments)->sum('amount'), 2);
            $paidAmount = (float) PaymentLink::where('payment_id', $payment_id)
                ->where('send_status', LinkSendStatus::PAID->value)
                ->sum('amount');
            $remainingAmount = round(((float) $payment->amount) - $paidAmount, 2);

            if (collect($installments)->isEmpty() || $submittedTotal !== $remainingAmount) {
                throw new Exception('There is no installments or amount does not match remaining amount');
            }

            // Validate dates strictly increase by order
            $sorted = collect($installments)->sortBy('order')->values();
            if ($sorted->pluck('order')->duplicates()->isNotEmpty()) {
                throw new Exception('Duplicate installment order values are not allowed.');
            }
            for ($i = 1; $i < $sorted->count(); $i++) {
                $prev = Carbon::parse($sorted[$i - 1]['date']);
                $curr = Carbon::parse($sorted[$i]['date']);
                if (! $curr->gt($prev)) {
                    throw new Exception('Installment with order ' . $sorted[$i]['order'] . ' must have a date after order ' . $sorted[$i - 1]['order'] . '.');
                }
                // ensure sequential order
                if ((int)$sorted[$i]['order'] !== ((int)$sorted[$i - 1]['order'] + 1)) {
                    throw new Exception('Installment order must be sequential without gaps. Expected ' . ((int)$sorted[$i - 1]['order'] + 1) . ' but got ' . (int)$sorted[$i]['order'] . '.');
                }
            }

            DB::beginTransaction();

            // Cancel only links that are not PAID and not included in the update payload
            PaymentLink::where('payment_id', $payment_id)
                ->whereNotIn('id', collect($installments)->pluck('id'))
                ->whereIn('send_status', [
                    LinkSendStatus::NOT_SENT->value,
                    LinkSendStatus::SENT->value,
                    LinkSendStatus::FAILED->value,
                    LinkSendStatus::DISPUTE_PAYMENT->value,
                    LinkSendStatus::SKIPPED->value,
                ])
                ->update([
                    'send_status' => LinkSendStatus::CANCELED->value,
                ]);

            foreach ($installments as $installment) {
                if (!isset($installment['amount']) || (float) $installment['amount'] == 0.0) {
                    continue;
                }

                $verificationCode = $payment->links()->where('type', PaymentType::FULL_PAYMENT->value)->first()?->verification_code;

                if (($installment['id'] ?? 0) == 0) {
                    $payment_link = PaymentLinkHelper::createUnique(
                        paymentId: $payment_id,
                        amount: $installment['amount'],
                        dueDate: $installment['date'],
                        type: PaymentType::INSTALLMENT_PAYMENT->value,
                        sendStatus: LinkSendStatus::NOT_SENT->value,
                        order: $installment['order'],
                        verificationCode: $verificationCode
                    );
                } else {
                    $payment_link = PaymentLink::findOrFail($installment['id']);

                    if ($payment_link->send_status == LinkSendStatus::PAID->value) {
                        throw new Exception(__('claim.some_installment_is_already_paid'));
                    }

                    $payment_link->update([
                        'due_date' => $installment['date'],
                        'amount' => $installment['amount'],
                        'order' => $installment['order'],
                        'send_status' => LinkSendStatus::NOT_SENT->value,
                        'type' => PaymentType::INSTALLMENT_PAYMENT->value,
                        'verification_code' => $verificationCode,
                    ]);
                }
            }

            // Ensure claim status stays PARTIAL
            $claim = $payment->claim;
            if ($claim->status !== ClaimStatus::PARTIAL->value) {
                $claim->status = ClaimStatus::PARTIAL->value;

                $claim->save();
            }

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            throw new Exception($exception->getMessage());
        }
    }

    public function addDiscount($request)
    {
        try {
            $payment = Payment::find($request['payment_id'])->update([
                'amount' => $request['discount'],
            ]);

            return $payment;
        } catch (Exception $exception) {
            throw new NotAuthorizeException($exception->getMessage());
        }
    }

    public function addDelayPayment($request)
    {
        try {
            $due_date = Carbon::parse($request['date_to_delay'])->format('d-m-Y');

            PaymentLink::where('payment_id', $request['payment_id'])
                ->whereIn('send_status', [LinkSendStatus::NOT_SENT->value, LinkSendStatus::SENT->value, LinkSendStatus::FAILED->value])
                ->update([
                    'send_status' => LinkSendStatus::CANCELED->value,
                ]);

            $order = PaymentLink::where('payment_id', $request['payment_id'])
                ->whereIn('send_status', [LinkSendStatus::CANCELED->value])
                ->min('order');

            $payment_link = PaymentLinkHelper::createUnique(
                paymentId: $request['payment_id'],
                amount: $request['remaining_amount'],
                dueDate: $request['date_to_delay'],
                type: 'delay_payment',
                order: $order
            );

            $messageService = new MessageService;
            $message = $messageService->convertTemplate(MessageTemplates::DELAY_PAYEMNT->value, $payment_link->id);

            $smsService = new SmsService;
            $smsService->sendSms($payment_link->payment->claim->debtor_phone, $message, $payment_link->payment->claim_id, MessageTemplates::DELAY_PAYEMNT->value, $payment_link->id, template_model_id: $payment_link->id);

            if (config('app.have_ivr') === true) {
                $response = $smsService->ivr($payment_link, getIVR(IVRs::DELAY_ACCEPT->value));
                if (!isset($response['error'])) {
                    IVR::create([
                        'payment_link_id' => $payment_link->id,
                        'status' => $response['status'],
                        'callId' => $response['callId'],
                        'referenceId' => $response['referenceId'],
                        'audio_id' => getIVR(IVRs::DELAY_ACCEPT->value),
                        'type' => IVRs::DELAY_ACCEPT->value,
                    ]);
                }
            }

            $claim = $payment_link->payment->claim;
            $claim->status = ClaimStatus::DELAY->value;

            $claim->save();

            return $payment_link;
        } catch (Exception $exception) {
            throw new NotAuthorizeException($exception->getMessage());
        }
    }

    public function cancelPayment($payment)
    {
        $discount = Discount::where('claim_id', $payment->claim_id)->first();
        if ($discount) {
            $request = $discount->request;
            if ($request) {
                $request->delete();
            }
            $discount->delete();
        }

        $payment_links = PaymentLink::where('payment_id', $payment->id)
            ->whereIn('send_status', [LinkSendStatus::NOT_SENT->value, LinkSendStatus::SENT->value, LinkSendStatus::FAILED->value])
            ->get();

         $is_full_payment = false;
         $first_link = $payment_links->first() ?? null;
         foreach ($payment_links as $payment_link) {
             if ($payment_link->type == PaymentType::DELAY_PAYMENT->value || $payment_link->type == PaymentType::FULL_PAYMENT->value) {
                 $payment_link->amount = $payment->amount;
                 $payment_link->save();
                 $is_full_payment = true;
             } else {
                 $payment_link->send_status = LinkSendStatus::CANCELED->value;
                 $payment_link->save();
             }
         }

         if (! $is_full_payment && $first_link) {
             $first_link->send_status = LinkSendStatus::NOT_SENT->value;
             $first_link->type = PaymentType::FULL_PAYMENT->value;
             $first_link->amount = $payment->amount;
             $first_link->save();
         }


        //fire event for claim info
        event(new UpdateClaimInstallmentEvent($payment->claim_id));
    }

    /**
     * Delete all installments from a payment.
     * Can only delete if there are no paid installments.
     * Cancels all payment links and converts to FULL_PAYMENT.
     *
     * @param int $paymentId
     * @return bool
     * @throws Exception
     */
    public function deleteInstallments($paymentId, $user, ClaimActionOptions $options)
    {
        try {
            $payment = Payment::findOrFail($paymentId);
            $claim = $payment->claim;

            //  any paid installments in the entire payment
            $paidInstallments = PaymentLink::where('payment_id', $paymentId)
                ->where('send_status', LinkSendStatus::PAID->value)
                ->count();

            if ($paidInstallments > 0) {
                throw new Exception('Cannot delete installments because there are paid installments');
            }
           
            $clianService = new ClaimService;
            if (!$clianService->createRequestStatus($claim->id, ClaimStatus::DELETE_INSTALLMENT->value, ['note'=>'Delete Installments'], null, 'installment_payments', 'payments',$user , $options)) {

                // Cancel all payment links
                PaymentLink::where('payment_id', $paymentId)
                    ->update([
                        'send_status' => LinkSendStatus::CANCELED->value,
                    ]);

                // set the first payment link  to FULL_PAYMENT
                $firstLink = PaymentLink::where('payment_id', $paymentId)
                    ->orderBy('id', 'asc')
                    ->first();

                if ($firstLink) {
                    $firstLink->send_status = LinkSendStatus::NOT_SENT->value;
                    $firstLink->type = PaymentType::FULL_PAYMENT->value;
                    $firstLink->amount = $payment->amount;

                    $firstLink->save();
                } else {
                    // create new if not exist
                    $firstLink = PaymentLinkHelper::createUnique(
                        paymentId: $paymentId,
                        amount: $payment->amount,
                        dueDate: Carbon::today(),
                        type: PaymentType::FULL_PAYMENT->value
                    );
                }


                event(new UpdateClaimInstallmentEvent($firstLink?->payment?->claim->id));

                return true;
            }
                NotificationService::create([
                    'title_ar' => __('notification.delete_installment_payment_request', [], 'ar'),
                    'title_en' => __('notification.delete_installment_payment_request', [], 'en'),
                    'description_ar' => __('notification.you_have_delete_installment_payment_request_for_claim', [], 'ar') . '( ' . $payment?->claim?->claim_number . ' )  ',
                    'description_en' => __('notification.you_have_delete_installment_payment_request_for_claim', [], 'en') . '( ' . $payment?->claim?->claim_number . ' )  ',
                    'regarding' => NotificationType::PERMISSION_REQUEST->value,
                    'user_id' => auth()->user()?->id,
                    'to' => json_encode([auth()->user()->supervisor_id]),
                    'links' => json_encode([
                        [
                            'title_ar' => 'الطلبات المفتوحة',
                            'title_en' => 'Open requests',
                            'url' => 'requests',
                        ],
                    ]),
                ]);

            return false;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }



    public function generateObligationLetter($claim,$user)
    {

        app()->setLocale('ar');

        $client_name = strtolower(config('app.client_name'));
        $relative = "obligation-letter/{$client_name}/{$claim->id}/voucher.pdf";
        $file_path = 'public/' . $relative;

        $clientPdfView = "pdf.{$client_name}.obligation-letter";
        $defaultPdfView = "pdf.default.obligation-letter";

        $pdfView = view()->exists($clientPdfView) ? $clientPdfView : $defaultPdfView;
        // Generate PDF
        Pdf::view($pdfView, [
            'claim' => $claim,
        ])
            ->disk('local')
            ->withBrowsershot(function (Browsershot $browsershot) {

                // Local environment (no env values)
                if (! config('app.puppeteer.puppeteer_node_binary')) {
                    $browsershot->noSandbox();
                    return;
                }

                // Server environment
                $browsershot->timeout(60000);
                $browsershot->setNodeBinary(config('app.puppeteer.puppeteer_node_binary'));
                $browsershot->setNpmBinary(config('app.puppeteer.puppeteer_npm_binary'));
                $browsershot->setChromePath(config('app.puppeteer.puppeteer_chrome_path'));
                $browsershot->noSandbox();
                $browsershot->setOption('newHeadless', true);
            })
            ->save($file_path);

        $file = (new FilesUploader)->registerLocalFile($relative, [
            'source'            => FileSource::OBLIGATION_LETTERS,
            'original_filename' => 'voucher.pdf',
            'uploaded_by'       => $user->id,
        ]);

        return AdditionalFile::updateOrCreate(
            [
                'claim_id' => $claim->id,
                'type' => AdditionalFileType::VOUCHER->value
            ],
            [
                'title' => 'Proof of Debt Vourcher for Claim Number' . $claim->claim_number,
                'url' => $relative,
                'file_id' => $file->id,
                'claim_id' => $claim->id,
                'note' => 'generated voucher for claim number ' . $claim->claim_number,
                'uploaded_by' => $user->id,
                'type' => AdditionalFileType::VOUCHER->value,
                'attachment_status' => FileVisibility::PUBLISHED->value
            ]
        );
    }
}
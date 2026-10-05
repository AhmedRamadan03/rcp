<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Enums\LinkSendStatus;

use App\Enums\MessageTemplates;
use App\Enums\NotificationType;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RequestStatus;
use App\Enums\Roles;
use App\Events\ClaimInfo\UpdateClaimInstallmentEvent;
use App\Models\ClaimStatusHistory;
use App\Models\Discount;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Models\RequestApproval;
use App\Models\StatusChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class DiscountService
{
    public function addDiscount($request)
    {
        try {
            $discount = Discount::create([
                'claim_id' => $request['claim_id'],
                'payment_id' => $request['payment_id'],
                'total_amount' => round($request['total_amount'], 2),
                'amount_after_discount' => round($request['amount_after_deduction'], 2),
                'discount_percentage' => $request['percentage'],
                'deducted_amount' => round($request['deducted_amount'], 2),
            ]);

            return $this->executeOrRequestDiscount($discount, $request['note'] ?? '', auth()->user());

        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

    }

    public function executeDiscount($discount)
    {
        try {
            //            $discount->payment->update([
            //                'amount' => $discount->amount_after_discount,
            //            ]);

            $payment_link = $discount->payment->links()->where('send_status', '<>', LinkSendStatus::CANCELED->value)
                ->where('send_status', '<>', LinkSendStatus::PAID->value)
                ->orderBy('id', 'desc')
                ->first();
            //
            //            $payment_link->amount = $discount->payment->amount;
            //            $payment_link->save();

            ClaimService::recalculateAfterDiscount($discount->payment, $discount);

            //            $this->recalculateClaim($discount->payment);

            $discount->update([
                'is_active' => 1,
            ]);

            $payment = $discount->payment;
            
          
            //fire event for claim info
           
            event(new UpdateClaimInstallmentEvent($discount->claim->id));




            $this->message = uiMessage(__('claim.discount_has_been_added_successfully'), 'success');
            // $payment?->claim?->debtor_phone
            //            $discount_message = 'عزيزي العميل نفيدك بالموافقة على طلب الخصم. يرجى سداد المبلغ عن طريق الرابط ادناه. ( '.url('/claim-info/'.$payment?->links?->first()?->link).' ) وشكراً';

            $messageService = new MessageService;
            $message = $messageService->convertTemplate(MessageTemplates::DISCOUNT_APPROVE->value, $payment_link->id);

            (new SmsService)->sendSms($payment_link->payment->claim->debtor_phone, $message, $payment_link->payment->claim_id, MessageTemplates::DISCOUNT_APPROVE->value, $payment_link->id, template_model_id: $payment_link->id);

            //            $status_request = StatusChangeRequest::where([
            //                'claim_id' => $discount->claim_id,
            //                'to_status' => ClaimStatus::DISCOUNT->value,
            //            ])->first();
            //
            //            $status_request->request_status = RequestStatus::APPROVED->value;
            //            $status_request->save();

            return true;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    /*
     * this function is reusable in case of the discount is exceeding the supervisor discount allowed it will rerun on approve and update
     * the same request but with supervisor of the supervisor and so many
     */
    public function executeOrRequestDiscount($discount, $note, $user)
    {
        try {

            $discount_limit = auth()->user()->effective_discount_percentage;

            if ( $discount->discount_percentage <= $discount_limit) {
                 $request=[
                    'note' => $note,
                ];
                
                $this->writeToClaimStatusHistory( $discount->claim_id, $discount->claim?->status,ClaimStatus::DISCOUNT->value, auth()->user()->id,$request);
                
                return $this->executeDiscount($discount);
            } else {

            if (!$user->supervisor_id || !auth()->user()->supervisor_id) {
                throw new \Exception('You cannot request discounts beyond your limit. No supervisor assigned to your account.');
                }
                $status_request = StatusChangeRequest::updateOrCreate([
                    'claim_id' => $discount->claim_id,
                    'user_id' => $user->id,
                    'to_status' => ClaimStatus::DISCOUNT->value,
                ], [
                    'claim_id' => $discount->claim_id,
                    'user_id' => $user->id,
                    'supervisor_id' => auth()->user()->supervisor_id,
                    'from_status' => $discount->claim->status,
                    'to_status' => ClaimStatus::DISCOUNT->value,
                    'request_status' => RequestStatus::REGISTERED->value,
                    'additional_file_id' => null,
                    'note' => $note,
                ]);
                if($status_request->user_id == auth()->user()->id){

                    RequestApproval::create([
                       'user_id' => auth()->user()->id,
                       'request_id' => $status_request->id,
                       'status' => RequestStatus::REGISTERED->value,
                       'notes' => $note,
                       'approved_at' => now()
                    ]);
                }else{
                    RequestApproval::create([
                       'user_id' => auth()->user()->id,
                       'request_id' => $status_request->id,
                       'status' => RequestStatus::APPROVED->value. '/' . 'moved to supervisor',
                       'notes' => $note,
                       'approved_at' => now()
                    ]);
                    $supervisor = auth()->user()->supervisor;
                    
                    NotificationService::create([
                    'title_ar' => __('notification.request_escalated_title', [], 'ar'),
                    'title_en' => __('notification.request_escalated_title', [], 'en'),
                    'description_ar' => __('notification.request_escalated_desc', [
                            'supervisor_name' => $supervisor?->name,
                        ], 'ar'),

                        'description_en' => __('notification.request_escalated_desc', [
                            'supervisor_name' => $supervisor?->name,
                        ], 'en'),
                    'regarding' => NotificationType::PERMISSION_REQUEST->value,
                    'user_id' => auth()->user()?->id,
                    'to' => json_encode([$status_request->user_id]),
                    'links' => json_encode([
                        [
                            'title_ar' => 'الطلبات المفتوحة',
                            'title_en' => 'Open requests',
                            'url' => 'requests',
                        ],
                    ]),
                ]);

                }

                $discount->request_id = $status_request->id;
                $discount->save();

                $request=[
                    'note' => $note,
                ];

                $this->writeToClaimStatusHistory( $discount->claim_id, $discount->claim?->status,ClaimStatus::DISCOUNT->value, auth()->user()->id,$request);

                $notification = NotificationService::create([
                    'title_ar' => __('notification.discount_request', [], 'ar'),
                    'title_en' => __('notification.discount_request', [], 'en'),
                    'description_ar' => __('notification.you_have_discount_request_for_claim', [], 'ar').'( '.$discount->claim->claim_number.' )  ',
                    'description_en' => __('notification.you_have_discount_request_for_claim', [], 'en').'( '.$discount->claim->claim_number.' )  ',
                    'regarding' => NotificationType::GENERAL->value,
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
                $user = User::find(auth()->user()->supervisor_id);
                if ($user->role_id == Roles::SUPER_ADMIN->value) {
                    try {
                       // Mail::to($user->email)->send(new \App\Mail\SuperadminRequestMail($notification));
                    } catch (\Exception $e) {
                        dd($e->getMessage());
                    }
                }

                return false;
            }
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }
    public function writeToClaimStatusHistory($claim_id, $previous_status, $current_status, $user_id,$request=[])
    {
        $claimStatusHistory = new ClaimStatusHistory();
        $claimStatusHistory->claim_id = $claim_id;
        $claimStatusHistory->previous_status = $previous_status;
        $claimStatusHistory->current_status = $current_status;
        $claimStatusHistory->user_id = $user_id;
        if ($request) {
             $url = isset($request['url']) ? $request['url'][0] : '';
            $claimStatusHistory->note = $request['note']??'';
            $claimStatusHistory->reason = $request['other_reason']??'';
            $claimStatusHistory->file = $url;
        }

        $claimStatusHistory->save();

        return true;
    }

    public function cancelDiscount($discount)
    {
        try {
            //            $discount->payment->update([
            //                'amount' => $discount->claim->amount,
            //            ]);

            //            $payment_link = $discount->payment->links()->where('send_status', '<>', LinkSendStatus::CANCELED->value)
            //                ->where('send_status', '<>', LinkSendStatus::PAID->value)
            //                ->orderBy('id', 'desc')
            //                ->first();
            //
            //            $payment_link->amount = $discount->payment->amount;
            //            $payment_link->save();

            ClaimService::reverseDiscount($discount->payment, $discount);


            $discount->update([
                'is_active' => 0,
                'updated_at' => now(),
            ]);
            $discount->refresh();

            StatusChangeRequest::where([
                'claim_id' => $discount->claim_id,
                'to_status' => ClaimStatus::DISCOUNT->value,
            ])->delete();

                
            event(new UpdateClaimInstallmentEvent($discount->claim->id));


            // Send discount cancellation SMS  
            $messageService = new MessageService;
            $message = $messageService->convertTemplate(
                MessageTemplates::DISCOUNT_CANCEL->value,
                $discount->id
            );

            (new SmsService)->sendSms($discount->claim->debtor_phone, $message, $discount->claim_id, MessageTemplates::DISCOUNT_CANCEL->value);


            return true;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }
}

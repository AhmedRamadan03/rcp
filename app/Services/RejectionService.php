<?php

namespace App\Services;

use App\Enums\Clients;
use App\Enums\FileSource;
use App\Enums\IVRs;
use App\Enums\MessageTemplates;
use App\Enums\NotificationType;
use App\Enums\RejectStatus;
use App\Enums\RejectType;
use App\Enums\Roles;
use App\Events\ClaimInfo\UpdateClaimRequestEvent;
use App\Events\General\UpdateRequestEvent;
use App\Exceptions\NotExistsException;
use App\Models\Claim;
use App\Models\Ivr;
use App\Models\MaqsamCallback;
use App\Models\MessageTemplate;
use App\Models\RejectedClaim;
use App\Models\User;
use Carbon\Carbon;
use Exception;

class RejectionService
{
    public function list($filters = [], $paginate = 10)
    {
        return $this->buildRejectedClaimsQueryFilter($filters)->paginate($paginate);
    }

    public function export($filters = [], ?int $userId = null)
    {
        return $this->buildRejectedClaimsQueryFilter($filters, $userId)->without('attachments');
    }

    public function create($rejection)
    {

        $role_id = User::find($rejection['user_id'])
            ->role_id;

        $rejection['type'] = $role_id == Roles::EMPLOYEE->value ? RejectType::EMPLOYEE->value : RejectType::USER->value;

        return RejectedClaim::create($rejection)->refresh();

    }

    public function createDebtorRejection($rejection)
    {
        $claim = Claim::find($rejection['claim_id'])->load('assignee.toUser');
        $rejection['user_id'] = $claim->assignee?->to;
        $rejection['supervisor_id'] = $claim->assignee?->toUser?->supervisor_id ?? $claim->assignee?->to ;
        
        $rejection['type'] = RejectType::USER->value;


        $files = $rejection['files'] ?? [];

        unset($rejection['files']);

        $rejectionCreated = RejectedClaim::create($rejection)->refresh();
        event(new UpdateRequestEvent());
        NotificationService::create([
            'title_ar' => __('notification.notice_for_client_request', [], 'ar'),
            'title_en' => __('notification.notice_for_client_request', [], 'en'),
            'description_ar' => __('notification.notice_for_client_request_for_claim', [], 'ar').'( '.$claim->claim_number.' )  ',
            'description_en' => __('notification.notice_for_client_request_for_claim', [], 'en').'( '.$claim->claim_number.' )  ',
            'regarding' => NotificationType::CLIENT_REQUEST->value,
            'user_id' => $rejection['user_id'],
            'to' => json_encode([$rejection['user_id']]),
            'links' => json_encode([
                [
                    'title_ar' => 'الطلبات المفتوحة',
                    'title_en' => 'Open requests',
                    'url' => 'client-requests',
                ],
            ]),
        ]);

        if ($files) {
            $rejection['rejection_id'] = $rejectionCreated->id;
            $rejection['files'] = $files;

            return $this->uploadRejectionFiles($rejection);
        }

        return $rejectionCreated;
    }

    public function uploadRejectionFiles($rejection)
    {
        $directory = 'rejected-claims/' . Carbon::today()->year;
        $rejectionModel = RejectedClaim::find($rejection['rejection_id']);

        $files = (new FilesUploader)->upload($rejection['files'], [
            'source'    => FileSource::REJECTED_CLAIMS,
            'directory' => $directory,
        ]);

        $paths = $files->map(fn ($file) => $file->local_path ?? $file->cloud_path)->all();

        foreach ($files as $file) {
            $rejectionModel->attachments()->attach($file->id, [
                'file_path' => $file->local_path ?? $file->cloud_path,
            ]);
        }

        return $rejectionModel->update([
            'files' => $paths,
        ]);
    }

    public function acceptRejection($rejection_id)
    {

        try {

            $rejection = RejectedClaim::findOrFail($rejection_id);

            if ($rejection->status != RejectStatus::NOT_SEEN->value) {
                throw new NotExistsException('You can not accept rejection that is not in not seen state');
            }

            //            if (auth()->user()->role_id != Roles::SUPERVISOR->value){
            //                throw new NotExistsException("You are not supervisor you can not accept this rejection");
            //            }

            // if (auth()->user()->id != $rejection->supervisor_id){
            //     throw new NotExistsException("You are not the one who create this rejection");
            // }

            $rejection
                ->update([
                    'status' => RejectStatus::ACCEPTED->value,
                    'approved_by' => auth()->user()->id
                ]);
              

            event(new UpdateClaimRequestEvent($rejection->claim_id));
            $messageTemplate = MessageTemplate::find(MessageTemplates::APPROVE_DEBTOR->value);
            if (isTemplateExists($messageTemplate)) {
                $messageService = new MessageService;
                $message = $messageService->convertTemplate(MessageTemplates::APPROVE_DEBTOR->value, $rejection->id);
                $smsService = new SmsService;
                $smsService->sendSms($rejection->claim->debtor_phone, $message, $rejection->claim_id, MessageTemplates::APPROVE_DEBTOR->value, template_model_id: $rejection->id);

                /**
                 * IVR
                 */
                $claim = Claim::find($rejection->claim_id);
                $payment_link = $claim->payments->first()->links->first();

//                if (config('app.client_name') === Clients::MIC->value) {
//                    if (config('app.have_ivr') === true) {
//                        $phone = '+966'.substr($payment_link->payment->claim->debtor_phone, -9);
//
//                        $mq = (new MaqsamService)->inquiry([
//                            'To' => $phone,
//                            'InquiryUrl' => 'https://developers.taheiya.sa/maqsam/audios/'.IVRs::toString(IVRs::DEBTOR_REQUEST_ACCEPT).'.mp3',
////                            'InquiryUrl' => config('app.url') . '/maqsam/audios/'.IVRs::toString(IVRs::DEBTOR_REQUEST_ACCEPT).'.mp3',
//                        ]);
//
//                        MaqsamCallback::create([
//                            'InquiryId' => $mq->body(),
//                            'type' => IVRs::DEBTOR_REQUEST_ACCEPT->value,
//                            'payment_link_id' => $payment_link->id,
//                        ]);
//                    }
                    if (config('app.have_ivr') === true) {

                        try {
                            
                            $response = $smsService->ivr($payment_link, getIVR(IVRs::DEBTOR_REQUEST_ACCEPT->value));
                            if (!isset($response['error'])) {
                            IVR::create([
                                'payment_link_id' => $payment_link->id,
                                'status' => $response['status'],
                                'callId' => $response['callId'],
                                'referenceId' => $response['referenceId'],
                                'audio_id' => getIVR(IVRs::DEBTOR_REQUEST_ACCEPT->value),
                                'type' => IVRs::DEBTOR_REQUEST_ACCEPT->value,
                            ]);
                            }
                        }
                        catch (\Exception $e) {
                            throw new NotExistsException($e->getMessage());
                        }
                    }
//                }
            }

            return $rejection;
        } catch (Exception $e) {
            throw new NotExistsException($e->getMessage());
        }

    }

    public function rejectRejection($rejection_id)
    {
        try {

            $rejection = RejectedClaim::findOrFail($rejection_id);

            if ($rejection->status == RejectStatus::REJECTED->value) {
                throw new NotExistsException('You can not reject Request that is already rejected');
            }
            if ($rejection->status == RejectStatus::CANCELLED->value) {
                throw new NotExistsException('You can not reject Request that is already Cancelled');
            }

            //            if (auth()->user()->role_id != Roles::SUPERVISOR->value){
            //                throw new NotExistsException("You are not supervisor you can not reject this rejection");
            //            }

            $rejection
                ->update([
                    'status' => RejectStatus::REJECTED->value,
                    'approved_by' => auth()->user()->id
                ]);


                event(new UpdateClaimRequestEvent($rejection->claim_id));

            $messageTemplate = MessageTemplate::find(MessageTemplates::REJECT_DEBTOR->value);
            if (isTemplateExists($messageTemplate)) {
                $messageService = new MessageService;
                $message = $messageService->convertTemplate(MessageTemplates::REJECT_DEBTOR->value, $rejection->id);
                $smsService = new SmsService;
                $smsService->sendSms($rejection->claim->debtor_phone, $message, $rejection->claim_id, MessageTemplates::REJECT_DEBTOR->value, template_model_id: $rejection->id);

                /**
                 * IVR
                 */
                $claim = Claim::find($rejection->claim_id);
                $payment_link = $claim->payments->first()->links->first();

//                if (config('app.client_name') === Clients::MIC->value) {
//                    if (config('app.have_ivr') === true) {
//                        $phone = '+966'.substr($payment_link->payment->claim->debtor_phone, -9);
//
//                        $mq = (new MaqsamService)->inquiry([
//                            'To' => $phone,
//                            'InquiryUrl' => 'https://developers.taheiya.sa/maqsam/audios/'.IVRs::toString(IVRs::DEBTOR_REQUEST_REJECT).'.mp3',
////                            'InquiryUrl' => config('app.url') . '/maqsam/audios/'.IVRs::toString(IVRs::DEBTOR_REQUEST_REJECT).'.mp3',
//                        ]);
//
//                        MaqsamCallback::create([
//                            'InquiryId' => $mq->body(),
//                            'type' => IVRs::DEBTOR_REQUEST_REJECT->value,
//                            'payment_link_id' => $payment_link->id,
//                        ]);
//                    }
                    if (config('app.have_ivr') === true) {

                        $response = $smsService->ivr($payment_link, getIVR(IVRs::DEBTOR_REQUEST_REJECT->value));
                        if (!isset($response['error'])) {
                        IVR::create([
                            'payment_link_id' => $payment_link->id,
                            'status' => $response['status'],
                            'callId' => $response['callId'],
                            'referenceId' => $response['referenceId'],
                            'audio_id' => getIVR(IVRs::DEBTOR_REQUEST_REJECT->value),
                            'type' => IVRs::DEBTOR_REQUEST_REJECT->value,
                        ]);
                        }
                    }
//                }
            }

            return $rejection;
        } catch (Exception $e) {
            throw new NotExistsException($e->getMessage());
        }
    }

    private function buildRejectedClaimsQueryFilter(array $filters = [], ?int $userId = null)
    {
        $userId = $userId ?? auth()->id();
        $user = User::findOrFail($userId);

        return RejectedClaim::with(['createdBy', 'supervisorUser', 'claim', 'attachments'])
            ->latest()

            // if user cannot view all reports, scope to user's own/assigned claims
            ->when($user->cannot('showReports', Claim::class), function ($query) use ($userId) {
                $query->where(function ($subQuery) use ($userId) {
                    $subQuery->whereHas('claim.assignees', function ($query) use ($userId) {
                        $query->where('to', $userId)
                            ->where('is_active', 1);
                    });
                    $subQuery->orWhere('supervisor_id', $userId);
                });
            })

            // filter by claim ids
            ->when(isset($filters['ids']), function ($qu) use ($filters) {
                $qu->whereIn('claim_id', $filters['ids']);
            })

            // filter by claim id
            ->when(isset($filters['claim_id']), function ($qu) use ($filters) {
                $qu->where('claim_id', $filters['claim_id']);
            })

            // filter by claim_number
            ->when(isset($filters['claim_number']), function ($qu) use ($filters) {
                $qu->whereHas('claim', function ($query) use ($filters) {
                    $query->where('claim_number', 'ILIKE', '%' . $filters['claim_number'] . '%');
                });
            })

            // filter by reason
            ->when(isset($filters['reason']), function ($qu) use ($filters) {
                $qu->where('reason', 'ILIKE', '%' . $filters['reason'] . '%');
            })

            // filter by status (array)
            ->when(isset($filters['status']), function ($qu) use ($filters) {
                $qu->whereIn('status', $filters['status']);
            })


            // filter by approved_by
            ->when(isset($filters['approved_by']), function ($qu) use ($filters) {
                $qu->whereHas('approvedBy', function ($q) use ($filters) {
                    $q->where('name', 'ILIKE', '%' . $filters['approved_by'] . '%');
                });
            })
            // search filter
            ->when(isset($filters['search']), function ($qu) use ($filters) {
                $search = $filters['search'];
                $qu->where(function ($sub) use ($search) {
                    $sub->where('reason', 'ILIKE', '%' . $search . '%')
                        ->orWhereHas('claim', function ($q) use ($search) {
                            $q->where('claim_number', 'ILIKE', '%' . $search . '%')
                                ->orWhere('id', 'ILIKE', '%' . $search . '%');
                        })
                        ->orWhereHas('approvedBy', function ($q) use ($search) {
                            $q->where('name', 'ILIKE', '%' . $search . '%');
                        });
                });
            });
    }

}

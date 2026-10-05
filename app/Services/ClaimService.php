<?php

namespace App\Services;

use App\Actions\GenerateLetters;
use App\BusinessLogic\PaymentReminder\PaymentReminder;
use App\Enums\AdditionalFileType;
use App\Enums\ClaimStatus;
use App\Enums\DebtorType;
use App\Enums\EscalationType;
use App\Enums\FileSource;
use App\Enums\FileVisibility;
use App\Enums\LinkSendStatus;
use App\Enums\MessageTemplates;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PermissionRequestsStatus;
use App\Enums\RequestStatus;
use App\Enums\Roles;
use App\Enums\TransactionType;
use App\Events\ClaimInfo\UpdateClaimInstallmentEvent;
use App\Events\ClaimInfo\UpdateClaimTransactiontEvent;
use App\Events\Payment\PaymentSuccess;
use App\Exceptions\AleardyApproved;
use App\Exceptions\NotAuthorizeException;
use App\Helpers\PaymentLinkHelper;
use App\Services\Payment\CollectorTargetSideEffect;
use App\Models\AdditionalFile;
use App\Models\User;
use App\Models\Assigning;
use App\Models\ChangeClaimAmountRequest;
use App\Models\Claim;
use App\Models\ClaimAmountHistory;
use App\Models\ClaimComment;
use App\Models\ClaimFile;
use App\Models\ClaimStatusHistory;
use App\Models\CloseClaim;
use App\Models\CloseReason;
use App\Models\Discharge;
use App\Models\Discount;
use App\Models\Escalation;
use App\Models\LawDepartmentClaim;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentRequest;
use App\Models\PaymentTransaction;
use App\Models\RejectedClaim;
use App\Models\Setting;
use App\Models\StatusChangeRequest;
use App\Models\Voucher;
use App\Services\Claim\ClaimActionOptions;
use App\Traits\ClaimFilters;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class ClaimService
{
    use ClaimFilters;
    public function info($id)
    {
        return Claim::with(
            'client',
            'createdBy',
            'messages',
            'calls',
            'claimFiles',
            'upload_file',
            'comments',
            'city',
            // 'assignees.from_user',
            // 'assignees.to_user',
            // 'delegated_users',
            // 'rejections',
            // 'assignee',
            'payments.links.ivrs'
        )
            ->where('id', $id)->get()->first();
    }
    public function accountClaimList($filters = [], $withPagination = true, $perPage = 10, $query = false, $user = null)
    {
        $claims = Claim::with('assignees', 'assignees.toUser');

        $this->applyAccountFilters($claims, $filters);

        return $this->handleResult($claims, $withPagination, $perPage, $query);
    }
    public function listExportForAccount($filters = [], $withPagination = true, $perPage = 10, $query = false, $user = null)
    {
        $claims = Claim::with('assignees', 'assignees.toUser');

        $this->applyAccountFilters($claims, $filters, true);

        return $this->handleResult($claims, $withPagination, $perPage, $query);
    }
    public function list($filters = [], $withPagination = true, $perPage = 10, $query = false, $user = null)
    {
        $user = $user ?? auth()->user();
        $claims = $this->baseQuery()->visibleTo($user);

        $this->applyAllFilters($claims, $filters);
        $this->applyBaseConditions($claims, $filters, $user);

        return $this->handleResult($claims, $withPagination, $perPage, $query);
    }

    public function listForCollector($filters = [], $withPagination = true, $perPage = 10, $query = false, $user = null)
    {
        return $this->list($filters, $withPagination, $perPage, $query, $user);
    }

    public function listForSeniorCollector($filters = [], $withPagination = true, $perPage = 10, $query = false, $user = null)
    {
        return $this->list($filters, $withPagination, $perPage, $query, $user);
    }

    public function listForExternalOffice($filters = [], $withPagination = true, $perPage = 10, $query = false, $user = null)
    {
        return $this->list($filters, $withPagination, $perPage, $query, $user);
    }

    public function listForLaw($filters = [], $withPagination = true, $perPage = 10, $query = false, $user = null)
    {
        return $this->list($filters, $withPagination, $perPage, $query, $user);
    }

    public function create($claim): Claim
    {
        return Claim::create($claim)->refresh();
    }

    public function edit($claim, $request)
    {
        $claim->update($request);
        if ($claim->payments->count() != 0 && $claim->amount != $claim->payments->first()->amount) {
            $previous_amount = $claim->payments->first()->amount;
            $claim->payments()->update([
                'amount' => $claim->amount,
            ]);

            $link = $claim->payments()->first()->links()->whereIn('send_status', [LinkSendStatus::SENT->value, LinkSendStatus::NOT_SENT->value])->first();
            $link->amount = ($claim->amount - $previous_amount) + $link->amount;
            $link->save();
        }
    }

    public function accept(Claim $claim , User $user)
    {
        try {
            $previous_status = $claim->status;
            if ($claim->claimFiles->count() == 0) {
                throw new NotAuthorizeException('you have to upload claim files first');
            }

            if ($claim->status == ClaimStatus::APPROVED->value) {

                throw new AleardyApproved('This claim is already approved');
            }
            

            DB::transaction(function () use ($claim, $user, $previous_status) {
                if ($claim->assignees()->count() == 0) {
                    Assigning::create([
                        'claim_id' => $claim->id,
                        'to' => $user->id,
                        'supervisor_id' => $user->id,
                        'is_active' => 1,
                        'assign_date' => Carbon::now(),
                    ]);
                }

                (new PaymentService)->createPayment($claim);
                (new PaymentService)->generateObligationLetter($claim, $user);

                if ($claim->debtor_phone) {
                    $claim->status = ClaimStatus::APPROVED->value;
                } else {
                    $claim->status = ClaimStatus::TRANSFER_TO_ELM->value;
                }
                $claim->is_draft = false;
                $claim->save();

                $this->writeToClaimStatusHistory($claim->id, $previous_status, $claim->status, $user->id);
            });
            return $claim->fresh();
        } catch (NotAuthorizeException $exception) {
            throw new NotAuthorizeException($exception->getMessage());
        } catch (Exception $exception) {
            throw new NotAuthorizeException($exception->getMessage());
        }
    }

    public function uploadClaimFile(array $request, \App\Models\File $file): ClaimFile
    {
        if (! isset($request['fileAccess'])) {
            $request['fileAccess'] = $request['file'];
        }

        try {
            return ClaimFile::create([
                'claim_id' => $request['claim_id'],
                'title'    => $request['title'],
                'access'   => $request['fileAccess'],
                'url'      => $file->local_path ?? $file->cloud_path,
                'file_id'  => $file->id,
            ]);
        } catch (Exception $e) {
            throw new NotAuthorizeException($e->getMessage());
        }
    }


    public function updateClaimFileVisibility($claim_id, $file_id, $new_access)
    {

        $claimFile = ClaimFile::where('id', $file_id)
            ->where('claim_id', $claim_id)
            ->firstOrFail();

        $claimFile->update([
            'access' => $new_access
        ]);

        return $claimFile->fresh();
    }

    public function generateDischargeLetter($id)
    {
        try {
            DB::beginTransaction();

            $canGenerateLetters = SettingsService::canGenerateLetters();
            $claim = Claim::findOrFail($id);


            if (!$canGenerateLetters) {
                throw new Exception("Discharge letter generation is currently disabled by system settings.");
            }

            if (! $claim->isCollected) {
                throw new Exception("The claim has not been fully paid yet.");
            }

            // if (isset($claim->discharge?->url)) {
            //     throw new Exception("Discharge letter already exists");
            // }

            if ($claim->is_close !== 0) {
                throw new Exception("The claim is already closed.");
            }

            $fileName  = time() . '-' . 'discharge.pdf';
            $relative  = 'discharge/' . $id . '/' . $fileName;
            $file_url  = '/public/' . $relative;

            $discharge = Discharge::create([
                'claim_id' => $id,
                'url'      => $file_url,
            ]);
            $claim->load('discharge');

            Discharge::where('claim_id', $id)->where('id', '!=', $discharge->id)->update([
                'is_valid' => false,
            ]);


            $client_name = strtolower(config('app.client_name'));

            $clientPdfView = "pdf.{$client_name}.discharge-letter";
            $defaultPdfView = "pdf.default.discharge-letter";

            $pdfView = view()->exists($clientPdfView) ? $clientPdfView : $defaultPdfView;

            Pdf::view($pdfView, [
                'claim' => $claim,
                'discharge' => $discharge,
            ])
                ->disk('local')
                ->withBrowsershot(function (Browsershot $browsershot) {
                    // $browsershot->setNodeBinary('/usr/local/bin/node')
                    //    ->setChromePath('/var/www/html/.cache/puppeteer/chrome/linux-126.0.6478.182/chrome-linux64/chrome')
                    //    ->setNpmBinary('/usr/local/bin/npm');
                    if (! config('app.puppeteer.puppeteer_node_binary')) {
                        $browsershot->noSandbox();

                        return;
                    }
                    $browsershot->setNodeBinary(config('app.puppeteer.puppeteer_node_binary'));
                    $browsershot->setNpmBinary(config('app.puppeteer.puppeteer_npm_binary'));
                    $browsershot->setChromePath(config('app.puppeteer.puppeteer_chrome_path'));
                    $browsershot->noSandbox();
                    // $browsershot->timeout(300000000000)
                    //                    $browsershot->setNodeBinary("/opt/cpanel/ea-nodejs20/bin/node");
                    //                    $browsershot->setNpmBinary("/opt/cpanel/ea-nodejs20/bin/npm");
                })
                ->save($file_url);

            $file = (new FilesUploader)->registerLocalFile($relative, [
                'source'            => FileSource::DISCHARGE_FILES,
                'original_filename' => $fileName,
                'uploaded_by'       => auth()->id(),
            ]);
            $discharge->forceFill(['file_id' => $file->id])->save();

            if (isset($claim->assignee?->supervisor_id)) {

                $notification = NotificationService::create([
                    'title_ar' => __('notification.discharge_generated', [], 'ar'),
                    'title_en' => __('notification.discharge_generated', [], 'en'),
                    'description_ar' => __('notification.notice_for_discharge_generated', [], 'ar') . '( ' . $claim?->claim_number . ' )  ',
                    'description_en' => __('notification.notice_for_discharge_generated', [], 'en') . '( ' . $claim?->claim_number . ' )  ',
                    'regarding' => NotificationType::DISCHARGE_LETTER->value,
                    'user_id' => $claim->assignee->supervisor_id,
                    'to' => json_encode([$claim->assignee->supervisor_id]),
                    'links' => json_encode([
                        [
                            'title_ar' => 'الطلبات المفتوحة',
                            'title_en' => 'Open requests',
                            'url' => 'claims/' . $claim?->id . '/info'
                        ],
                    ]),
                ]);
            }

            DB::commit();

            return $claim;
        } catch (Exception $exception) {
            DB::rollBack();

            throw new NotAuthorizeException($exception->getMessage());
        }
    }

    public function sendDischargeLetter($claim_id)
    {
        try {

            $claim = Claim::with('discharge')->findOrFail($claim_id);

            if (!isset($claim->discharge?->url)) {
                throw new Exception("Discharge letter not generated yet");
            }

            if (!$claim->debtor_phone) {
                throw new Exception("Debtor phone number is required");
            }

            $smsService = new SmsService;
            $messageService = new MessageService;
            $message = $messageService->convertTemplate(
                MessageTemplates::DISCHARGE->value,
                $claim->discharge->id
            );
            $smsService->sendSms($claim->debtor_phone, $message, $claim->id, MessageTemplates::DISCHARGE->value, template_model_id: $claim->discharge->id);

            return [
                'claim' => [
                    'id' => $claim->id,
                    'claim_number' => $claim->claim_number,
                    'created_by' => $claim->createdBy->name,
                    'assigned_to' => $claim->assignee->toUser->name,
                    'debtor_name' => $claim->debtor_name,
                    'debtor_phone' => $claim->debtor_phone
                ],
                'message_sent' => true,

            ];
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getDischargeLetter($claim_id)
    {
        try {
            $claim = Claim::findOrFail($claim_id);

            if (!isset($claim->discharge?->url)) {
                throw new Exception("Discharge letter not generated yet");
            }
            $claim->load('discharge');
            return route('download', ['key' => $claim->discharge->file?->uuid ?? $claim->discharge->url]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

  
    
    public function CollectedOutSystem($claim_id, $request, $additional_file)
    {
        try {
            $claim = Claim::find($claim_id);
            if ($claim->status !== ClaimStatus::REGISTERED->value) {
                throw new Exception('Claim is not outstanding');
            }

            if (!$this->createRequestStatus($claim_id, ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value, $request, $additional_file, 'collected_out_of_system', 'claim_status')) {
                $claim->update([
                    'status' => ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value,
                ]);

                // update additional file 'attachment_status'
                if ($additional_file) {

                    $additional_file->attachment_status = FileVisibility::PUBLISHED->value;
                    $additional_file->save();
                }

                return true;
            }

            return false;
        } catch (Exception $exception) {
            throw new NotAuthorizeException($exception->getMessage());
        }
    }

    public function cancel($claim_id, $request)
    {
        //        if(!$this->createRequestStatus($claim_id, ClaimStatus::REJECTED->value, $request, $additional_file)) {
        try {
            if ($request['reason_id'] == 0) {
                $request['close_id'] = CloseReason::create([
                    'reason' => $request['other_reason'],
                ])->id;
            }

            $url = isset($request['url']) ? $request['url'][0] : '';

            CloseClaim::create([
                'claim_id' => $claim_id,
                'reason_id' => $request['reason_id'],
                'url' => $url,
            ]);

            $claim = Claim::findOrFail($claim_id);
            $previous_status = $claim->status;

            $claim->update([
                'is_close' => 1,
                'status' => ClaimStatus::REJECTED->value,
            ]);

            $claim->refresh();

            $this->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::REJECTED->value, auth()->user()->id, $request);
            return $claim;
        } catch (Exception $exception) {
            throw new NotAuthorizeException($exception->getMessage());
        }
        //        }
    }

    public function manualCollection($claim_id, $request, $additional_file)
    {
        //        if (! $this->createRequestStatus($claim_id, ClaimStatus::MANUAL_COLLECTED->value, $request, $additional_file)) {
        try {
            $claim = Claim::findOrFail($claim_id);

            $previous_status = $claim->status;

            $payment = Payment::where('claim_id', $claim_id)->first();

            $payment->links()->where('send_status', '<>', LinkSendStatus::PAID->value)->update([
                'send_status' => LinkSendStatus::CANCELED->value,
            ]);

            if ($payment->amount != $payment->paid_amount) {
                $payment_link = PaymentLinkHelper::createUnique(
                    paymentId: $payment->id,
                    amount: $payment->amount - $payment->paid_amount,
                    dueDate: Carbon::today()->format('Y-m-d')
                );

                $transaction = PaymentTransaction::create([
                    'payment_id' => $payment_link->payment_id,
                    'link_id' => $payment_link->id,
                    'send_status' => PaymentTransactionStatus::PAID->value,
                    'amount' => $payment->amount - $payment->paid_amount,
                    'payment_type' => TransactionType::MANUAL->value,
                    'collector_id' => $claim->assignee?->to ?? null,
                    'paid_at'=> Carbon::now(),
                ]);

                $payment_link->send_status = LinkSendStatus::PAID->value;
                $payment_link->views_count = $payment_link->views_count + 1;
                $payment_link->last_access = Carbon::today()->format('Y-m-d');
                $payment_link->collector_id = $claim->assignee?->to ?? null; // store the current collector user

                $payment_link->save();
            }

            $payment->paid_amount = $payment->amount;
            $payment->payment_status = PaymentStatus::PAID->value;
            $payment->save();

            $claim->update([
                'status' => ClaimStatus::MANUAL_COLLECTED->value,
            ]);

            PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::MANUAL_COLLECTED->value), $claim_id);

            StatusChangeRequest::query()->where('claim_id', $claim->id)
                ->update([
                    'request_status' => RequestStatus::REJECTED->value,
                ]);

            $this->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::MANUAL_COLLECTED->value, auth()->user()->id, $request);

            if (isset($payment_link)) {
                CollectorTargetSideEffect::apply(
                    $claim,
                    (int) $payment_link->amount,
                    'claim_service_manual_collection'
                );
            }
            if ($transaction) {
                //                    event(new PaymentSuccess($transaction));
                $generateLetters = new GenerateLetters($transaction);
                $generateLetters->execute();
            }

            return true;
        } catch (Exception $exception) {
            throw new NotAuthorizeException($exception->getMessage());
        }
        //        }
        //        $claim = Claim::findOrFail($claim_id);
        //        NotificationService::create([
        //            'title_ar' => __('notification.status_change_request', [], 'ar'),
        //            'title_en' => __('notification.status_change_request', [], 'en'),
        //            'description_ar' => __('notification.you_have_change_status_request_for_claim', [], 'ar').'( '.$claim->claim_number.' )  ',
        //            'description_en' => __('notification.you_have_change_status_request_for_claim', [], 'en').'( '.$claim->claim_number.' )  ',
        //            'regarding' => NotificationType::GENERAL->value,
        //            'user_id' => auth()->user()?->id,
        //            'to' => json_encode([auth()->user()->supervisor_id]),
        //            'links' => json_encode([
        //                [
        //                    'title_ar' => 'الطلبات المفتوحة',
        //                    'title_en' => 'Open requests',
        //                    'url' => 'requests',
        //                ],
        //            ]),
        //        ]);

        //        return false;
    }

    // DEPRECATED
    public function bankCollection($claim_id, $request)
    {

        try {
            $claim = Claim::findOrFail($claim_id);

            $previous_status = $claim->status;

            $payment = Payment::where('claim_id', $claim_id)->first();

            $payment->links()->update([
                'send_status' => LinkSendStatus::CANCELED->value,
            ]);

            $payment_link = PaymentLinkHelper::createUnique(
                paymentId: $payment->id,
                amount: $payment->amount,
                dueDate: Carbon::today()->format('d-m-Y')
            );

            $transaction = PaymentTransaction::create([
                'payment_id' => $payment_link->payment_id,
                'link_id' => $payment_link->id,
                'send_status' => PaymentTransactionStatus::PAID->value,
                'amount' => $payment_link->amount,
                'response' => json_encode([]),
                'payment_type' => 'bank',
                'collector_id' => $claim->assignee?->to ?? null
            ]);

            $payment_link->send_status = LinkSendStatus::PAID->value;

            $payment_link->views_count = $payment_link->views_count + 1;

            $payment_link->last_access = Carbon::today()->format('Y-m-d');

            // store the current collector user
            $payment_link->collector_id = $claim->assignee?->to ?? null;
            $payment_link->save();

            $payment->paid_amount = $payment->amount;
            $payment->payment_status = PaymentStatus::PAID->value;
            $payment->save();

            $claim->update([
                'status' => ClaimStatus::BANK_COLLECTED->value,
            ]);

            $this->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::BANK_COLLECTED->value, auth()->user()->id);
        } catch (Exception $exception) {
            throw new NotAuthorizeException($exception->getMessage());
        }
    }

    public function credited($claim)
    {
        // if (! $this->createRequestStatus($claim->id, ClaimStatus::CREDITED->value, [], null))
        if (! $this->createRequestStatus(
            $claim->id,
            ClaimStatus::CREDITED->value,
            [],
            null,
            'collected_credited',  // اسم الصلاحية
            'claim_status'              // الـ path
        )) {
            $previous_status = $claim->status;

            $claim->status = ClaimStatus::CREDITED->value;
            $claim->save();

            PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::CREDITED->value), $claim->id);

            $this->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::CREDITED->value, auth()->user()->id);

            return true;
        }
        NotificationService::create([
            'title_ar' => __('notification.status_change_request', [], 'ar'),
            'title_en' => __('notification.status_change_request', [], 'en'),
            'description_ar' => __('notification.you_have_change_status_request_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
            'description_en' => __('notification.you_have_change_status_request_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
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

        return false;
    }

    public function defualt_on_payment($claim_id, $request)
    {
        $claim = Claim::findOrFail($claim_id);
        // if (! $this->createRequestStatus($claim_id, ClaimStatus::DEFAULT_IN_PAYMENT->value, $request, null))
        if (! $this->createRequestStatus(
            $claim->id,
            ClaimStatus::DEFAULT_IN_PAYMENT->value,
            $request,
            null,
            'default_in_payment_manual',  // اسم الصلاحية
            'claim_status'              // الـ path
        )) {

            $previous_status = $claim->status;

            $claim->status = ClaimStatus::DEFAULT_IN_PAYMENT->value;
            $claim->save();

            PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::DEFAULT_IN_PAYMENT->value), $claim_id);

            $this->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::DEFAULT_IN_PAYMENT->value, auth()->user()->id, $request);

            return true;
        }
        NotificationService::create([
            'title_ar' => __('notification.status_change_request', [], 'ar'),
            'title_en' => __('notification.status_change_request', [], 'en'),
            'description_ar' => __('notification.you_have_change_status_request_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
            'description_en' => __('notification.you_have_change_status_request_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
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

        return false;
    }

    public function transfer_to_legal($claim, $selected_legal_user, $request, $additional_file)
    {
        // if (! $this->createRequestStatus($claim->id, ClaimStatus::LAW_FIRM->value, $request, $additional_file)) {
        if (! $this->createRequestStatus(
            $claim->id,
            ClaimStatus::LAW_FIRM->value,
            $request,
            $additional_file,
            'transfer_to_legal',  // اسم الصلاحية
            'claim_status'              // الـ path
        )) {
            $previous_status = $claim->status;

            $claim->status = ClaimStatus::LAW_FIRM->value;

            $claim->save();

            (new AssigningService)->create([
                'to' => [$selected_legal_user],
                'claims_id' => [$claim->id],
            ]);
            // update additional file 'attachment_status'
            if ($additional_file) {
                $additional_file->attachment_status = FileVisibility::PUBLISHED->value;
                $additional_file->save();
            }

            //            $message = hasMessages() ? "نود اشعاركم بوجود مبلغ مستحق لـ الشركة الخليجية العامة للتأمين بقيمة " . $claim->amount . " ريال وذلك جراء الحادث الذي وقع بتاريخ " . Carbon::parse($claim->accident_date)->format('d-m-Y') . " رقم الحادث " . $claim->claim_number . " وبسبب عدم وجود تجاوب من طرفكم نفيدكم انه سيتم التصعيد الى الادارة القانونية لاتخاذ الاجراءات اللازمة لرفع دعوى قضائية امام الجهات المختصة. "
            //                : "test"
            //            ;

            $messageService = new MessageService;
            $message = $messageService->convertTemplate(MessageTemplates::TRANSFER_TO_LAWYER->value, $claim->id);

            (new SmsService)->sendSms($claim->debtor_phone, $message, $claim->id, MessageTemplates::TRANSFER_TO_LAWYER->value, template_model_id: $claim->id);

            NotificationService::create([
                'title_ar' => 'تم اضافة طلب جديد',
                'title_en' => 'New claim added to the list ',
                'description_ar' => 'تم تحويل المطالبة بالرفم ' . $claim->claim_number . ' و بمبلغ ' . $claim->amount . ' للإدارة القانونية.',
                'description_en' => 'Claim ' . $claim->claim_number . ' transliterated to legal department with amount ' . $claim->amount . ' SAR',
                'regarding' => NotificationType::LAW->value,
                'user_id' => auth()->user()?->id,
                'to' => json_encode(array_map('intval', [$selected_legal_user])),
                'links' => json_encode([
                    [
                        'label_ar' => 'عرض تفاصيل الطلب',
                        'label_en' => 'Open Claim Info',
                        'url' => 'claims/' . $claim?->id . '/info',
                    ],
                ]),
            ]);

            PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::LAW_FIRM->value), $claim->id);

            $this->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::LAW_FIRM->value, auth()->user()->id, $request);

            return true;
        }
        NotificationService::create([
            'title_ar' => __('notification.status_change_request', [], 'ar'),
            'title_en' => __('notification.status_change_request', [], 'en'),
            'description_ar' => __('notification.you_have_change_status_request_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
            'description_en' => __('notification.you_have_change_status_request_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
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

        return false;
    }

    // public function createRequestStatus($claim_id, $status, $request = null, $additional_file = null)
    public function createRequestStatus($claim_id, $status, $request = null, $additional_file = null, string $permission_key = null, string $permission_path = null, object $user = null, ?ClaimActionOptions $options = null)
    {
        $user ??= auth()->user();
        $options ??= ClaimActionOptions::systemApi();

        // if ($user->role_id == Roles::COLLECTOR->value || $user->role_id == Roles::EXTERNAL_OFFICE->value) {
        $needs_approval = false;

        // Deliberate: Partner API (requiresApproval=false) bypasses BOTH the
        // approval workflow AND the underlying permission grant check.
        // The API credential itself is the authorization boundary — the
        // internal user's RCP permissions are not consulted for Partner API
        if ($permission_key && $permission_path && $options->requiresApproval) {
            $perm = get_permission_value($permission_path, $permission_key, $user);
            if ($perm['granted'] == 0) {
                throw new Exception('ليس لديك صلاحية لهذا الإجراء');
            }

            $needs_approval = $options->requiresApproval && $perm['with_approve'] == 1;
        }
        try {
            if ($needs_approval) {

                if (!$user->supervisor_id) {
                    throw new Exception(__('request.supervisor_not_assigned'));
                }

                $claim = Claim::findOrFail($claim_id);

                $has_opened_request_to_status = StatusChangeRequest::where('request_status', RequestStatus::REGISTERED->value)
                    ->where('claim_id', $claim_id)
                    ->where('to_status', '!=', ClaimStatus::DISCOUNT->value)
                    ->count() > 0;

                if ($has_opened_request_to_status) {
                    throw new Exception(__('request.there_are_already_opened_request'));
                }

                $status_request = StatusChangeRequest::create([
                    'claim_id' => $claim_id,
                    'user_id' => $user->id,
                    'supervisor_id' => $user->supervisor_id,
                    'from_status' => $claim->status,
                    'to_status' => $status,
                    'request_status' => RequestStatus::REGISTERED->value,
                    'additional_file_id' => $additional_file ? $additional_file->id : null,
                ]);

                if ($status == ClaimStatus::PARTIAL->value) {
                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();

                    foreach ($request['installments'] as $installment) {
                        PaymentRequest::create([
                            'request_id' => $status_request->id,
                            'link_id' => $installment['id'],
                            'payment_id' => $request['payment_id'],
                            'link_amount' => $installment['amount'],
                            'link_status' => LinkSendStatus::NOT_SENT->value,
                            'link_order' => $installment['order'],
                            'link_date' => $installment['date'],
                        ]);
                    }

                }elseif($status == ClaimStatus::DELETE_INSTALLMENT->value) {
                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();
                    $payment = Payment::where('claim_id', $claim_id)->first();
                    $installments = $payment->links()->get();
                    foreach ($installments as $installment) {
                        PaymentRequest::create([
                            'request_id' => $status_request->id,
                            'link_id' => $installment->id,
                            'payment_id' => $payment->id,
                            'link_amount' => $installment->amount,
                            'link_status' => LinkSendStatus::NOT_SENT->value,
                            'link_order' => $installment->order,
                            'link_date' => $installment->due_date,
                        ]);
                    }


                } elseif ($status == ClaimStatus::DELAY->value) {

                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();

                    PaymentRequest::create([
                        'request_id' => $status_request->id,
                        'link_id' => 0,
                        'payment_id' => $request['payment_id'],
                        'link_amount' => $request['remaining_amount'],
                        'link_status' => LinkSendStatus::NOT_SENT->value,
                        'link_order' => 1,
                        'link_date' => $request['date_to_delay'],
                    ]);
                } elseif ($status == ClaimStatus::CLOSED->value) {
                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();
                    
                    NotificationService::create([
                        'title_ar' => __('notification.status_change_request', [], 'ar'),
                        'title_en' => __('notification.status_change_request', [], 'en'),
                        'description_ar' => __('notification.you_have_change_status_request_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
                        'description_en' => __('notification.you_have_change_status_request_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
                        'regarding' => NotificationType::GENERAL->value,
                        'user_id' => $user?->id,
                        'to' => json_encode([$user->supervisor_id]),
                        'links' => json_encode([
                            [
                                'title_ar' => 'الطلبات المفتوحة',
                                'title_en' => 'Open requests',
                                'url' => 'requests',
                            ],
                        ]),
                    ]);
                } elseif ($status == ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value) {
                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();
                    NotificationService::create([
                        'title_ar' => __('notification.status_change_request', [], 'ar'),
                        'title_en' => __('notification.status_change_request', [], 'en'),
                        'description_ar' => __('notification.you_have_change_status_request_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
                        'description_en' => __('notification.you_have_change_status_request_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
                        'regarding' => NotificationType::PERMISSION_REQUEST->value,
                        'user_id' => $user?->id,
                        'to' => json_encode([$user->supervisor_id]),
                        'links' => json_encode([
                            [
                                'title_ar' => 'الطلبات المفتوحة',
                                'title_en' => 'Open requests',
                                'url' => 'requests',
                            ],
                        ]),
                    ]);
                } elseif ($status == ClaimStatus::FOLLOW_UP->value) {
                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();
                } elseif ($status == ClaimStatus::DEFAULT_IN_PAYMENT->value) {
                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();
                } elseif ($status == ClaimStatus::MANUAL_COLLECTED->value) {
                    $status_request->note = $additional_file->note;
                    $status_request->save();
                     NotificationService::create([
                        'title_ar' => "لديك طلب تغيير حالة من الحالة " . __('claim.status.'. str_replace('/', '_', $claim->status) , [] , 'ar')   . " الي الحالة " . __('claim.status.'. str_replace('/', '_', $status) , [] , 'ar'),
                        "title_en" => "There are status change request from ". __('claim.status.'. str_replace('/', '_', $claim->status) , [] , 'en')." to " . __('claim.status.'. str_replace('/', '_', $status) , [] , 'en'),
                        'description_ar' => "قام المستخدم " . $user->name  . " بطلب تغيير الحالة",
                        "description_en" => "user  (".  $user->name . "), has requested change in status ",
                        "regarding" => NotificationType::PERMISSION_REQUEST->value,
                        "user_id" => $user?->id,
                        "to" => json_encode([$user->supervisor_id]),
                        "links" => json_encode([
                            [
                                'title_ar' => 'الطلبات المفتوحة',
                                'title_en' => 'Open requests',
                                'url' => 'requests'
                            ]
                        ])
                    ]);
                } elseif ($status == ClaimStatus::CLAIM_AMOUNT_CHANGE->value) {
                    $status_request->note = $request['note'] ?? null;

                    ChangeClaimAmountRequest::create([
                        'request_id' => $status_request->id,
                        'new_amount' => $request['new_amount'],
                        'current_amount' => $request['current_amount'],
                        'status' => RequestStatus::REGISTERED->value,
                    ]);
                    $status_request->save();

                    $notification = NotificationService::create([
                        'title_ar' => " لديك طلب تغيير في مبلغ المطالبة من " . $request['current_amount'] . " الي  " . $request['new_amount'],
                        "title_en" => "There are claim amount change request from " . $request['current_amount'] . " to " . $request['new_amount'],
                        'description_ar' => "قام المستخدم " . $user->name . " بطلب تغيير في مبلغ المطالبة",
                        "description_en" => "user  (" . $user->name . "), has requested claim change amount ",
                        "regarding" => NotificationType::PERMISSION_REQUEST->value,
                        "user_id" => $user?->id,
                        "to" => json_encode([$user->supervisor_id]),
                        "links" => json_encode([
                            [
                                'title_ar' => 'الطلبات المفتوحة',
                                'title_en' => 'Open requests',
                                'url' => 'requests'
                            ]
                        ])
                    ]);

                    return $status_request;
                } elseif ($status == ClaimStatus::LAW_FIRM->value) {
                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();

                    LawDepartmentClaim::create([
                        'claim_id' => $claim_id,
                        'user_id' => $request['selected_legal_user'] ?? $request['user_id'],
                        'request_id' => $status_request->id,
                    ]);
                    NotificationService::create([
                        'title_ar' => __('notification.status_change_request', [], 'ar'),
                        'title_en' => __('notification.status_change_request', [], 'en'),
                        'description_ar' => 'طلب تحويل المطالبة (' . $claim->claim_number . ') إلى الإدارة القانونية بحاجة إلى موافقتك',
                        'description_en' => 'Transfer request for claim (' . $claim->claim_number . ') to the legal department needs your approval',
                        'regarding' => NotificationType::PERMISSION_REQUEST->value,
                        'user_id' => $user?->id,
                        'to' => json_encode([$user->supervisor_id]),
                        'links' => json_encode([
                            [
                                'title_ar' => 'الطلبات المفتوحة',
                                'title_en' => 'Open requests',
                                'url' => 'requests',
                            ],
                        ]),
                    ]);

                } elseif ($status == ClaimStatus::MANUAL_COLLECTED_FOR_INSTALLMENT->value) {
                    $link = PaymentLink::find($request['link_id']);

                    PaymentRequest::create([
                        'request_id' => $status_request->id,
                        'link_id' => $link->id,
                        'payment_id' => $link->payment_id,
                        'link_amount' => $link->amount,
                        'link_status' => $link->send_status,
                        'link_order' => $link->order,
                        'link_date' => $link->due_date,
                    ]);
                } elseif ($status == PermissionRequestsStatus::SEND_DISCHARGE_LETTER->value) {
                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();

                    NotificationService::create([
                        'title_ar' => 'طلب إرسال مخالصة مالية',
                        'title_en' => 'Discharge Letter Send Request',
                        'description_ar' => 'طلب إرسال مخالصة مالية للمطالبة (' . $claim->claim_number . ')',
                        'description_en' => 'Request to send discharge letter for claim (' . $claim->claim_number . ')',
                        'regarding' => NotificationType::DISCHARGE_LETTER->value,
                        'user_id' => $user?->id,
                        'to' => json_encode([$user->supervisor_id]),
                        'links' => json_encode([
                            [
                                'title_ar' => 'الطلبات المفتوحة',
                                'title_en' => 'Open requests',
                                'url' => 'requests',
                            ]
                        ]),
                    ]);
                } elseif ($status == PermissionRequestsStatus::GENERATE_DISCHARGE_LETTER->value) {
                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();

                    NotificationService::create([
                        'title_ar' => 'طلب انشاء مخالصة مالية',
                        'title_en' => 'Discharge Letter Generate Request',
                        'description_ar' => 'طلب انشاء مخالصة مالية للمطالبة (' . $claim->claim_number . ')',
                        'description_en' => 'Request to generate discharge letter for claim (' . $claim->claim_number . ')',
                        'regarding' => NotificationType::DISCHARGE_LETTER->value,
                        'user_id' => $user?->id,
                        'to' => json_encode([$user->supervisor_id]),
                        'links' => json_encode([
                            [
                                'title_ar' => 'الطلبات المفتوحة',
                                'title_en' => 'Open requests',
                                'url' => 'requests',
                            ]
                        ]),
                    ]);
                } elseif ($status == PermissionRequestsStatus::GENERATE_VOUCHER_LETTER->value) {
                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();

                    $link = PaymentLink::findOrFail($request['link_id']);

                    PaymentRequest::create([
                        'request_id' => $status_request->id,
                        'link_id' => $link->id,
                        'payment_id' => $link->payment_id,
                        'link_amount' => $link->amount,
                        'link_status' => $link->send_status,
                        'link_order' => $link->order,
                        'link_date' => $link->due_date,
                    ]);


                    NotificationService::create([
                        'title_ar' => 'طلب انشاء سند قبض',
                        'title_en' => 'Voucher Letter Generate Request',
                        'description_ar' => 'طلب انشاء سند قبض  (' . $claim->claim_number . ')',
                        'description_en' => 'Request to generate Voucher letter (' . $claim->claim_number . ')',
                        'regarding' => NotificationType::VOUCHER_LETTER->value,
                        'user_id' => $user?->id,
                        'to' => json_encode([$user->supervisor_id]),
                        'links' => json_encode([
                            [
                                'title_ar' => 'الطلبات المفتوحة',
                                'title_en' => 'Open requests',
                                'url' => 'requests',
                            ]
                        ]),
                    ]);
                } elseif (in_array($status, [ClaimStatus::CREDITED->value])) {
                    $notification = NotificationService::create([
                        'title_ar' => "لديك طلب تغيير حالة من الحالة " . __('claim.status.'. str_replace('/', '_', $claim->status) , [] , 'ar')   . " الي الحالة " . __('claim.status.'. str_replace('/', '_', $status) , [] , 'ar'),
                        "title_en" => "There are status change request from ". __('claim.status.'. str_replace('/', '_', $claim->status) , [] , 'en')." to " . __('claim.status.'. str_replace('/', '_', $status) , [] , 'en'),
                        'description_ar' => "قام المستخدم " . $user->name  . " بطلب تغيير الحالة",
                        "description_en" => "user  (".  $user->name . "), has requested change in status ",
                        "regarding" => NotificationType::PERMISSION_REQUEST->value,
                        "user_id" => $user?->id,
                        "to" => json_encode([$user->supervisor_id]),
                        "links" => json_encode([
                            [
                                'title_ar' => 'الطلبات المفتوحة',
                                'title_en' => 'Open requests',
                                'url' => 'requests'
                            ]
                        ])
                    ]);

                   

                
                } elseif ($status == PermissionRequestsStatus::SEND_VOUCHER_LETTER->value) {
                    $status_request->note = $request['note'] ?? null;
                    $status_request->save();

                    $link = PaymentLink::findOrFail($request['link_id']);

                    PaymentRequest::create([
                        'request_id' => $status_request->id,
                        'link_id' => $link->id,
                        'payment_id' => $link->payment_id,
                        'link_amount' => $link->amount,
                        'link_status' => $link->send_status,
                        'link_order' => $link->order,
                        'link_date' => $link->due_date,
                    ]);


                    NotificationService::create([
                        'title_ar' => 'طلب ارسال سند قبض',
                        'title_en' => 'Voucher Letter Send Request',
                        'description_ar' => 'طلب ارسال سند قبض  (' . $claim->claim_number . ')',
                        'description_en' => 'Request to Send Voucher letter (' . $claim->claim_number . ')',
                        'regarding' => NotificationType::VOUCHER_LETTER->value,
                        'user_id' => $user?->id,
                        'to' => json_encode([$user->supervisor_id]),
                        'links' => json_encode([
                            [
                                'title_ar' => 'الطلبات المفتوحة',
                                'title_en' => 'Open requests',
                                'url' => 'requests',
                            ]
                        ]),
                    ]);
                }

                //                $notification = NotificationService::create([
                //                    'title_ar' => "لديك طلب تغيير حالة من الحالة " . __('claim.status.'. str_replace('/', '_', $claim->status) , [] , 'ar')   . " الي الحالة " . __('claim.status.'. str_replace('/', '_', $status) , [] , 'ar'),
                //                    "title_en" => "There are status change request from ". __('claim.status.'. str_replace('/', '_', $claim->status) , [] , 'en')." to " . __('claim.status.'. str_replace('/', '_', $status) , [] , 'en'),
                //                    'description_ar' => "قام المستخدم " . auth()->user()->name  . " بطلب تغيير الحالة",
                //                    "description_en" => "user  (".  auth()->user()->name . "), has requested change in status ",
                //                    "regarding" => NotificationType::GENERAL->value,
                //                    "user_id" => auth()->user()?->id,
                //                    "to" => json_encode([auth()->user()->supervisor_id]),
                //                    "links" => json_encode([
                //                        [
                //                            'title_ar' => 'الطلبات المفتوحة',
                //                            'title_en' => 'Open requests',
                //                            'url' => 'requests'
                //                        ]
                //                    ])
                //                ]);

                //            event(new NotificationEvent($notification));

                return $status_request;
            }
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }

        return  null;
       
    }

    public function to_follow_up($claim, $request)
    {
        if (! $this->createRequestStatus($claim->id, ClaimStatus::FOLLOW_UP->value, $request)) {
            $previous_status = $claim->status;

            $manual_transaction = $claim->payments->first()->transactions()->where('payment_type', TransactionType::MANUAL->value)->first();
            //        dd($manual_transaction);
            //        $manual_transaction = PaymentTransaction::whereBelongsTo($claim, 'payment.claim')->where('payment_type' , TransactionType::MANUAL->value)
            //        ->first()
            //        ;

            // Remove the manual collection
            if ($manual_transaction) {
                $payment = $manual_transaction->payment;

                $payment->paid_amount = $payment->paid_amount - $manual_transaction->amount;
                $payment->save();
                //            dd($payment);
                $manual_link = PaymentLink::find($manual_transaction->link_id);

                $manual_link->send_status = LinkSendStatus::NOT_SENT->value;
                $manual_link->save();

                try {
                    $targetService = new TargetService;

                    $targetService->removeFromAchieveTarget($claim->assignee->to, $manual_link->amount);
                } catch (Exception $exception) {
                }

                $manual_transaction->delete();
            }

            $claim->status = ClaimStatus::FOLLOW_UP->value;
            $claim->is_close = 0;
            $claim->save();

            PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::FOLLOW_UP->value), $claim->id);

            $this->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::FOLLOW_UP->value, auth()->user()->id);

            return true;
        }
        NotificationService::create([
            'title_ar' => __('notification.status_change_request', [], 'ar'),
            'title_en' => __('notification.status_change_request', [], 'en'),
            'description_ar' => __('notification.you_have_change_status_request_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
            'description_en' => __('notification.you_have_change_status_request_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
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

        return false;
    }

    public function manualCollectionForInstallment($claim_id, $request, $additional_file,$userId)
    {
        //        if (! $this->createRequestStatus($claim_id, ClaimStatus::MANUAL_COLLECTED_FOR_INSTALLMENT->value, $request, $additional_file)) {
        try {
            $payment = Payment::where('claim_id', $claim_id)->first();

            $payment_link = PaymentLink::findOrFail($request['link_id']);

            $claim = Claim::findOrFail($claim_id);

            $transaction = PaymentTransaction::create([
                'payment_id' => $payment_link->payment_id,
                'link_id' => $payment_link->id,
                'send_status' => PaymentTransactionStatus::PAID->value,
                'amount' => $payment_link->amount,
                'payment_type' => TransactionType::MANUAL->value,
                'collector_id' => $claim->assignee?->to ?? null,
                'paid_at'=> Carbon::now(),
            ]);

            $payment_link->send_status = LinkSendStatus::PAID->value;

            $payment_link->views_count = $payment_link->views_count + 1;

            $payment_link->last_access = Carbon::today()->format('Y-m-d');

            // store the current collector user
            $payment_link->collector_id = $claim->assignee?->to ?? null;
            $payment_link->save();
            event(new UpdateClaimInstallmentEvent($claim->id));
            $payment->paid_amount = $payment->paid_amount + $payment_link->amount;
            if ($payment->paid_amount >= $payment->amount) {
                $payment->payment_status = PaymentStatus::PAID->value;

                $previous_status = $claim->status;

                $claim->update([
                    'status' => ClaimStatus::MANUAL_COLLECTED->value,
                ]);
                //fire event for claim info
                event(new UpdateClaimInstallmentEvent($claim->id));

                PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::MANUAL_COLLECTED->value), $claim_id);

                StatusChangeRequest::query()->where('claim_id', $claim->id)
                    ->update([
                        'request_status' => RequestStatus::REJECTED->value,
                    ]);

                $this->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::MANUAL_COLLECTED->value, $userId);
            } else {
                $claim->status = ClaimStatus::PARTIAL->value;
                $claim->save();
            }
            $payment->save();

            CollectorTargetSideEffect::apply(
                $claim,
                (int) $payment_link->amount,
                'claim_service_manual_collection_installment'
            );

            if ($transaction) {
                //                    event(new PaymentSuccess($transaction));
                $generateLetters = new GenerateLetters($transaction);
                $generateLetters->execute();
            }



            return true;
        } catch (Exception $exception) {
            throw new NotAuthorizeException($exception->getMessage());
        }
        //        }

        //        return false;
    }

    public function generateVoucher($id)
    {
        try {
            DB::beginTransaction();
            $link = PaymentLink::findOrFail($id);

            if ($link->send_status != LinkSendStatus::PAID->value) {
                throw new NotAuthorizeException("this claim isn't paid fully yet");
            }
            $fileName = time() . '-' . 'voucher.pdf';
            $relative = 'voucher/' . $id . '/' . $fileName;
            $file_url = '/public/' . $relative;
            $voucher  = Voucher::create([
                'link_id' => $id,
                'url'     => $file_url,
            ]);
            $link->load('voucher');

            Voucher::where('link_id', $id)->where('id', '!=', $voucher->id)->update([
                'is_valid' => false,
            ]);

            $client_name = strtolower(config('app.client_name'));

            $clientPdfView = "pdf.{$client_name}.voucher";
            $defaultPdfView = "pdf.default.voucher";

            $pdfView = view()->exists($clientPdfView) ? $clientPdfView : $defaultPdfView;

            Pdf::view($pdfView, [
                'link' => $link,
                'voucher' => $voucher,
            ])
                ->disk('local')
                ->withBrowsershot(function (Browsershot $browsershot) {
                    // $browsershot->setNodeBinary('/usr/local/bin/node')
                    //   ->setChromePath('/var/www/html/.cache/puppeteer/chrome/linux-126.0.6478.182/chrome-linux64/chrome')
                    //  ->setNpmBinary('/usr/local/bin/npm');
                    if (! config('app.puppeteer.puppeteer_node_binary')) {
                        $browsershot->noSandbox();

                        return;
                    }
                    //                        $browsershot->timeout(60000);
                    $browsershot->setNodeBinary(config('app.puppeteer.puppeteer_node_binary'));
                    $browsershot->setNpmBinary(config('app.puppeteer.puppeteer_npm_binary'));
                    $browsershot->setChromePath(config('app.puppeteer.puppeteer_chrome_path'));
                    //                        $browsershot->setOption('newHeadless', true);
                    $browsershot->noSandbox();

                    // $browsershot->setChromePath("/home/albraa/Projects/ggi/.cache/puppeteer/chrome/linux-126.0.6478.126/chrome-linux64/chrome");
                })
                ->save($file_url);

            $file = (new FilesUploader)->registerLocalFile($relative, [
                'source'            => FileSource::VOUCHERS,
                'original_filename' => $fileName,
                'uploaded_by'       => auth()->id(),
            ]);
            $voucher->forceFill(['file_id' => $file->id])->save();

            DB::commit();

            return $link;
        } catch (Exception $exception) {
            DB::rollBack();

            throw new NotAuthorizeException($exception->getMessage());
        }
    }

    public function getVoucher($link_id)
    {
        $link = PaymentLink::with(['voucher.file', 'payment.claim'])->findOrFail($link_id);

        $voucher = $link->voucher;

        if (!$voucher) {
            throw new Exception('No voucher found for this payment link.');
        }

        $key = $voucher->file?->uuid ?? $voucher->url;

        if (!$key) {
            throw new Exception('Voucher has no associated file or URL.');
        }

        $voucher_link = route('download', ['key' => $key]);


        $messageService = new MessageService;
        $message = $messageService->convertTemplate(MessageTemplates::VOUCHER->value, $link->voucher()->first()->id);
        (new SmsService)->sendSms($link->payment->claim->debtor_phone, $message, $link->payment->claim->id, MessageTemplates::VOUCHER->value, template_model_id: $link->voucher()->first()->id);

        return $voucher_link;
    }

    /**
     * Get claim installments information.
     */
    public function getClaimInstallments($claimId)
    {
        $claim = Claim::findOrFail($claimId);
        $payment = $claim->payments()->first();

        if (!$payment) {
            return null;
        }

        // installment payment links
        $installmentLinks = PaymentLink::where('payment_id', $payment->id)
            ->where('type', PaymentType::INSTALLMENT_PAYMENT->value)
            ->where('send_status', '!=', LinkSendStatus::CANCELED->value)
            ->orderBy('order', 'asc')
            ->get();

        // get the note from status change request
        $statusChangeRequest = StatusChangeRequest::where('claim_id', $claimId)
            ->where('to_status', ClaimStatus::PARTIAL->value)
            ->latest()
            ->first();

        $note = $statusChangeRequest ? $statusChangeRequest->note : '';

        // calculate outstanding balances
        $totalAmount = $payment->amount;
        $outstandingBalance = $totalAmount;
        $installments = [];

        foreach ($installmentLinks as $link) {
            $outstandingBalance -= $link->amount;

            $installments[] = [
                'payment_link_id' => $link->id,
                'order' => $link->order,
                'amount' => $link->amount,
                'date' => $link->due_date,
                'outstanding_balance' => max(0, $outstandingBalance),
                'status' => $link->send_status
            ];
        }

        $firstInstallmentDate = $installmentLinks->first() ? $installmentLinks->first()->due_date : null;

        return [
            'claim_id' => (int) $claimId,
            'reference'=> $claim->reference,
            'external_reference'=> $claim->external_reference,
            'note' => $note,
            'first_installment_date' => $firstInstallmentDate,
            'installments' => $installments
        ];
    }


    public function makeInstallments(Claim $claim,$installments,int $paymentId,?string $installmentNote,int $actingUserId,?ClaimActionOptions $options = null): bool 
    {
       
        $options ??= ClaimActionOptions::systemApi();

        $user = User::findOrFail($actingUserId);

        $request = [
            'note' => $installmentNote,
            'installments' => $installments,
            'payment_id' => $paymentId,
        ];

        $previousStatus = $claim->status;
        if (!$this->createRequestStatus(
            claim_id: $claim->id,
            status: ClaimStatus::PARTIAL->value,
            request: $request,
            additional_file: null,
            permission_key: 'installment_payments',
            permission_path: 'payments',
            user: $user,
            options: $options,
        )) {

            // Cancel pending installment requests
            RequestsService::cancelPendingRequest($claim->id,ClaimStatus::PARTIAL->value,'Superseded by direct installment plan');

            (new PaymentService)->createNewInstallments($installments,$paymentId);

            $this->writeToClaimStatusHistory($claim->id,$previousStatus,ClaimStatus::PARTIAL->value,$actingUserId,$request);

            event(new UpdateClaimInstallmentEvent($claim->id));

            return true;
        }

        NotificationService::create([
            'title_ar' => __('notification.installment_payment_request', [], 'ar'),
            'title_en' => __('notification.installment_payment_request', [], 'en'),

            'description_ar' =>
                __('notification.you_have_installment_plan_request_for_claim', [], 'ar')
                . '( ' . $claim->claim_number . ' )',

            'description_en' =>
                __('notification.you_have_installment_plan_request_for_claim', [], 'en')
                . '( ' . $claim->claim_number . ' )',

            'regarding' => NotificationType::GENERAL->value,

            'user_id' => $user->id,

            'to' => json_encode([
                $user->supervisor_id,
            ]),

            'links' => json_encode([
                [
                    'title_ar' => 'الطلبات المفتوحة',
                    'title_en' => 'Open requests',
                    'url' => 'requests',
                ],
            ]),
        ]);

        return false;
    }
    public function validateClaimForInstallments(Claim $claim): void
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
    }

    public function makeDelay($claim, $validatedData, $payment_id, $installment_note)
    {
        $request = [
            'note' => $installment_note,
            'remaining_amount' => $validatedData['remaining_amount'],
            'date_to_delay' => $validatedData['date_to_delay'],
            'payment_id' => $payment_id,
        ];

        $previous_status = $claim->status;

        if (! $this->createRequestStatus($claim->id, ClaimStatus::DELAY->value, $request, null, 'delay_payments', 'payments')) {
            (new PaymentService)->addDelayPayment($request);
            $this->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::DELAY->value, auth()->user()->id, $request);

            return true;
        }
        NotificationService::create([
            'title_ar' => __('notification.delayed_payment_request', [], 'ar'),
            'title_en' => __('notification.delayed_payment_request', [], 'en'),
            'description_ar' => __('notification.you_have_delay_payment_request_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
            'description_en' => __('notification.you_have_delay_payment_request_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
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

        return false;
    }

    public function writeToClaimStatusHistory($claim_id, $previous_status, $current_status, $user_id, $request = [])
    {
        $claimStatusHistory = new ClaimStatusHistory;
        $claimStatusHistory->claim_id = $claim_id;
        $claimStatusHistory->previous_status = $previous_status;
        $claimStatusHistory->current_status = $current_status;
        $claimStatusHistory->user_id = $user_id;
        if ($request) {
            $url = isset($request['url']) ? $request['url'][0] : '';
            $claimStatusHistory->note = $request['note'] ?? '';
            $claimStatusHistory->reason = $request['other_reason'] ?? '';
            $claimStatusHistory->file = $url;

            if (! empty($request['file_id'])) {
                $claimStatusHistory->file_id = $request['file_id'];
            } elseif (filled($url)) {
                $fileId = \App\Models\File::where('local_path', $url)
                    ->orWhere('cloud_path', $url)
                    ->latest('id')
                    ->value('id');
                if ($fileId) {
                    $claimStatusHistory->file_id = $fileId;
                }
            }
        }

        $claimStatusHistory->save();

        return true;
    }

    public function changeAmount($claim, $validatedData, $user, $options)
    {

        $this->ensureClaimAmountIsChangeable($claim);

        $request = [
            'note' => $validatedData['note'],
            'new_amount' => $validatedData['new_amount'],
            'current_amount' => $validatedData['current_amount'],
        ];

        $previous_status = $claim->status;

        if (!$this->createRequestStatus($claim->id, ClaimStatus::CLAIM_AMOUNT_CHANGE->value, $request, null, 'update_claim_amount', 'claims', $user, $options)) {
            //            (new PaymentService())->addDelayPayment($request);

            ClaimAmountHistory::create([
                'claim_id' => $claim->id,
                'new_amount' => $validatedData['new_amount'],
                'current_amount' => $validatedData['current_amount'],
                'user_id' => $user->id,
            ]);

            $claim->amount = $validatedData['new_amount'];
            if ($claim->status !== ClaimStatus::REGISTERED->value && $claim->status !== ClaimStatus::APPROVED->value) {
                $claim->status = ClaimStatus::FOLLOW_UP->value;
            }
            $claim->is_draft = false;
            $claim->save();

            $payments = $claim->payments()->get();

            if ($payments->count() > 0) {
                foreach ($payments as $payment) {
                    $payment->amount = $validatedData['new_amount'];
                    $payment->save();

                    (new PaymentService)->cancelPayment($payment);
                }
            }
            //fire event for claim info

            $claimAmountHistory = [
                'note' => "Claim amount changed from {$validatedData['current_amount']} to {$validatedData['new_amount']}"
            ];

            $this->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::FOLLOW_UP->value, $user->id, $claimAmountHistory);

            return true;
        }
        NotificationService::create([
            'title_ar' => __('notification.change_amount_request', [], 'ar'),
            'title_en' => __('notification.change_amount_request', [], 'en'),
            'description_ar' => __('notification.you_have_change_amount_request_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ' .
                __('system.from', [], 'ar') . ' ( ' . $validatedData['current_amount'] . ' ) ' .
                __('system.to', [], 'ar') . ' ( ' . $validatedData['new_amount'] . ' ) ',
            'description_en' => __('notification.you_have_change_amount_request_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ' .
                __('system.from', [], 'en') . ' ( ' . $validatedData['current_amount'] . ' ) ' .
                __('system.to', [], 'en') . ' ( ' . $validatedData['new_amount'] . ' ) ',
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
    }

    private function ensureClaimAmountIsChangeable($claim): void
    {
        if ($claim->has_one_payment_at_least) {
            throw new Exception('Claim Amount can not be changed as it has one payment at least');
        }

        if ($this->hasPendingPaymentLink($claim)) {
            throw new Exception('Claim Amount can not be changed as it has a pending payment link');
        }
    }

    private function hasPendingPaymentLink($claim): bool
    {
        return $claim->payments()
            ->whereHas('links', fn($query) => $query->where('send_status', LinkSendStatus::PENDING->value))
            ->exists();
    }

    /**
     * Check if a claim already exists based on specific criteria.
     */
    public static function isDuplicateClaim(string $claimNumber, string $debtorNid, float $amount, ?string $subClaimNumber = null)
    {
        $query = DB::table('claims')
            ->where('claim_number', $claimNumber)
            ->where('debtor_nid', $debtorNid)
            ->where('amount', $amount);

        if ($subClaimNumber === null) {
            $query->whereNull('sub_claim_number');
        } else {
            $query->where('sub_claim_number', $subClaimNumber);
        }

        return $query;
    }

    public static function recalculateAfterDiscount(Payment $payment, Discount $discount)
    {
        $remaining_amount = $discount->amount_after_discount;

        $links = $payment->links->whereIn('send_status', [
            LinkSendStatus::NOT_SENT->value,
            LinkSendStatus::SENT->value,
            LinkSendStatus::DISPUTE_PAYMENT->value,
            LinkSendStatus::SKIPPED->value,
            LinkSendStatus::FAILED->value,
        ])->values();

        $remaining_percent = 100;
        $baseAmount = $payment->amount - $payment->paid_amount;
        $totalAssigned = 0;
        $lastIndex = count($links) - 1;

        foreach ($links as $index => $link) {
            if ($index === $lastIndex) {
                $link->amount = round($remaining_amount - $totalAssigned, 2);
            } else {
                $percentage = ($link->amount / $baseAmount) * $remaining_percent;
                $link->amount = round($remaining_amount * ($percentage / 100), 2);
                $totalAssigned += $link->amount;
            }
            $link->save();
        }

        $payment->amount = $payment->amount - $discount->deducted_amount;
        $payment->save();
    }

    public static function reverseDiscount(Payment $payment, Discount $discount)
    {
        $remaining_amount = $discount->total_amount - $payment->paid_amount;
        $remaining_percent = 100;

        $links = $payment->links->whereIn('send_status', [
            LinkSendStatus::NOT_SENT->value,
            LinkSendStatus::SENT->value,
            LinkSendStatus::DISPUTE_PAYMENT->value,
            LinkSendStatus::SKIPPED->value,
            LinkSendStatus::FAILED->value,
        ])->values();

        $remaining_percent = 100;
        $baseAmount = $payment->amount - $payment->paid_amount;
        $totalAssigned = 0;
        $lastIndex = count($links) - 1;

        foreach ($links as $index => $link) {
            if ($index === $lastIndex) {
                $link->amount = round($remaining_amount - $totalAssigned, 2);
            } else {
                $percentage = ($link->amount / $baseAmount) * $remaining_percent;
                $link->amount = round($remaining_amount * ($percentage / 100), 2);
                $totalAssigned += $link->amount;
            }
            $link->save();
        }

        $payment->amount = $discount->total_amount;
        $payment->save();
    }

    public function updateInstallments($claim, array $installments_add, int $payment_id, ?string $installment_note, int $actingUserId,?ClaimActionOptions $options = null)
    {
        $options ??= \App\Services\Claim\ClaimActionOptions::systemApi();
        $user = \App\Models\User::findOrFail($actingUserId);
        $request = [
            'note' => $installment_note,
            'installments' => $installments_add,
            'payment_id' => $payment_id,
        ];

        $previous_status = $claim->status;

        if (! $this->createRequestStatus($claim->id, ClaimStatus::PARTIAL->value, $request, null, 'installment_payments', 'payments', $user, $options)) {
            
            // cancel pending installment requests 
            RequestsService::cancelPendingRequest($claim->id, ClaimStatus::PARTIAL->value, 'Superseded by direct installment plan' );

            (new PaymentService)->updateInstallmentsPlan($installments_add, $payment_id);

            $this->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::PARTIAL->value, $actingUserId, $request);

            //fire event for claim info

            event(new UpdateClaimInstallmentEvent($claim->id));
            return true;
        }
        NotificationService::create([
            'title_ar' => __('notification.installment_payment_request', [], 'ar'),
            'title_en' => __('notification.installment_payment_request', [], 'en'),
            'description_ar' => __('notification.you_have_installment_plan_request_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
            'description_en' => __('notification.you_have_installment_plan_request_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
            'regarding' => NotificationType::GENERAL->value,
            'user_id' => $user?->id,
            'to' => json_encode([$user->supervisor_id]),
            'links' => json_encode([
                [
                    'title_ar' => 'الطلبات المفتوحة',
                    'title_en' => 'Open requests',
                    'url' => 'requests',
                ],
            ]),
        ]);

        return false;
    }
}
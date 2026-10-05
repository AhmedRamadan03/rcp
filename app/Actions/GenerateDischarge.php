<?php

namespace App\Actions;

use App\Enums\FileSource;
use App\Enums\LetterNotification;
use App\Enums\MessageTemplates;
use App\Enums\NotificationType;
use App\Exceptions\NotAuthorizeException;
use App\Models\Claim;
use App\Models\Discharge;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Services\FilesUploader;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\SmsService;
use Exception;
use Illuminate\Support\Facades\DB;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class GenerateDischarge
{
    public function __construct(
        public PaymentTransaction $paymentTransaction
    ) {}

    public function execute(): void
    {
        try {
            $claim = Claim::findOrFail($this->paymentTransaction->link->payment->claim_id);

            if (! $claim->isCollected) {
                return;
            }

            DB::beginTransaction();

            $fileName    = time().'-'.'discharge.pdf';
            $relative    = 'discharge/'.$claim->id.'/'.$fileName;
            $file_url    = '/public/'.$relative;
            $discharge   = Discharge::create([
                'claim_id' => $claim->id,
                'url'      => $file_url,
            ]);
            $claim->load('discharge');

            Discharge::where('claim_id', $claim->id)->where('id', '!=', $discharge->id)->update([
                'is_valid' => false,
            ]);

            $client_name = strtolower(config('app.client_name'));

            $clientPdfView = "pdf.{$client_name}.discharge-letter";
            $defaultPdfView = "pdf.default.discharge-letter";

            $pdfView = view()->exists($clientPdfView) ? $clientPdfView : $defaultPdfView;

            Pdf::view($pdfView, [
                'claim'     => $claim,
                'discharge' => $discharge,
            ])
                ->disk('local')
                ->withBrowsershot(function (Browsershot $browsershot) {
                    if (! config('app.puppeteer.puppeteer_node_binary')) {
                        $browsershot->noSandbox();

                        return;
                    }
                    $browsershot->setIncludePath(false);
                    $browsershot->setNodeBinary(config('app.puppeteer.puppeteer_node_binary'));
                    $browsershot->setNpmBinary(config('app.puppeteer.puppeteer_npm_binary'));
                    $browsershot->setChromePath(config('app.puppeteer.puppeteer_chrome_path'));
                    $browsershot->noSandbox();
                })
                ->save($file_url);

            $file = (new FilesUploader)->registerLocalFile($relative, [
                'source'            => FileSource::DISCHARGE_FILES,
                'original_filename' => $fileName,
                'uploaded_by'       => auth()->id(),
            ]);
            $discharge->forceFill(['file_id' => $file->id])->save();


            DB::commit();

            $service = new SmsService;

            $messageService = new MessageService;
            $message = $messageService->convertTemplate(MessageTemplates::DISCHARGE->value, $claim->discharge()->first()->id);

            $service->sendSms($claim->debtor_phone, $message, $claim->id, MessageTemplates::DISCHARGE->value, template_model_id: $claim->discharge()->first()->id);

            $letters_notification = Setting::select('value')->where('key', 'claims.letters_generation_notification')->first();
            if ($letters_notification->value == LetterNotification::NOTIFY->value) {
                $notification = NotificationService::create([
                    'title_ar' => __('notification.discharge_generated', [], 'ar'),
                    'title_en' => __('notification.discharge_generated', [], 'en'),
                    'description_ar' => __('notification.notice_for_discharge_generated', [], 'ar').'( '.$claim?->claim_number.' )  ',
                    'description_en' => __('notification.notice_for_discharge_generated', [], 'en').'( '.$claim?->claim_number.' )  ',
                    'regarding' => NotificationType::DISCHARGE_LETTER->value,
                    'user_id' => $claim->assignee->to,
                    'to' => json_encode([$claim->assignee->to]),
                    'links' => json_encode([
                        [
                            'title_ar' => 'الطلبات المفتوحة',
                            'title_en' => 'Open requests',
                            'url' => 'claims/'. $claim?->id.'/info'
                        ],
                    ]),
                ]);
            }
        } catch (Exception $exception) {
            DB::rollBack();

            throw new NotAuthorizeException($exception->getMessage());
        }
    }
}

<?php

namespace App\Actions;

use App\Enums\FileSource;
use App\Enums\LetterNotification;
use App\Enums\MessageTemplates;
use App\Enums\NotificationType;
use App\Enums\PaymentTransactionStatus;
use App\Exceptions\NotAuthorizeException;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Models\Voucher;
use App\Services\FilesUploader;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\SmsService;
use Exception;
use Illuminate\Support\Facades\DB;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;

class GenerateVoucher
{
    public function __construct(
        public PaymentTransaction $paymentTransaction
    ) {}

    public function execute(): void
    {
        try {
            DB::beginTransaction();

            $link = PaymentLink::findOrFail($this->paymentTransaction->link_id);

            if ($this->paymentTransaction->send_status !== PaymentTransactionStatus::PAID->value) {
                throw new NotAuthorizeException("this transaction isn't paid yet");
            }

            $fileName    = time().'-'.'voucher.pdf';
            $relative    = 'voucher/'.$link->id.'/'.$fileName;
            $file_url    = '/public/'.$relative;
            $voucher     = Voucher::create([
                'link_id' => $link->id,
                'url'     => $file_url,
            ]);
            $link->load('voucher');

            Voucher::where('link_id', $link->id)->where('id', '!=', $voucher->id)->update([
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
                    if (! config('app.puppeteer.puppeteer_node_binary')) {
                        $browsershot->noSandbox();

                        return;
                    }
                    $browsershot->setIncludePath(false);
                    $browsershot->timeout(60000);
                    $browsershot->setNodeBinary(config('app.puppeteer.puppeteer_node_binary'));
                    $browsershot->setNpmBinary(config('app.puppeteer.puppeteer_npm_binary'));
                    $browsershot->setChromePath(config('app.puppeteer.puppeteer_chrome_path'));
                    $browsershot->noSandbox();
                    $browsershot->setOption('newHeadless', true);
                })
                ->save($file_url);

            $file = (new FilesUploader)->registerLocalFile($relative, [
                'source'            => FileSource::VOUCHERS,
                'original_filename' => $fileName,
                'uploaded_by'       => auth()->id(),
            ]);
            $voucher->forceFill(['file_id' => $file->id])->save();

            DB::commit();

            $service = new SmsService;

            $messageService = new MessageService;
            $message = $messageService->convertTemplate(MessageTemplates::VOUCHER->value, $voucher->id);

            $service->sendSms($link->payment->claim->debtor_phone, $message, $link->payment->claim->id, MessageTemplates::VOUCHER->value, template_model_id: $voucher->id);

            $letters_notification = Setting::select('value')->where('key', 'claims.letters_generation_notification')->first();
            if ($letters_notification->value == LetterNotification::NOTIFY->value) {
                $notification = NotificationService::create([
                    'title_ar' => __('notification.voucher_generated', [], 'ar'),
                    'title_en' => __('notification.voucher_generated', [], 'en'),
                    'description_ar' => __('notification.notice_for_voucher_generated', [], 'ar').'( '.$link->payment->claim?->claim_number.' )  ',
                    'description_en' => __('notification.notice_for_voucher_generated', [], 'en').'( '.$link->payment->claim?->claim_number.' )  ',
                    'regarding' => NotificationType::VOUCHER_LETTER->value,
                    'user_id' => $link->payment->claim->assignee?->toUser?->supervisor_id,
                    'to' => json_encode([$link->payment->claim->assignee?->toUser?->supervisor_id]),
                    'links' => json_encode([
                        [
                            'title_ar' => 'الطلبات المفتوحة',
                            'title_en' => 'Open requests',
                            'url' => 'claims/'.$link->payment->claim_id.'/info'
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

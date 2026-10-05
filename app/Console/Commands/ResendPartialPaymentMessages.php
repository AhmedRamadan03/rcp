<?php

namespace App\Console\Commands;

use App\Models\Claim;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\PaymentLink;
use App\Services\MessageService;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Throwable;

class ResendPartialPaymentMessages extends Command
{
    protected $signature = 'app:resend-partial-payment-messages {--send : Actually send the messages. Without this flag the command runs in dry-run mode and only prints what would be sent.}';

    protected $description = 'Resend SMS messages for specific message records (dry-run by default; pass --send to actually send)';

    private array $messageIds = [
        150119,
        150118,
        150126,
        150127,
        150117,
        150097,
        150098,
        150099,
        150100,
        150120,
        150121,
        150122,
        150123,
        150125,
    ];

    public function handle(): int
    {
        $messageService = new MessageService;
        $smsService = new SmsService;

        $send = (bool) $this->option('send');

        if ($send) {
            $this->warn('Running in SEND mode. Messages will be sent to real customers.');
        } else {
            $this->info('Running in DRY-RUN mode. No messages will be sent. Pass --send to actually send.');
        }

        $messages = Message::with('paymentLink')
            ->whereIn('id', $this->messageIds)
            ->get()
            ->keyBy('id');

        foreach ($this->messageIds as $messageId) {
            $messageRecord = $messages->get($messageId);

            if (! $messageRecord) {
                $this->error("Message {$messageId}: not found.");

                continue;
            }

            if (! $messageRecord->template_id) {
                $this->error("Message {$messageId}: has no template.");

                continue;
            }

            if (! $messageRecord->claim_id) {
                $this->error("Message {$messageId}: has no claim_id.");

                continue;
            }

            if (! $messageRecord->phone_number) {
                $this->error("Message {$messageId}: has no phone number.");

                continue;
            }

            $templateModelId = $this->resolveTemplateModelId($messageRecord);

            if (! $templateModelId) {
                $this->error("Message {$messageId}: could not resolve template model id.");

                continue;
            }

            try {
                $body = $messageService->convertTemplate(
                    $messageRecord->template_id,
                    $templateModelId
                );
            } catch (Throwable $e) {
                $this->error("Message {$messageId}: failed to build template body: {$e->getMessage()}");

                continue;
            }

            $this->line(str_repeat('-', 60));
            $this->line("message_id:       {$messageId}");
            $this->line("phone:            {$messageRecord->phone_number}");
            $this->line("claim_id:         {$messageRecord->claim_id}");
            $this->line("template_id:      {$messageRecord->template_id}");
            $this->line("model_id:         {$templateModelId}");
            $this->line('is_elm_message:   ' . ($messageRecord->is_elm_message ? 'true' : 'false'));
            $this->line("body:\n{$body}");

            if (! $send) {
                continue;
            }

            try {
                $sent = $smsService->sendSms(
                    $messageRecord->phone_number,
                    $body,
                    $messageRecord->claim_id,
                    $messageRecord->template_id,
                    $messageRecord->payment_link_id,
                    true,
                    $templateModelId
                );

                $this->info("Message {$messageId}: sent as message #{$sent->id} (status: {$sent->status}, message_status: {$sent->message_status}).");
            } catch (Throwable $e) {
                $this->error("Message {$messageId}: failed to send: {$e->getMessage()}");
            }
        }

        $this->line(str_repeat('-', 60));

        return self::SUCCESS;
    }

    private function resolveTemplateModelId(Message $messageRecord): ?int
    {
        $messageTemplate = MessageTemplate::find($messageRecord->template_id);

        if (! $messageTemplate) {
            return null;
        }

        return match ($messageTemplate->model) {
            PaymentLink::class => $messageRecord->payment_link_id,
            Claim::class => $messageRecord->claim_id,
            default => $messageRecord->payment_link_id ?? $messageRecord->claim_id,
        };
    }
}

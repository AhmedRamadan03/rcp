<?php

namespace Database\Seeders;

use App\Enums\MessageTemplates;
use App\Models\MessageTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClaimNumberToVoucherTemplateMessage extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $messageTemplate = MessageTemplate::find(MessageTemplates::VOUCHER->value);

        $messageTemplate->variables()->createMany([
            [
                'key' => '$claim_number',
                'value' => 'link.payment.claim.claim_number',
                'label' => __('claim.claim_number'),
            ]
        ]);
    }
}

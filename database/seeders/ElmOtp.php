<?php

namespace Database\Seeders;

use App\Models\CustomTemplate;
use App\Models\ElmRequest;
use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class ElmOtp extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $message = MessageTemplate::create([
            'title' => 'تسجيل دخول علم',
            'model' => ElmRequest::class,
        ]);

        $message->variables()->createMany([[
            'key' => '$code',
            'value' => 'otp',
            'label' => __('payment_link.url'),
        ]]);

        $custom_message = 'OTP: $code
                        لتسجيل الدخول
        ';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'تسجيل دخول علم',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);
    }
}

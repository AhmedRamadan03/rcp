<?php

namespace Database\Seeders;

use App\Models\CustomTemplate;
use App\Models\MessageTemplate;
use App\Models\RejectedClaim;
use Illuminate\Database\Seeder;

class ClientRequestMessageTemplate extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // موافقة طلب عميل
        $message = MessageTemplate::create([
            'title' => 'موافقة طلب عميل',
            'model' => RejectedClaim::class,
        ]);

        $custom_message = 'عزيزي العميل تم قبول طلبك بنجاح';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'موافقة طلب عميل',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);

        // رفض طلب عميل
        $message = MessageTemplate::create([
            'title' => 'رفض طلب عميل',
            'model' => RejectedClaim::class,
        ]);

        $custom_message = 'عزيزي العميل تم رفض طلبك بنجاح';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'رفض طلب عميل',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);
    }
}

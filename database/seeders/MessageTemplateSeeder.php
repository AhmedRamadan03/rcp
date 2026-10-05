<?php

namespace Database\Seeders;

use App\Models\Claim;
use App\Models\CustomTemplate;
use App\Models\Discharge;
use App\Models\MessageTemplate;
use App\Models\PaymentLink;
use App\Models\VerificationCode;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** ------------------------------
         * 1. الموافقة
         * ------------------------------*/
        $message = MessageTemplate::create([
            'title' => 'الموافقة',
            'model' => PaymentLink::class,
        ]);

        $custom_message = 'عزيزي العميل، نفيدكم بوجود مطالبة مالية لدى (تهيئة) رقم المرجع ($claim_number) بمبلغ وقدره ($amount) ريال سعودي. لمزيد من المعلومات يرجى الاطلاع على المستندات من خلال الرابط: $link. تذكّر دائماً أن الخليجية العامة لن تطلب أي أرقام سرية خاصة بكم بأي حال من الأحوال. رمز التحقق الخاص بكم هو: ($verification_code).';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'موافقة على مطالبة مالية',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);

        $message->variables()->createMany([
            ['key' => '$claim_number', 'value' => 'payment.claim.claim_number', 'label' => __('claim.claim_number')],
            ['key' => '$policy_number', 'value' => 'payment.claim.policy_number', 'label' => __('claim.policy_number')],
            ['key' => '$debtor_national_id', 'value' => 'payment.claim.debtor_nid', 'label' => __('claim.debtor_id')],
            ['key' => '$debtor_name', 'value' => 'payment.claim.debtor_name', 'label' => __('claim.debtor_name')],
            ['key' => '$debtor_phone', 'value' => 'payment.claim.debtor_phone', 'label' => __('claim.debtor_phone_number')],
            ['key' => '$accident_date', 'value' => 'payment.claim.accident_date', 'label' => __('claim.accident_date')],
            ['key' => '$city_name', 'value' => 'payment.claim.city.name', 'label' => __('claim.accident_city')],
            ['key' => '$amount', 'value' => 'payment.claim.amount', 'label' => __('claim.amount')],
            ['key' => '$link', 'value' => 'link', 'label' => __('Link')],
            ['key' => '$verification_code', 'value' => 'verification_code', 'label' => __('verification_code')],
        ]);

        /** ------------------------------
         * 2. تأجيل السداد
         * ------------------------------*/
        $message = MessageTemplate::create([
            'title' => 'تأجيل السداد',
            'model' => PaymentLink::class,
        ]);

        $custom_message = 'عزيزي العميل، بناء على طلبكم نفيدكم أنه تم الموافقة على تأجيل السداد، علماً أن موعد الدفعة المستحقة سيكون بتاريخ $due_date بمبلغ ($amount)، وسيصلكم رابط الدفع بالتاريخ المحدد.';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'موافقة على تأجيل السداد',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);

        $message->variables()->createMany([
            ['key' => '$due_date', 'value' => 'due_date', 'label' => __('Due Date')],
            ['key' => '$amount', 'value' => 'amount', 'label' => __('Amount')],
        ]);

        /** ------------------------------
         * 3. موعد السداد الجزئي
         * ------------------------------*/
        $message = MessageTemplate::create([
            'title' => 'موعد السداد الجزئي',
            'model' => PaymentLink::class,
        ]);

        $custom_message = 'عزيزي العميل، نود إشعاركم باستحقاق القسط رقم ($count). نرجو التكرم بسداد المبلغ ($amount). الرجاء الضغط على الرابط التالي للسداد من خلال قنوات الدفع الرسمية المعتمدة: $link. رمز التحقق الخاص بكم هو: ($verification_code). تذكر دائماً أن الخليجية العامة لن تطلب أي أرقام سرية خاصة بكم لأي سبب من الأسباب.';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'إشعار بموعد السداد الجزئي',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);

        $message->variables()->createMany([
            ['key' => '$count', 'value' => 'order', 'label' => __('installment link')],
            ['key' => '$amount', 'value' => 'amount', 'label' => __('amount')],
            ['key' => '$link', 'value' => 'link', 'label' => __('link')],
            ['key' => '$verification_code', 'value' => 'verification_code', 'label' => __('verification_code')],
        ]);

        /** ------------------------------
         * 4. التحويل للمحامي
         * ------------------------------*/
        $message = MessageTemplate::create([
            'title' => 'التحويل للمحامي',
            'model' => Claim::class,
        ]);

        $custom_message = 'تم تحويل المطالبة إلى المحامي للمتابعة القانونية. سيتم التواصل معكم حال تحديث الحالة.';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'إشعار بالتحويل للمحامي',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);

        /** ------------------------------
         * 5. المخالصة المالية
         * ------------------------------*/
        $message = MessageTemplate::create([
            'title' => 'المخالصة المالية',
            'model' => Discharge::class,
        ]);

        $custom_message = 'عزيزي العميل، تم إصدار المخالصة المالية الخاصة بمطالبتكم. نشكركم على تعاونكم.';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'إشعار بالمخالصة المالية',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);

        /** ------------------------------
         * 6. سند القبض
         * ------------------------------*/
        $message = MessageTemplate::create([
            'title' => 'سند القبض',
            'model' => Voucher::class,
        ]);

        $custom_message = 'تم إصدار سند قبض خاص بكم. يمكنكم الاطلاع على التفاصيل عبر الرابط المرسل.';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'إشعار بسند القبض',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);

        /** ------------------------------
         * 7. السداد البنكي
         * ------------------------------*/
        $message = MessageTemplate::create([
            'title' => 'السداد البنكي',
            'model' => PaymentLink::class,
        ]);

        $custom_message = 'عزيزي العميل، نرجو منكم إتمام عملية السداد البنكي عبر الرابط المرفق $url. شاكرين تعاونكم.';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'إشعار بالسداد البنكي',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);

        /** ------------------------------
         * 8. رمز التحقق (OTP)
         * ------------------------------*/
        $message = MessageTemplate::create([
            'title' => 'رمز التحقق',
            'model' => VerificationCode::class,
        ]);

        $custom_message = 'شكرًا لاختياركم شركة تهيئة. استخدم رمز التحقق التالي لإتمام العملية: $code. الرمز صالح لمدة 5 دقائق. لا تشاركه مع أي شخص.';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'رمز التحقق لحساب العميل',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);

        /** ------------------------------
         * 9. وقت السداد
         * ------------------------------*/
        $message = MessageTemplate::create([
            'title' => 'وقت السداد',
            'model' => PaymentLink::class,
        ]);

        $custom_message = 'عزيزي العميل، نود إشعاركم بموعد سداد المبلغ المستحق وقدره ($amount). يرجى السداد عبر الرابط التالي: $link. رمز التحقق الخاص بكم هو: ($verification_code). تذكر دائماً أن الخليجية العامة لن تطلب أي أرقام سرية خاصة بكم لأي سبب من الأسباب.';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'تذكير بموعد السداد',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);

        $message->variables()->createMany([
            ['key' => '$amount', 'value' => 'amount', 'label' => __('Amount')],
            ['key' => '$link', 'value' => 'link', 'label' => __('Link')],
            ['key' => '$verification_code', 'value' => 'verification_code', 'label' => __('verification_code')],
        ]);


        /** ------------------------------
         * 10. موافقة على السداد الجزئي
         * ------------------------------*/
        $message = MessageTemplate::create([
            'title' => 'موافقة على السداد الجزئي',
            'model' => Claim::class,
        ]);

        $custom_message = 'عزيزي العميل، نفيدكم أنه تمت الموافقة على التسوية بواقع $count، وسيتم إرسال رابط الدفع الأول بتاريخ $date.';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'إشعار بالموافقة على السداد الجزئي',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);

        $message->variables()->createMany([
            ['key' => '$count', 'value' => 'installment_counts', 'label' => __('number of installments')],
            ['key' => '$date', 'value' => 'installment_first_date', 'label' => __('first installment due date')],
        ]);


        /** ------------------------------
         * 11. موافقة على الخصم
         * ------------------------------*/
        $message = MessageTemplate::create([
            'title' => 'موافقة على الخصم',
            'model' => PaymentLink::class,
        ]);

        $custom_message = 'عزيزي العميل، تمت الموافقة على طلب الخصم. يرجى سداد المبلغ عبر الرابط أدناه $url. شكراً لتعاونكم.';

        CustomTemplate::create([
            'template_id' => $message->id,
            'name' => 'إشعار بالموافقة على الخصم',
            'body' => $custom_message,
            'is_active' => 1,
            'client_id' => 1,
        ]);
    }
}

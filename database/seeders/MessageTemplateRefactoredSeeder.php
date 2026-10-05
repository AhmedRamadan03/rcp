<?php

namespace Database\Seeders;

use App\Models\Claim;
use App\Models\CustomTemplate;
use App\Models\Discharge;
use App\Models\Discount;
use App\Models\ElmRequest;
use App\Models\MessageTemplate;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Models\RejectedClaim;
use App\Models\VerificationCode;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

class MessageTemplateRefactoredSeeder extends Seeder
{
    /**
     * Template definitions — add new templates here.
     * Each entry:
     *   title      => MessageTemplate title (used as unique key for idempotency)
     *   model      => Eloquent model class the template belongs to
     *   templates  => array of CustomTemplate definitions for this group
     *   variables  => array of variable definitions (optional)
     */
    private function definitions(): array
    {
        return [

            /* 1 ─ الموافقة */
            [
                'title' => 'الموافقة',
                'model' => PaymentLink::class,
                'templates' => [
                    [
                        'name'      => 'موافقة على مطالبة مالية',
                        'body'      => 'عزيزي العميل، نفيدكم بوجود مطالبة مالية لدى (تهيئة) رقم المرجع ($claim_number) بمبلغ وقدره ($amount) ريال سعودي. لمزيد من المعلومات يرجى الاطلاع على المستندات من خلال الرابط: $link. تذكّر دائماً أن الخليجية العامة لن تطلب أي أرقام سرية خاصة بكم بأي حال من الأحوال. رمز التحقق الخاص بكم هو: ($verification_code).',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$claim_number',     'value' => 'payment.claim.claim_number', 'label' => __('claim.claim_number')],
                    ['key' => '$policy_number',     'value' => 'payment.claim.policy_number','label' => __('claim.policy_number')],
                    ['key' => '$debtor_national_id','value' => 'payment.claim.debtor_nid',   'label' => __('claim.debtor_id')],
                    ['key' => '$debtor_name',       'value' => 'payment.claim.debtor_name',  'label' => __('claim.debtor_name')],
                    ['key' => '$debtor_phone',      'value' => 'payment.claim.debtor_phone', 'label' => __('claim.debtor_phone_number')],
                    ['key' => '$accident_date',     'value' => 'payment.claim.accident_date','label' => __('claim.accident_date')],
                    ['key' => '$city_name',         'value' => 'payment.claim.city.name',    'label' => __('claim.accident_city')],
                    ['key' => '$amount',            'value' => 'payment.claim.amount',       'label' => __('claim.amount')],
                    ['key' => '$link',              'value' => 'payment.link',                       'label' => __('Link')],
                    ['key' => '$verification_code', 'value' => 'payment.verification_code',          'label' => __('verification_code')],
                ],
            ],

            /* 2 ─ تأجيل السداد */
            [
                'title' => 'تأجيل السداد',
                'model' => PaymentLink::class,
                'templates' => [
                    [
                        'name'      => 'موافقة على تأجيل السداد',
                        'body'      => 'عزيزي العميل، بناء على طلبكم نفيدكم أنه تم الموافقة على تأجيل السداد، علماً أن موعد الدفعة المستحقة سيكون بتاريخ $due_date بمبلغ ($amount)، وسيصلكم رابط الدفع بالتاريخ المحدد.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$due_date', 'value' => 'due_date', 'label' => __('Due Date')],
                    ['key' => '$amount',   'value' => 'amount',   'label' => __('Amount')],
                    ['key' => '$claim_number',     'value' => 'payment.claim.claim_number', 'label' => __('claim.claim_number')],
                     ['key' => '$link',              'value' => 'payment.link',                       'label' => __('Link')],
                    ['key' => '$verification_code', 'value' => 'payment.verification_code',          'label' => __('verification_code')],
                    
                ],
            ],

            /* 3 ─ موعد السداد الجزئي */
            [
                'title' => 'موعد السداد الجزئي',
                'model' => PaymentLink::class,
                'templates' => [
                    [
                        'name'      => 'إشعار بموعد السداد الجزئي',
                        'body'      => 'عزيزي العميل، نود إشعاركم باستحقاق القسط رقم ($count). نرجو التكرم بسداد المبلغ ($amount). الرجاء الضغط على الرابط التالي للسداد من خلال قنوات الدفع الرسمية المعتمدة: $link. رمز التحقق الخاص بكم هو: ($verification_code). تذكر دائماً أن الخليجية العامة لن تطلب أي أرقام سرية خاصة بكم لأي سبب من الأسباب.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$count',             'value' => 'order',             'label' => __('installment link')],
                    ['key' => '$amount',            'value' => 'amount',            'label' => __('amount')],
                    ['key' => '$link',              'value' => 'payment.link',              'label' => __('link')],
                    ['key' => '$verification_code', 'value' => 'payment.verification_code', 'label' => __('verification_code')],
                    ['key' => '$claim_number',     'value' => 'payment.claim.claim_number', 'label' => __('claim.claim_number')],
                ],
            ],

            /* 4 ─ التحويل للمحامي */
            [
                'title' => 'التحويل للمحامي',
                'model' => Claim::class,
                'templates' => [
                    [
                        'name'      => 'إشعار بالتحويل للمحامي',
                        'body'      => 'تم تحويل المطالبة إلى المحامي للمتابعة القانونية. سيتم التواصل معكم حال تحديث الحالة.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$claim_number',     'value' => 'claim_number', 'label' => __('claim.claim_number')],
                    ['key' => '$link',              'value' => 'payment.link',              'label' => __('link')],
                    ['key' => '$verification_code', 'value' => 'payment.verification_code', 'label' => __('verification_code')],
                ],
            ],

            /* 5 ─ المخالصة المالية */
            [
                'title' => 'المخالصة المالية',
                'model' => Discharge::class,
                'templates' => [
                    [
                        'name'      => 'إشعار بالمخالصة المالية',
                        'body'      => 'عزيزي العميل، تم إصدار المخالصة المالية الخاصة بمطالبتكم. نشكركم على تعاونكم.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                    ],
                    'variables' => [
                        ['key' => '$claim_number',     'value' => 'claim.claim_number', 'label' => __('claim.claim_number')],
                        ['key' => '$link',              'value' => 'claim.payment.link',              'label' => __('link')],
                        ['key' => '$verification_code', 'value' => 'claim.payment.verification_code', 'label' => __('verification_code')],
                    ],
            ],

            /* 6 ─ سند القبض */
            [
                'title' => 'سند القبض',
                'model' => Voucher::class,
                'templates' => [
                    [
                        'name' => 'إشعار بسند القبض',
                        'body' => 'تم إصدار سند قبض خاص بكم. يمكنكم الاطلاع على التفاصيل عبر الرابط المرسل.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    [
                        'key' => '$claim_number',
                        'value' => 'link.payment.claim.claim_number',
                        'label' => __('claim.claim_number')
                    ],
                    [
                        'key' => '$url',
                        'value' => 'url',
                        'label' => __('URL')
                    ],
                    [
                        'key' => '$link',
                        'value' => 'link.payment.link',
                        'label' => __('link')
                    ],
                     [
                        'key' => '$verification_code',
                        'value' => 'link.payment.verification_code',
                        'label' => __('verification_code')
                    ],
                ],
            ],

            /* 7 ─ السداد البنكي */
            [
                'title' => 'السداد البنكي',
                'model' => PaymentLink::class,
                'templates' => [
                    [
                        'name'      => 'إشعار بالسداد البنكي',
                        'body'      => 'عزيزي العميل، نرجو منكم إتمام عملية السداد البنكي عبر الرابط المرفق $url. شاكرين تعاونكم.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$claim_number',     'value' => 'payment.claim.claim_number', 'label' => __('claim.claim_number')],
                    ['key' => '$link',              'value' => 'payment.link',              'label' => __('link')],
                    ['key' => '$verification_code', 'value' => 'payment.verification_code', 'label' => __('verification_code')],
                ],
            ],

            /* 8 ─ رمز التحقق (OTP) */
            [
                'title' => 'رمز التحقق',
                'model' => VerificationCode::class,
                'templates' => [
                    [
                        'name'      => 'رمز التحقق لحساب العميل',
                        'body'      => 'شكرًا لاختياركم شركة تهيئة. استخدم رمز التحقق التالي لإتمام العملية: $otp. الرمز صالح لمدة 5 دقائق. لا تشاركه مع أي شخص.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$otp',            'value' => 'otp',            'label' => __('verification_code')],
                ],
            ],

            /* 9 ─ وقت السداد */
            [
                'title' => 'وقت السداد',
                'model' => PaymentLink::class,
                'templates' => [
                    [
                        'name'      => 'تذكير بموعد السداد',
                        'body'      => 'عزيزي العميل، نود إشعاركم بموعد سداد المبلغ المستحق وقدره ($amount). يرجى السداد عبر الرابط التالي: $link. رمز التحقق الخاص بكم هو: ($verification_code). تذكر دائماً أن الخليجية العامة لن تطلب أي أرقام سرية خاصة بكم لأي سبب من الأسباب.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$amount',            'value' => 'amount',            'label' => __('Amount')],
                    ['key' => '$link',              'value' => 'payment.link',              'label' => __('Link')],
                    ['key' => '$verification_code', 'value' => 'payment.verification_code', 'label' => __('verification_code')],
                    ['key' => '$claim_number',     'value' => 'payment.claim.claim_number', 'label' => __('claim.claim_number')],
                ],
            ],

            /* 10 ─ موافقة على السداد الجزئي */
            [
                'title' => 'موافقة على السداد الجزئي',
                'model' => Claim::class,
                'templates' => [
                    [
                        'name'      => 'إشعار بالموافقة على السداد الجزئي',
                        'body'      => 'عزيزي العميل، نفيدكم أنه تمت الموافقة على التسوية بواقع $count، وسيتم إرسال رابط الدفع الأول بتاريخ $date.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$count', 'value' => 'payment.claim.installment_counts',     'label' => __('number of installments')],
                    ['key' => '$date',  'value' => 'payment.claim.installment_first_date', 'label' => __('first installment due date')],
                    ['key' => '$claim_number',     'value' => 'payment.claim.claim_number', 'label' => __('claim.claim_number')],
                    ['key' => '$link',              'value' => 'payment.link',              'label' => __('link')],
                    ['key' => '$verification_code', 'value' => 'payment.verification_code', 'label' => __('verification_code')],
                
                ],
            ],

            /* 11 ─ موافقة على الخصم */
            [
                'title' => 'موافقة على الخصم',
                'model' => PaymentLink::class,
                'templates' => [
                    [
                        'name'      => 'إشعار بالموافقة على الخصم',
                        'body' => 'عزيزي العميل، تمت الموافقة على الخصم بقيمة $deducted_amount ر.س. يرجى السداد عبر الرابط: $link. رمز التحقق: ($verification_code). شكراً لتعاونكم.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$link',              'value' => 'payment.link', 'label' => __('Link')],
                    ['key' => '$deducted_amount', 'value' => 'payment.activeDiscount.deducted_amount', 'label' => __('claim.discount.deducted_amount')],
                    ['key' => '$verification_code', 'value' => 'payment.verification_code', 'label' => __('verification_code')],
                    ['key' => '$claim_number',     'value' => 'payment.claim.claim_number', 'label' => __('claim.claim_number')],
                ],
            ],

            /* 12 - موافقة طلب عميل */

            [
                'title' => 'موافقة طلب عميل',
                'model' => RejectedClaim::class,
                'templates' => [
                    [
                        'name'      => 'موافقة طلب عميل',
                        'body' => 'عزيزي العميل تم قبول طلبك بنجاح',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$link',              'value' => 'claim.payment.link', 'label' => __('Link')],
                    ['key' => '$verification_code', 'value' => 'claim.payment.verification_code', 'label' => __('verification_code')],
                    ['key' => '$claim_number',     'value' => 'claim.claim_number', 'label' => __('claim.claim_number')],
                ],
            ],



            /* 13 - رفض طلب عميل */

            [
                'title' => 'رفض طلب عميل',
                'model' => RejectedClaim::class,
                'templates' => [
                    [
                        'name'      => 'رفض طلب عميل',
                        'body' => 'عزيزي العميل تم رفض طلبك بنجاح',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$link',              'value' => 'claim.payment.link', 'label' => __('Link')],
                    ['key' => '$verification_code', 'value' => 'claim.payment.verification_code', 'label' => __('verification_code')],
                    ['key' => '$claim_number',     'value' => 'claim.claim_number', 'label' => __('claim.claim_number')],
                ],
            ],



            /* 14 - تسجيل دخول علم */

            [
                'title' => 'تسجيل دخول علم',
                'model' => ElmRequest::class,
                'templates' => [
                    [
                        'name' => 'تسجيل دخول علم',
                        'body' => 'OTP: $code
                        لتسجيل الدخول',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$code', 'value' => 'otp', 'label' => __('payment_link.url')],
                ],
            ],

            /* 15 ─ إلغاء الخصم   */
            [
                'title' => 'إلغاء الخصم',
                'model' => Discount::class,
                'templates' => [
                    [
                        'name'      => 'إشعار بإلغاء الخصم',
                        'body' => 'عزيزي $debtor_name، تم إلغاء خصم $deducted_amount ر.س على المطالبة ($claim_number) بتاريخ ($cancellation_date). المبلغ المستحق حالياً: $total_amount ر.س. التفاصيل عبر $link برمز التحقق ($verification_code).',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$debtor_name',       'value' => 'claim.debtor_name',  'label' => __('claim.debtor_name')],
                    ['key' => '$claim_number', 'value' => 'claim.claim_number', 'label' => __('claim.claim_number')],
                    ['key' => '$deducted_amount', 'value' => 'deducted_amount', 'label' => __('claim.discount.deducted_amount')],
                    ['key' => '$cancellation_date', 'value' => 'updated_at', 'label' => __('Cancellation Date')],
                    ['key' => '$total_amount', 'value' => 'total_amount', 'label' => __('claim.discount.total_amount')],
                    ['key' => '$link', 'value' => 'payment.link', 'label' => __('Link')],
                    ['key' => '$verification_code', 'value' => 'payment.verification_code', 'label' => __('verification_code')],
                ],
            ],
            /* 16 ─ فشل الدفع */
            [
                'title' => 'فشل الدفع',
                'model' => PaymentTransaction::class,
                'templates' => [
                    [
                        'name' => 'إشعار بفشل عملية الدفع',
                        'body' => 'عزيزي العميل، نأسف لإبلاغكم بأن عملية الدفع الخاصة بمطالبتكم رقم ($claim_number) قد فشلت. رقم العملية: ($transaction_ref)، المبلغ: ($amount) ريال سعودي. يرجى المحاولة مرة أخرى عبر الرابط: $link برمز التحقق ($verification_code) .',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                'variables' => [
                    ['key' => '$claim_number', 'value' => 'payment.claim.claim_number', 'label' => __('claim.claim_number')],
                    ['key' => '$transaction_ref', 'value' => 'transaction_ref', 'label' => __('Transaction Reference')],
                    ['key' => '$amount', 'value' => 'amount', 'label' => __('Amount')],
                    ['key' => '$link', 'value' => 'payment.link', 'label' => __('Link')],
                    ['key' => '$verification_code', 'value' => 'payment.verification_code', 'label' => __('verification_code')],
                ],
            ],

        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        foreach ($this->definitions() as $definition) {
 
            // 1. Find-or-update the MessageTemplate (idempotent by title + model).
            //    No extra attributes to sync here, so update payload is empty.
            $message = MessageTemplate::updateOrCreate(
                [
                    'title' => $definition['title'],
                    'model' => $definition['model'],
                ],
                []
            );
 
            // 2. Sync CustomTemplates by (template_id, name) — update body/is_active/client_id
            //    if the definition changes, instead of silently keeping stale content.
            foreach ($definition['templates'] as $tpl) {
                CustomTemplate::updateOrCreate(
                    [
                        'template_id' => $message->id,
                        'name'        => $tpl['name'],
                    ],
                    array_merge($tpl, ['template_id' => $message->id]),
                );
            }
 
            // 3. Sync variables by (template_id, key) — NOT by key alone.
            //    Scoping by template_id is critical: many templates reuse the same
            //    variable key (e.g. $link, $claim_number, $verification_code) with a
            //    DIFFERENT value path per template. Matching on key alone would let
            //    one template's variable definition silently overwrite another's.
            foreach ($definition['variables'] ?? [] as $variable) {
                $message->variables()->updateOrCreate(
                    [
                        'template_id' => $message->id,
                        'key'         => $variable['key'],
                    ],
                    $variable,
                );
            }
        }
    }
}

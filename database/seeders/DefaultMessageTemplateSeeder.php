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

class DefaultMessageTemplateSeeder extends Seeder
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
                'templates' => [
                    [
                        'name'      => 'موافقة على مطالبة مالية',
                        'body'      => 'عزيزي العميل، نفيدكم بوجود مطالبة مالية لدى (تهيئة) رقم المرجع ($claim_number) بمبلغ وقدره ($amount) ريال سعودي. لمزيد من المعلومات يرجى الاطلاع على المستندات من خلال الرابط: $link. تذكّر دائماً أن الخليجية العامة لن تطلب أي أرقام سرية خاصة بكم بأي حال من الأحوال. رمز التحقق الخاص بكم هو: ($verification_code).',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                
            ],

            /* 2 ─ تأجيل السداد */
            [
                'title' => 'تأجيل السداد',
                'templates' => [
                    [
                        'name'      => 'موافقة على تأجيل السداد',
                        'body'      => 'عزيزي العميل، بناء على طلبكم نفيدكم أنه تم الموافقة على تأجيل السداد، علماً أن موعد الدفعة المستحقة سيكون بتاريخ $due_date بمبلغ ($amount)، وسيصلكم رابط الدفع بالتاريخ المحدد.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                
            ],

            /* 3 ─ موعد السداد الجزئي */
            [
                'title' => 'موعد السداد الجزئي',
                'templates' => [
                    [
                        'name'      => 'إشعار بموعد السداد الجزئي',
                        'body'      => 'عزيزي العميل، نود إشعاركم باستحقاق القسط رقم ($count). نرجو التكرم بسداد المبلغ ($amount). الرجاء الضغط على الرابط التالي للسداد من خلال قنوات الدفع الرسمية المعتمدة: $link. رمز التحقق الخاص بكم هو: ($verification_code). تذكر دائماً أن الخليجية العامة لن تطلب أي أرقام سرية خاصة بكم لأي سبب من الأسباب.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
               
            ],

            /* 4 ─ التحويل للمحامي */
            [
                'title' => 'التحويل للمحامي',
                'templates' => [
                    [
                        'name'      => 'إشعار بالتحويل للمحامي',
                        'body'      => 'تم تحويل المطالبة إلى المحامي للمتابعة القانونية. سيتم التواصل معكم حال تحديث الحالة.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
               
            ],

            /* 5 ─ المخالصة المالية */
            [
                'title' => 'المخالصة المالية',
                'templates' => [
                    [
                        'name'      => 'إشعار بالمخالصة المالية',
                        'body'      => 'عزيزي العميل، تم إصدار المخالصة المالية الخاصة بمطالبتكم. نشكركم على تعاونكم.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                    
            ],

            /* 6 ─ سند القبض */
            [
                'title' => 'سند القبض',
                'templates' => [
                    [
                        'name' => 'إشعار بسند القبض',
                        'body' => 'تم إصدار سند قبض خاص بكم. يمكنكم الاطلاع على التفاصيل عبر الرابط المرسل.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                
            ],

            /* 7 ─ السداد البنكي */
            [
                'title' => 'السداد البنكي',
                'templates' => [
                    [
                        'name'      => 'إشعار بالسداد البنكي',
                        'body'      => 'عزيزي العميل، نرجو منكم إتمام عملية السداد البنكي عبر الرابط المرفق $url. شاكرين تعاونكم.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                
            ],

            /* 8 ─ رمز التحقق (OTP) */
            [
                'title' => 'رمز التحقق',
                'templates' => [
                    [
                        'name'      => 'رمز التحقق لحساب العميل',
                        'body'      => 'شكرًا لاختياركم شركة تهيئة. استخدم رمز التحقق التالي لإتمام العملية: $otp. الرمز صالح لمدة 5 دقائق. لا تشاركه مع أي شخص.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                
            ],

            /* 9 ─ وقت السداد */
            [
                'title' => 'وقت السداد',
                'templates' => [
                    [
                        'name'      => 'تذكير بموعد السداد',
                        'body'      => 'عزيزي العميل، نود إشعاركم بموعد سداد المبلغ المستحق وقدره ($amount). يرجى السداد عبر الرابط التالي: $link. رمز التحقق الخاص بكم هو: ($verification_code). تذكر دائماً أن الخليجية العامة لن تطلب أي أرقام سرية خاصة بكم لأي سبب من الأسباب.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                
            ],

            /* 10 ─ موافقة على السداد الجزئي */
            [
                'title' => 'موافقة على السداد الجزئي',
                'templates' => [
                    [
                        'name'      => 'إشعار بالموافقة على السداد الجزئي',
                        'body'      => 'عزيزي العميل، نفيدكم أنه تمت الموافقة على التسوية بواقع $count، وسيتم إرسال رابط الدفع الأول بتاريخ $date.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                
            ],

            /* 11 ─ موافقة على الخصم */
            [
                'title' => 'موافقة على الخصم',
                'templates' => [
                    [
                        'name'      => 'إشعار بالموافقة على الخصم',
                        'body' => 'عزيزي العميل، تمت الموافقة على الخصم بقيمة $deducted_amount ر.س. يرجى السداد عبر الرابط: $link. رمز التحقق: ($verification_code). شكراً لتعاونكم.',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                
            ],

            /* 12 - موافقة طلب عميل */

            [
                'title' => 'موافقة طلب عميل',
                'templates' => [
                    [
                        'name'      => 'موافقة طلب عميل',
                        'body' => 'عزيزي العميل تم قبول طلبك بنجاح',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                
            ],



            /* 13 - رفض طلب عميل */

            [
                'title' => 'رفض طلب عميل',
                'templates' => [
                    [
                        'name'      => 'رفض طلب عميل',
                        'body' => 'عزيزي العميل تم رفض طلبك بنجاح',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                
            ],



            /* 14 - تسجيل دخول علم */

            [
                'title' => 'تسجيل دخول علم',
                'templates' => [
                    [
                        'name' => 'تسجيل دخول علم',
                        'body' => 'OTP: $code
                        لتسجيل الدخول',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
                
            ],

            /* 15 ─ إلغاء الخصم   */
            [
                'title' => 'إلغاء الخصم',
                'templates' => [
                    [
                        'name'      => 'إشعار بإلغاء الخصم',
                        'body' => 'عزيزي $debtor_name، تم إلغاء خصم $deducted_amount ر.س على المطالبة ($claim_number) بتاريخ ($cancellation_date). المبلغ المستحق حالياً: $total_amount ر.س. التفاصيل عبر $link برمز التحقق ($verification_code).',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
                ],
               
            ],
            /* 16 ─ فشل الدفع */
            [
                'title' => 'فشل الدفع',
                'templates' => [
                    [
                        'name' => 'إشعار بفشل عملية الدفع',
                        'body' => 'عزيزي العميل، نأسف لإبلاغكم بأن عملية الدفع الخاصة بمطالبتكم رقم ($claim_number) قد فشلت. رقم العملية: ($transaction_ref)، المبلغ: ($amount) ريال سعودي. يرجى المحاولة مرة أخرى عبر الرابط: $link برمز التحقق ($verification_code) .',
                        'is_active' => 1,
                        'client_id' => 1,
                    ],
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
            $message = MessageTemplate::where('title',$definition['title'])->first();
 
            // 2. Sync CustomTemplates by (template_id, name) — update body/is_active/client_id
            //    if the definition changes, instead of silently keeping stale content.
            if ($message) {
                # code...
                foreach ($definition['templates'] as $tpl) {
                    CustomTemplate::updateOrCreate(
                        [
                            'template_id' => $message->id,
                            'name'        => $tpl['name'],
                        ],
                        array_merge($tpl, ['template_id' => $message->id]),
                    );
                }
            }
 
           
        }
    }
}

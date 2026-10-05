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
use Illuminate\Support\Facades\DB;

class CategoryMessageVariablesSeeder extends Seeder
{
    /**
     * Template definitions — add new category here with all variables.
     * Each entry:
     *   title      => MessageTemplate title (used as unique key for idempotency)
     *   model      => Eloquent model class the template belongs to
     *   variables  => array of variable definitions (optional)
     */
    public function definitions(): array
    {
        return [

            /* 1 ─ الموافقة */
            [
                'title' => 'الموافقة',
                'model' => PaymentLink::class,
              
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
               
                'variables' => [
                    [
                        'key' => '$claim_number',
                        'value' => 'link.payment.claim.claim_number',
                        'label' => __('claim.claim_number')
                    ],
                    // [
                    //     'key' => '$url',
                    //     'value' => 'url',
                    //     'label' => __('URL')
                    // ],
                    [
                        'key' => '$link',
                        'value' => 'link.payment.link',
                        'label' => __('link')
                    ],
                    [
                        'key' => '$order',
                        'value' => 'link.order',
                        'label' => __('order')
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
               
                'variables' => [
                    ['key' => '$otp',            'value' => 'otp',            'label' => __('verification_code')],
                ],
            ],

            /* 9 ─ وقت السداد */
            [
                'title' => 'وقت السداد',
                'model' => PaymentLink::class,
                
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
               
                'variables' => [
                    ['key' => '$amount', 'value' => 'payment.amount',     'label' => __('ammount of claim')],
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
                
                'variables' => [
                    ['key' => '$link',              'value' => 'payment.link', 'label' => __('Link')],
                    ['key' => '$deducted_amount', 'value' => 'payment.activeDiscount.deducted_amount', 'label' => __('claim.discount.deducted_amount')],
                    ['key' => '$total_amount', 'value' => 'payment.activeDiscount.total_amount', 'label' => __('claim.discount.total_amount')],
                    ['key' => '$amount_after_discount', 'value' => 'payment.activeDiscount.amount_after_discount', 'label' => __('claim.discount.amount_after_discount')],
                    ['key' => '$verification_code', 'value' => 'payment.verification_code', 'label' => __('verification_code')],
                    ['key' => '$claim_number',     'value' => 'payment.claim.claim_number', 'label' => __('claim.claim_number')],
                ],
            ],

            /* 12 - موافقة طلب عميل */

            [
                'title' => 'موافقة طلب عميل',
                'model' => RejectedClaim::class,
              
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
                
                'variables' => [
                    ['key' => '$link',              'value' => 'claim.payment.link', 'label' => __('Link')],
                    ['key' => '$verification_code', 'value' => 'claim.payment.verification_code', 'label' => __('verification_code')],
                    ['key' => '$claim_number',     'value' => 'claim.claim_number', 'label' => __('claim.claim_number')],
                ],
            ],



            /* 14 - تسجيل دخول علم */

            [
                'title' => 'تسجيل دخول علم',
                'model' => VerificationCode::class,

                'variables' => [
                    ['key' => '$code', 'value' => 'otp', 'label' => __('verification_code')],
                ],
            ],

            /* 15 ─ إلغاء الخصم   */
            [
                'title' => 'إلغاء الخصم',
                'model' => Discount::class,
                
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
        DB::transaction(function () use ($definition) {
            // 1. Find-or-update the MessageTemplate (idempotent by title).
            //    No extra attributes to sync here, so update payload is empty.
            $message = MessageTemplate::updateOrCreate(
                [
                    'title' => $definition['title'],
                ],
                ['model' => $definition['model']],
            );
            
            // 2. Sync variables by (template_id, key) — NOT by key alone.
            //    Scoping by template_id is critical: many templates reuse the same
            //    variable key (e.g. $link, $claim_number, $verification_code) with a
            //    DIFFERENT value path per template. Matching on key alone would let
            //    one template's variable definition silently overwrite another's.
                $variables = $definition['variables'] ?? [];
                foreach ($variables as $variable) {
                    $message->variables()->updateOrCreate(
                        [
                            'template_id' => $message->id,
                            'key' => $variable['key'],
                        ],
                        $variable,
                    );
                }


                $currentKeys = collect($variables)->pluck('key');

                $message->variables()
                    ->where('template_id', $message->id)
                    ->whereNotIn('key', $currentKeys)
                    ->delete();
            });
        }
    }
}
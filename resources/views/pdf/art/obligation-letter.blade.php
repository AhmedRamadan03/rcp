@extends('pdf.art.layout')

@section('title')
مطالبة مالية
@endsection

@section('content')
<div class="flex flex-col flex-1">
    <div class="py-6 pt-12 block font-medium text-left" dir="rtl" style="color: #606060;">
        <div class="">
            <span class="py-1.5">التاريخ : </span>
            <span class="py-1.5">{{ \Carbon\Carbon::today()->format('d-m-Y') }}</span>
        </div>
    </div>

    {{-- Title & Body --}}
    <div class="py-2 pt-4 block font-medium" dir="rtl" style="color: #000000; font-family: Calibri, Candara, Segoe, Segoe UI, Optima, Arial, sans-serif;">
        <div class="text-center flex flex-row justify-center items-center py-2" style="font-size: 18px; font-weight: bold;">
        مطالبة مالية
        </div>

        <div class="">
            <span class="text-base py-4 font-bold">السلام عليكم ورحمة الله وبركاته .. </span>
        </div>

        <div class="text-base py-4">
            فإنه بموجب التقرير الصادر برقم
            (<strong>{{ $claim->accident_number ?? '' }}</strong>)
            ولثبوت احقية الشركه بالرجوع عليكم ومطالبتكم بما تم دفعه
            للطرف المتضرر حسب لوائح (<strong>هيئة التأمين</strong>), وان يتم دفع المبلغ المستحق
            لشركه {{ config()->get('app.company_name_ar') }}
            على الحساب الموضح أدناه وذلك لإنهاء جميع الإجراءات المتعلقة بالحادث وتجنب اتخاذ أي اجراء قانوني بحقكم.
        </div>

        {{-- Bank account details --}}

        @php
        $bank_account =
        App\Models\BankAccount::where('status', App\Enums\BankAccountStatusEnum::ACTIVE)->first() ?? null;
        @endphp

        <div class="text-base py-1" dir="rtl">
            حساب {{ config()->get('app.company_name_ar') }} في {{ $bank_account->bank_name ?? '' }}:
        </div>
        <div class="text-base py-1 font-bold" dir="rtl">
            ( رقم الحساب: {{ $bank_account->account_number ?? '' }} )
        </div>
        <div class="text-base py-1 font-bold" dir="rtl">
            ( رقم الأيبان: {{ $bank_account->iban ?? '' }} )
        </div>
    </div>


    <div class="pt-3 py-6">
        <div class="relative">
            <table class="rtl:text-right font-bold table table-bordered text-left w-full" style="font-size: 14px; border: solid 1px #868686dd" dir="rtl">

                <tbody>
                    <tr class="py-3">
                        <td style="width:50%;background-color: #e2e2e2;border:1px solid #868686dd" class="text-center py-3">اسم المُطالب</td>
                        <td style="border:1px solid #868686dd; text-align:center">{{ $claim->debtor_name ?? '' }}</td>
                    </tr>

                    <tr class="py-3">
                        <td style="width:50%;background-color: #e2e2e2;border:1px solid #868686dd" class="text-center py-3">رقم السجل</td>
                        <td style="border:1px solid #868686dd; text-align:center">{{ $claim->debtor_nid ?? '' }}</td>
                    </tr>

                    <tr class="py-3">
                        <td style="width:50%;background-color: #e2e2e2;border:1px solid #868686dd" class="text-center py-3">رقم المطالبة</td>
                        <td style="border:1px solid #868686dd; text-align:center">{{ $claim->claim_number ?? '' }}</td>
                    </tr>

                    <tr class="py-3">
                        <td style="width:50%;background-color: #e2e2e2;border:1px solid #868686dd" class="text-center py-3">تاريخ الحادث</td>
                        <td style="border:1px solid #868686dd; text-align:center">
                            {{ $claim->accident_date ? \Carbon\Carbon::parse($claim->accident_date)->format('F j, Y') : '' }}
                        </td>
                    </tr>

                    <tr class="py-3">
                        <td style="width:50%;background-color: #e2e2e2;border:1px solid #868686dd" class="text-center py-3">سبب المطالبة</td>
                        <td style="border:1px solid #868686dd; text-align:center">
                            @if ($claim->debtor_type == \App\Enums\DebtorType::INSURED->value)
                            {{ \App\Enums\ReturnReason::getString((int) $claim->return_reason) }}
                            @else
                            لا يوجد تأمين
                            @endif
                        </td>
                    </tr>

                    <tr class="py-3">
                        <td style="width:50%;background-color: #e2e2e2;border:1px solid #868686dd" class="text-center py-3">لوحة سيارة ( المُؤمن )</td>
                        <td style="border:1px solid #868686dd; text-align:center">{{ $claim->plate_number ?? '' }}</td>
                    </tr>

                    <tr class="py-3">
                        <td style="width:50%;background-color: #e2e2e2;border:1px solid #868686dd" class="text-center py-3">لوحة سيارة ( المُتضرر )</td>
                        <td style="border:1px solid #868686dd; text-align:center">{{ $claim->victim_plate_number ?? '' }}</td>
                    </tr>


                    <tr class="py-3">
                        <td style="width:50%;background-color: #e2e2e2;border:1px solid #868686dd" class="text-center py-3">مبلغ المطالبة الاجمالي</td>
                        <td style="border:1px solid #868686dd; text-align:center">{{ $claim->amount ?? '' }}</td>
                    </tr>


                </tbody>
            </table>
        </div>

    </div>

    <div class="pt-6 text-center" style="color: #000000; font-family: Arial, Helvetica, sans-serif;">
        <div style="font-weight: bold; margin-bottom: 5px;">
            وتفضلوا بقبول فائق التقدير والاحترام
        </div>
        <div style="font-weight: bold;">
            {{ config()->get('app.company_name_ar') }}
        </div>
    </div>



</div>


<!-- CR Number - Sidebar (Rotated) — fixed using writing-mode so text is never clipped -->
{{-- <span style="
        position: fixed;
        right: 15px;
        bottom: 15%;
        font-size: 8px;
        color: #333;
        writing-mode: vertical-rl;
        text-orientation: mixed;
        transform: rotate(180deg);
        white-space: nowrap;
        line-height: 1;
    ">
    سجل تجاري 1010270371 - C.R. 1010270371
</span> --}}

<!-- Footer -->
<footer style="position: fixed; bottom: 0; left: 0; right: 0; padding: 15px 40px 20px; background: white; width: 100%;">
    <div style="position: relative; border-top: 1px solid #2063c1; padding-top: 12px;">
        {{-- corner bracket marks --}}
        <span style="position:absolute; top:-1px; left:0; width:10px; height:10px; border-left:1.5px solid #2063c1; border-top:1.5px solid #2063c1;"></span>
        <span style="position:absolute; top:-1px; right:0; width:10px; height:10px; border-right:1.5px solid #2063c1; border-top:1.5px solid #2063c1;"></span>

        <div style="display:flex; justify-content:space-between; align-items:flex-start;">

            {{-- LEFT: Arabic address / CR / contact info --}}
            <div dir="rtl" style="text-align:left; font-size:10px; color:#2063c1; line-height:1.7;">
                <p style="margin:0;">صندوق بريد 3485، الرياض 13316 - 8450، المملكة العربية السعودية</p>
                <p style="margin:0;">رقم السجل التجاري: 1010270371 .C.R</p>
                <p style="margin:0;">هاتف: 9666 440 11 966+ .:Tel</p>
                <p style="margin:0;">alrajhitakaful.com</p>
            </div>

            {{-- RIGHT: Arabic company details --}}
            <div dir="rtl" style="text-align:right; font-size:10px; color:#2063c1; line-height:1.7;">
                <p style="margin:0;">شركة الراجحي للتأمين التعاوني (تكافل الراجحي)</p>
                <p style="margin:0;">شركة مساهمة سعودية خاضعة لإشراف ومراقبة هيئة التأمين،</p>
                <p style="margin:0;">برقم ترخيص (ت م ن 200911/22)،</p>
                <p style="margin:0;">رأس مالها 2,000,000,000 ريال سعودي</p>
            </div>

        </div>
    </div>
</footer>
@endsection

<!-- for margin bottom -->
<style>
    @media print {
        body {
            margin-bottom: 150px;
        }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
        }
    }

</style>

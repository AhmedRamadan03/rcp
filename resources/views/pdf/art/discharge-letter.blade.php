@extends('pdf.art.layout')

@section('title')
    مخالصة نهائية
@endsection

@section('content')
    <div class="">

        @php
        $title = 'نموذج مخالصة نهائية';

        if ($claim?->debtor_type === 'third party') {
            $title = 'نموذج مخالصة نهائية بموجب حق الحلول
            (أفراد)';
        } elseif ($claim?->debtor_type === 'insured') {
            $title = 'نموذج مخالصة نهائية بموجب حق الرجوع 
            (استرداد من المشتركين بموجب المادة الخامسة من الوثيقة الموحدة للتأمين الإلزامي على المركبات)';
        }
    @endphp

    {{-- Title --}}
    <div class="py-1 text-center" dir="rtl">
        <h1 class="font-bold" style="font-size: 12px; color: #1a1a1a;">
            {!! nl2br($title) !!}
        </h1>
    </div>

        {{-- Main Info Table --}}
        <div class="py-1">
            <div class="relative">
                <table class="text-center w-full" style="font-size: 13px; border-collapse: collapse; border: solid 1.5px #585858;" dir="rtl">
                    <tbody>

                        {{-- Row 1: اسم المُطالب (full width) --}}
                        <tr style="color: #333;">
                            <td colspan="5" class="px-3 py-2 font-bold" style="border: solid 1.5px #585858; background-color: #f0f0f0; width: 50%;">
                                اسم المُطالب:
                            </td>
                            <td colspan="5" class="px-3 py-2" colspan="3" style="border: solid 1.5px #585858;">
                                {{ $claim?->debtor_name }}
                            </td>
                        </tr>

                        {{-- Row 2: رقم الهوية + نسبة المسؤولية --}}
                        <tr style="color: #333;">
                            <td class="px-3 py-2 font-bold" colspan="2" style="border: solid 1.5px #585858; background-color: #f0f0f0; width: 25%;">
                                رقم الهوية:
                            </td>
                            <td class="px-3 py-2" colspan="3" style="border: solid 1.5px #585858; width: 25%;">
                                {{ $claim?->debtor_nid }}
                            </td>
                            <td class="px-3 py-2 font-bold" colspan="2" style="border: solid 1.5px #585858; background-color: #f0f0f0; width: 25%;">
                                نسبة المسؤولية عن الحادث:
                            </td>
                            <td class="px-3 py-2" colspan="3" style="border: solid 1.5px #585858; width: 25%;">
                                {{ $claim?->mistake_percentage ?? '' }}
                            </td>
                        </tr>

                        {{-- Row 3: رقم المطالبة + تاريخ الحادث --}}
                        <tr style="color: #333;">
                            <td colspan="2" class="px-3 py-2 font-bold" style="border: solid 1.5px #585858; background-color: #f0f0f0;">
                                رقم المطالبة:
                            </td>
                            <td colspan="3" class="px-3 py-2" style="border: solid 1.5px #585858;">
                                {{ $claim?->claim_number }}
                            </td>
                            <td colspan="2" class="px-3 py-2 font-bold" style="border: solid 1.5px #585858; background-color: #f0f0f0;">
                                تاريخ الحادث:
                            </td>
                            <td colspan="3" class="px-3 py-2" style="border: solid 1.5px #585858;">
                                {{ $claim?->accident_date ? \Carbon\Carbon::parse($claim->accident_date)->format('Y-m-d') : '' }}
                            </td>
                        </tr>

                        {{-- Row 4: رقم لوحة سيارة المتسبب (full width) --}}
                        <tr style="color: #333;">
                            <td colspan="5" class="px-3 py-2 font-bold" style="border: solid 1.5px #585858; background-color: #f0f0f0;">
                                رقم لوحة سيارة (المُتسبب في الحادث)
                            </td>
                            <td class="px-3 py-2" colspan="5" style="border: solid 1.5px #585858;">
                                {{ $claim?->plate_number }}
                            </td>
                        </tr>

                        {{-- Row 5: رقم لوحة سيارة المتضرر (full width) --}}
                        <tr style="color: #333;">
                            <td colspan="5" class="px-3 py-2 font-bold" style="border: solid 1.5px #585858; background-color: #f0f0f0;">
                                رقم لوحة سيارة (لطرف الثالث)
                            </td>
                            <td class="px-3 py-2" colspan="5" style="border: solid 1.5px #585858;">
                                {{ $claim?->victim_plate_number ?? '' }}
                            </td>
                        </tr>

                        {{-- Row 6: طريقة الدفع --}}
                        <tr style="color: #333;">
                            <td colspan="1" class="px-3 py-2 font-bold" style="border: solid 1.5px #585858; background-color: #f0f0f0;">
                                طريقة الدفع:
                            </td>
                            <td class="px-3 py-2" colspan="1" style="border: solid 1.5px #585858;">
                                <span style="display: inline-flex; align-items: center; gap: 6px; margin-left: 16px;">
                                    <span style="display:inline-block; width:14px; height:14px; border: 1.5px solid #585858; background:#fff; vertical-align:middle;"></span>
                                    دفع الكتروني
                                </span>
                                
                            </td>
                            <td class="px-3 py-2" colspan="1" style="border: solid 1.5px #585858;">
                               
                                <span style="display: inline-flex; align-items: center; gap: 6px; margin-left: 16px;">
                                    <span style="display:inline-block; width:14px; height:14px; border: 1.5px solid #585858; background:#fff; vertical-align:middle;"></span>
                                    خدمة سداد
                                </span>
                              
                            </td>
                            <td class="px-3 py-2" colspan="1" style="border: solid 1.5px #585858;">
                                  <span style="display: inline-flex; align-items: center; gap: 6px; margin-left: 16px;">
                                    {{-- تحويل is always checked --}}
                                    <span style="display:inline-block; width:14px; height:14px; border: 1.5px solid #585858; background:#fff; vertical-align:middle; text-align:center; line-height:14px; font-size:12px;">✓</span>
                                    تحويل
                                </span>
                                
                            </td>
                            <td class="px-3 py-2" colspan="1" style="border: solid 1.5px #585858;">
                                  
                                <span style="display: inline-flex; align-items: center; gap: 6px;">
                                    <span style="display:inline-block; width:14px; height:14px; border: 1.5px solid #585858; background:#fff; vertical-align:middle;"></span>
                                    شيك مصدّق
                                </span>
                            </td>
                            <td colspan="2" class="px-3 py-2" style="border: solid 1.5px #585858; white-space: nowrap;">
                                شيك رقم / سداد: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            </td>
                            <td colspan="3" class="px-3 py-2" style="border: solid 1.5px #585858; white-space: nowrap;">
                               
                            </td>
                        </tr>

                        {{-- Row 7: المبلغ المستلم --}}
                        <tr style="color: #333;">
                            <td colspan="5" class="px-3 py-2 font-bold text-center" style="border: solid 1.5px #585858; background-color: #f0f0f0;">
                                المبلغ المُستلم:
                            </td>
                            <td class="px-3 py-2 font-bold text-center" colspan="5" style="border: solid 1.5px #585858;">
                                {{ $claim->payments()->first()->amount ?? '00000' }} ريال فقط لا غير
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>

        @php
        $declarationText = '';

        if ($claim?->debtor_type === 'insured') {

            $declarationText = "
        نحن شركة " . config('app.company_name_ar') . " نؤكّد بأننا قد استلمنا المبلغ المذكور أعلاه من المشترك (المؤمن له) المذكور اسمه أعلاه؛ وذلك عن سداد مبلغ التعويض المستحق عليه بموجب تقرير رقم (" . ($claim?->accident_number ?? '') . ") وتاريخ (" . ($claim?->accident_date ? \Carbon\Carbon::parse($claim->accident_date)->format('Y-m-d') : '') . ") جراء الحادث الذي تسبب به في التاريخ المذكور أعلاه.

        وبهذا فإننا نخلي ذمة المشترك (المؤمن له) من جميع الالتزامات والإجراءات القانونية والمطالبات والدّعاوى المتعلقة بالمطالبة المذكور بياناتها أعلاه.
        ";

        }

        elseif ($claim?->debtor_type === 'third party') {

            $declarationText = "
        نحن شركة " . config('app.company_name_ar') . " نؤكّد بأننا قد استلمنا المبلغ المذكور أعلاه من المتسبب في الحادث (الطرف الثالث) المذكور اسمه أعلاه؛ وذلك عن سداد مبلغ التعويض المستحق عليه لصالح عميلنا/ شركتنا بموجب تقرير رقم (" . ($claim?->accident_number ?? '') . ") وتاريخ (" . ($claim?->accident_date ? \Carbon\Carbon::parse($claim->accident_date)->format('Y-m-d') : '') . ") جراء الحادث الذي تسبب به الطرف الثالث في التاريخ المذكور أعلاه.

        وبهذا فإننا نخلي ذمة المتسبب في الحادث (الطرف الثالث) من جميع الالتزامات والإجراءات القانونية والمطالبات والدّعاوى المتعلقة بالمطالبة المذكور بياناتها أعلاه.
        ";

        }
        @endphp

{{-- إقرار Section --}}
<div class="py-3" dir="rtl" style="color: #1a1a1a; padding: 30px;">
    <div class="font-bold py-2" style="font-size: 15px; background-color: #ededed; padding: 6px;">
        إقرار
    </div>

    <div style="padding: 16px; font-size: 13px; line-height: 2;">
        {{ $declarationText }}
    </div>

    {{-- Grey highlighted end bar --}}
    <div style="background-color:#ededed; height:18px;"></div>

        <div style="text-align: center; display: flex; justify-content: center; align-items: center; margin-top: 10px;">
            @php
            $logoFile = getCompanyStampName();
            $logoPath = public_path($logoFile);
            $ext = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
            $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/' . $ext;
            $logoSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
            @endphp

            <img alt="logo" src="{{ $logoSrc }}" width="130" style="display: block; margin: 0 auto;" />
        </div>


    {{-- Electronic issuance note --}}
    <div style="text-align:center; margin-top: 40px;">
        <span style="text-decoration: underline; font-weight: bold; font-size:13px;">
            تم اصدار هذه المخالصة الكترونياً
        </span>
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

@section('scripts')
    <script>
        // QR Code
        let fileId = "{{ $discharge->id }}";
        new QRCode(document.getElementById("qr-code"), "{{ config('app.url') . "/" . 'is-valid/discharge/' }}" + fileId);
    </script>
@endsection
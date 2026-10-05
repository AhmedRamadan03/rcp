@extends('pdf.art.layout')

@section('title')
    سند قبض
@endsection

@section('content')
    <div class="flex flex-col flex-1">
        <div class="py-6 pt-12 block font-bold" dir="rtl" style="color: #606060;">
            <div>
                <span class="py-1.5">التاريخ : </span>
                <span class="py-1.5" >{{ \Carbon\Carbon::parse($link?->voucher?->created_at)->format('d-m-Y')  }}</span>
            </div>
            <div>السيد/ة المكرم المحترم: {{ $link->payment->claim?->debtor_name }}</div>
            <div>السلام عليكم و رحمة الله و بركاته .... و بعد:</div>
        </div>

        <div class="pt-3 py-6">
            <div class="relative">
                <table class="rtl:text-right font-bold text-left w-full" style="font-size: 14px; border: solid 2px #585858" dir="rtl">
                    <thead class="text-gray uppercase">
                    <tr>
                        <td colspan="2" class="p-1 text-white" style="background-color: #585858">
                            <h1 class="text-medium text-center">
                                الموضوع / إيصال دفع
                                ({{ $link->order }})
                            </h1>
                        </td>
                    </tr>
                    </thead>
                    <tbody>
                    <tr style="color: #606060;">
                        <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                            إسم المستفيد
                        </td>
                        <td class="px-4 py-1" style="border: solid 2px #585858;">{{ $link->payment->claim?->debtor_name }}</td>
                    </tr>

                    <tr style="color: #606060;">
                        <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                            رقم الهوية
                        </td>
                        <td class="px-4 py-1" style="border: solid 2px #585858;">{{ $link->payment->claim?->debtor_nid }}</td>
                    </tr>

                    <tr style="color: #606060;">
                        <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                            رقم المطالبة
                        </td>
                        <td class="px-4 py-1" style="border: solid 2px #585858;">{{ $link->payment->claim?->claim_number }} </td>
                    </tr>

                    <tr style="color: #606060;">
                        <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                            رقم الحادث
                        </td>
                        @if(config('app.client_name') == \App\Enums\Clients::GGI->value)
                            <td class="px-4 py-1" style="border: solid 2px #585858;">
                                {{ $link->payment->claim?->policy_number }}
                            </td>
                        @else
                            <td class="px-4 py-1" style="border: solid 2px #585858;">
                                {{ $link->payment->claim?->accident_number }}
                            </td>
                        @endif
                    </tr>

                    <tr style="color: #606060;">
                        <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                           لوحة سيارة ( المُؤمن )
                        </td>
                        <td class="px-4 py-1" style="border: solid 2px #585858;">
                            {{ $link->payment->claim?->plate_number }}
                        </td>
                    </tr>
                    <tr style="color: #606060;">
                        <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                        لوحة سيارة ( المُتضرر )
                        </td>
                        <td class="px-4 py-1" style="border: solid 2px #585858;">
                            {{ $link->payment->claim?->victim_plate_number }}
                        </td>
                    </tr>

                    <tr style="color: #606060;">
                        <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                            نوع السيارة
                        </td>
                        <td class="px-4 py-1" style="border: solid 2px #585858;">
                            {{ $link->payment->claim?->car_type }}
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <div class="py-4" dir="rtl" style="color: #606060;">
            <p class="py-2">
                بالإشارة الي الموضوع أعلاه نفيدكم بأن المذكور قد أتم سداد مبلغ
                (  {{ $link->amount }} )
                ريال من اصل
                (  {{ $link->payment->amount }}  )
                ريال المترتبة عليه اثر الحادث المروري برقم
                ( {{ $link->payment->claim?->policy_number }} )
                و بإنتظار سداد المبلغ المتبقي خلال المدة النظامية.
            </p>
            <br>
        </div>

        <div class="flex justify-end items-start pb-4" style="color: #606060">
            <div class="text-center">
                <span class="font-bold">
                    .شاكرين كريم تفهمكم
                </span>
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

@section('scripts')
    <script>
        // QR Code
        let fileId = "{{ $voucher->id }}";
        new QRCode(document.getElementById("qr-code"), "{{ config('app.url') . "/" . 'is-valid/voucher/' }}" + fileId);
    </script>
@endsection

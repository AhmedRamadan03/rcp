@extends('pdf.layout')

@section('title')
    مخالصة نهائية
@endsection

@section('content')
    <div class="flex flex-col flex-1">
        <div class="py-6 pt-12 block font-bold" dir="rtl" style="color: #606060;">
            <div>
                <span class="py-1.5">التاريخ : </span>
                <span class="py-1.5" >{{ now()->format("Y-m-d")  }}</span>
            </div>
            <div>السيد/ة المكرم المحترم: {{ $claim?->debtor_name }}</div>
            <div>السلام عليكم و رحمة الله و بركاته .... و بعد:</div>
        </div>

        <div class="pt-3 py-6">
            <div class="relative">
                <table class="rtl:text-right font-bold text-left w-full" style="font-size: 14px; border: solid 2px #585858" dir="rtl">
                    <thead class="text-gray uppercase">
                    <tr>
                        <td colspan="2" class="p-1 text-white" style="background-color: #585858">
                            <h1 class="text-medium text-center">
                                الموضوع /       مخالصة نهـائـية
                            </h1>
                        </td>
                    </tr>
                    </thead>

                    <tbody>
                        <tr style="color: #606060;">
                            <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                                رقم الحـادث
                            </td>
                            @if(config('app.client_name') == \App\Enums\Clients::GGI->value)
                                <td class="px-4 py-1">{{ $claim?->policy_number }}</td>
                            @else
                                <td class="px-4 py-1">{{ $claim?->accident_number }}</td>
                            @endif
                        </tr>

                        <tr style="color: #606060;">
                            <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                                إسم المستفيد
                            </td>
                            <td class="px-4 py-1" style="border: solid 2px #585858;">{{ $claim?->debtor_name }}</td>
                        </tr>

                        <tr style="color: #606060;">
                            <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                                رقم الهوية
                            </td>
                            <td class="px-4 py-1" style="border: solid 2px #585858;">{{ $claim?->debtor_nid }}</td>
                        </tr>

                        <tr style="color: #606060;">
                            <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                                رقم المطالبة
                            </td>
                            <td class="px-4 py-1" style="border: solid 2px #585858;">{{ $claim?->claim_number }} </td>
                        </tr>

                        <tr style="color: #606060;">
                            <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                                رقم اللوحة
                            </td>
                            <td class="px-4 py-1" style="border: solid 2px #585858;">
                                {{ $claim?->plate_number }}
                            </td>
                        </tr>

                        <tr style="color: #606060;">
                            <td class="font-bold px-2 py-1 whitespace-nowrap" style="width: 200px; border: solid 2px #585858">
                                نوع السيارة
                            </td>
                            <td class="px-4 py-1" style="border: solid 2px #585858;">
                                {{ $claim?->car_type }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <div class="py-4" dir="rtl" style="color: #606060;">
            <p class="py-2">
                بالإشارة إلى الموضوع أعلاه نفيدكم بأن المذكور  قد أتم سداد المستحقات المترتبة عليه بمبلغ
                {{ $claim->payments()->first()->amount }}
                ريال و تعتبر هذه بمثابة مخالصة وابراء ذمة للمذكور أعلاه ولا توجد عليه أي مبالغ مستحقة ماليا .
            </p>
            <br>
            <span> ملاحـظة : </span>
            <p class="py-4">
                تعتبر المخالصة للأضرار المترتبة على تعويض المؤمن ما لم يوجد أمر قضائي لأي أضرار جسدية أو وفيات او أطراف اخرى في الحادث
            </p>
        </div>

        <div class="flex justify-end items-start pb-4" style="color: #606060">
            <div class="text-center">
                <span class="font-bold">
                    .شاكرين كريم تفهمكم
                </span>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
    <script>
        // QR Code
        let fileId = "{{ $discharge->id }}";
        new QRCode(document.getElementById("qr-code"), "{{ config('app.url') . "/" . 'is-valid/discharge/' }}" + fileId);
    </script>
@endsection

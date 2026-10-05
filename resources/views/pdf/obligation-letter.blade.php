@extends('pdf.layout')

@section('title')
     إثبات مديونية
@endsection

@section('content')
    <div class="flex flex-col flex-1">
        <div class="py-6 pt-12 block font-medium" dir="rtl" style="color: #606060;">
            <div>
                <span class="py-1.5">رقم المرجع : </span>
                <span class="py-1.5" >{{ $claim->claim_number ?? '' }}</span>
            </div>
            <div class="">
                <span class="py-1.5">التاريخ : </span>
                <span class="py-1.5" >{{ \Carbon\Carbon::today()->format('d-m-Y') }}</span>
            </div>
            <div class="">
                <span class="py-1.5">إسم العميل : </span>
                <span class="py-1.5" >{{ $claim->debtor_name ?? '' }}</span>
            </div>
            <div class="">
                <span class="py-1.5">رقم الهوية : </span>
                <span class="py-1.5" >{{ $claim->debtor_nid ?? '' }}</span>
            </div>
        </div>

        <div class="py-2 pt-4 block font-medium" dir="rtl" style="color: #606060;">
            <div class="text-center text-xl flex flex-row justify-center items-center py-2 font-bold">
                خطاب إثبات مديونية
            </div>

            <div class="">
                <span class="text-base py-4 font-bold">إلي من يهمه الأمر :- </span>
            </div>

            <div class="text-base py-4">
                تشهد {{  config()->get('app.company_name_ar') }} بأن السيد /
                {{ $claim->debtor_name ?? '' }}
                هوية رقم
                {{ $claim->debtor_nid ?? '' }}
                مدين لشركة {{  config()->get('app.company_name_ar') }} جراء حادث مروري وذلك حتى تاريخ إصدار هذا الخطاب.
            </div>
        </div>



        <div class="pt-3 py-6">
            <div class="relative">
                <table class="rtl:text-right font-bold text-left w-full" style="font-size: 14px; border: solid 2px #585858" dir="rtl">
                    <thead class="uppercase">
                        <tr style="background-color: #585858; border: solid 2px #585858;">
                            <th class="p-1 text-white text-center" style="border: solid 2px #585858;">
                                رقم الحادث
                            </th>
                            <th class="p-1 text-white text-center" style="border: solid 2px #585858;">
                                نوع المطالبة
                            </th>
                            <th class="p-1 text-white text-center" style="border: solid 2px #585858;">
                                السبب
                            </th>
                            <th class="p-1 text-white text-center" style="border: solid 2px #585858;">
                                المبلغ
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="color: #606060;">
                            <td class="text-center" style="border: solid 2px #585858;">
                                @if( config('app.client_name') == \App\Enums\Clients::GGI->value)
                                    {{ $claim->policy_number ?? '' }}
                                @else
                                    {{ $claim->accident_number ?? '' }}
                                @endif
                            </td>

                            <td class="text-center" style="border: solid 2px #585858;">
                                {{ __('claim.' . str_replace(' ', '_', $claim->debtor_type ?? '')) }}
                            </td>

                            <td class="text-center" style="border: solid 2px #585858;">
                                @if($claim->debtor_type  == \App\Enums\DebtorType::INSURED->value)
                                    {{ \App\Enums\ReturnReason::getString((int)$claim->return_reason) }}
                                @else
                                    {{ \App\Enums\ReturnReason::getString(0) }}
                                @endif
                            </td>

                            <td class="text-center" style="border: solid 2px #585858;">
                                {{ $claim?->payments()?->first()->amount }} {{ __('claim.sar') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <div class="py-4" dir="rtl" style="color: #606060;">
            <p class="py-2">
                تنويه : تم إصدار هذا الخطاب بناء على طلب العميل و دون أدنى مسؤولية على {{  config()->get('app.company_name_ar') }} و مدة صلاحية هذا الخطاب شهر
            </p>
        </div>

{{--        <div class="flex justify-end items-start pb-4" style="color: #606060">--}}
{{--            <div class="text-center">--}}
{{--                <span class="font-bold">--}}
{{--                    .شاكرين كريم تفهمكم--}}
{{--                </span>--}}
{{--            </div>--}}
{{--        </div>--}}
    </div>
@endsection

@php
    $subject = __('otp.login_otp');
@endphp
<x-mail.base :subject="$subject">
    <div class="flex items-start gap-x-3 rounded-md p-2 mb-0.5 bg-cyan-50 border border-cyan-300" >
        <div class="flex-1">
            <div class="flex">
                <div class="flex-1">
                    <span class="text-xs text-gray-400" >{{ now() }}</span>
                </div>
            </div>
            <div>
                <p class="text-sm text-gray-500 mt-4" >
                    <span>{{ __('otp.your_otp_to_login_is') }}</span>
                    {{ $otp }}
                </p>
            </div>
            <div class="flex items-center gap-x-1.5 py-1.5"></div>
        </div>
    </div>
</x-mail.base>

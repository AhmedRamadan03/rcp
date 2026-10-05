<x-mail.base :subject="__('Reset Your Password')">
    <div class="flex flex-col items-start gap-y-3 rounded-md p-2 mb-0.5 bg-cyan-50 border border-cyan-300 ">
        <div class="flex-1">
            <div class="flex">
                <div class="flex-1">
                    <span>Hello {{ $user->name }},</span> <br>
                    <span class="text-xs text-gray-400">
                       {{__('We received a request to reset your password')}}
                    </span>
                    <br>
                    <br>
                    <p>
                        {{__('Please click the link below to set a new password') }}:
                    </p>
                    <br>
                    <a href="{{ $resetUrl }}"
                        style="background:#dc3545;color:#fff;padding:10px 15px;text-decoration:none;">
                        {{__('Reset Password')}}
                    </a>
                    <p>
                        {{__('If you did not request this, you can safely ignore this email')}} .
                    </p>
                    <p>
                        {{__('thank you')}}
                    </p>
                </div>

            </div>

        </div>
    </div>
</x-mail.base>
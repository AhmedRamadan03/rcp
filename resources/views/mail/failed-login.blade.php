<x-mail.base :subject="__('user.failed_login_attempt')">
    <div class="flex flex-col items-start gap-y-3 rounded-md p-2 mb-0.5 bg-cyan-50 border border-cyan-300 ">
        <div class="flex-1">
            <div class="flex">
                <div class="flex-1">
                    <span class="text-xs text-gray-400">
                        there is a login attempt on your account. If it wasn’t you, please change the password from the
                        link below
                    </span>
                    <br>
                    <br>
                    <br>
                    <a href="{{ $resetUrl }}"
                        style="background:#dc3545;color:#fff;padding:10px 15px;text-decoration:none;">
                        Reset Password
                    </a>

                </div>

            </div>

        </div>
    </div>
</x-mail.base>
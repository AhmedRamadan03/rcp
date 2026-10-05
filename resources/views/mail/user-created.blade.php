<x-mail.base :subject="__('user.hi_user')">
    <div class="flex flex-col items-start gap-y-3 rounded-md p-2 mb-0.5 bg-cyan-50 border border-cyan-300 ">
        <div class="flex-1">
            <div class="flex">
                <div class="flex-1">
                    <span class="text-xs text-gray-400">
                        {{ __("user.user_new_mail_message",["username" => $user->name])}}
                    </span>
                    <p>
                        <a href="{{config('app.frontend_url')}}">
                            {{config('app.frontend_url')}}
                        </a>
                    </p>
                    <p>
                        <span class="text-xs text-gray-400">
                            {{ __("user.email")}} : {{ $user->email }}
                        </span><br>
                        <span class="text-xs text-gray-400">
                            {{ __("user.current_password")}} : password
                        </span>
                    </p>
                </div>

            </div>

        </div>
    </div>
</x-mail.base>
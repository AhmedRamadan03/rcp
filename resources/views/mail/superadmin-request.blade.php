<x-mail.base :subject="__('calendar.reminder')">
    <div class="flex flex-col items-start gap-y-3 rounded-md p-2 mb-0.5 bg-cyan-50 border border-cyan-300 " >
        <div class="flex-1">
            <div class="flex">
                <div class="flex-1">
                    <span class="text-xs text-gray-400" >{{ __("request.you_have_new_request")}}</span>
                </div>

            </div>

        </div>
    </div>
</x-mail.base>

<x-mail.base :subject="__('calendar.reminder')">
    <div class="flex flex-col items-start gap-y-3 rounded-md p-2 mb-0.5 bg-cyan-50 border border-cyan-300 " >
        <div class="flex-1">
            <div class="flex">
                <div class="flex-1">
                    <span class="text-xs text-gray-400" >{{ __("claim.this_is_reminder_email_about_what_have_been_schedule") . ' ' . $comment->claim->claim_number}}</span>
                </div>

                <div class="flex-1">
                    <span class="text-xs text-gray-400" >{{ $comment->body}}</span>
                </div>

                <div class="flex-1">
                    <span class="text-xs text-gray-400" >{{ \Carbon\Carbon::parse($comment->reminder_date)->format('Y-m-d')}}</span>
                </div>

            </div>

        </div>
    </div>
</x-mail.base>

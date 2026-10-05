<x-mail.base :subject="__('claim.reminder')">
    <div class="flex flex-col items-start gap-y-3 rounded-md p-2 mb-0.5 bg-cyan-50 border border-cyan-300 " style="background-color: #ddd; padding: 10px; margin-top: 10px; border-radius: 8px;">
        <div class="flex-1">
            <div class="flex">
                <div class="flex-1">
                    <span class="text-xs text-gray-400">{{ __("claim.you_have_set_a_reminder_in_claim") . ' - ' . __('Claim Count') . ' (' . $counter . ')' }}</span>
                </div>

               
                <div class="flex-1">
                    <a href="{{ config('app.frontend_url').'/'. 'claims?reminder=reminder' }}" style="display: flex; flex-direction : row; align-items: center; row-gap: 0.75rem; border: rgb(96,165,250,1); height: 2rem; border-radius: 0.25rem; padding: 0.5rem" class=" flex items-center gap-x-1.5 border border-red-300 h-8 rounded p-2 bg-red-200 ">
                        <span class="text-gray-700"> {{ __('view Reminder Claims') }} </span>
                    </a>
                </div>

            </div>

        </div>
    </div>
</x-mail.base>
@foreach($escalations as $type => $escalationItems)

<x-mail.base :subject="$escalationItems->first()->notification?->title_en">
    <div class="flex items-start gap-x-3 rounded-md p-2 mb-0.5 bg-cyan-50 border border-cyan-300 ">
        <div class="flex-1">
            <!-- <span class="text-xs text-gray-400">{{$escalationItems->first()->notification?->created_at->diffForHumans()}}</span> -->
            <!-- <h1 class="font-extrabold text-gray-700">{{ app()->getLocale() == 'ar' ? $escalationItems->first()->notification?->title_ar : $escalationItems->first()->notification?->title_en }}</h1> -->
        </div>
        <div class="flex-1" style="background-color: #ddd; padding: 10px; margin-top: 10px; border-radius: 8px;">

            <div>
                <h3><b> {{__('Claim Count')}} :  ( {{ count($escalationItems) }} )</b></h3>
                @if($type == 'default_on_payment')
                <a href="{{ config('app.frontend_url').'/claims?escalation_type=default_in_payment' }}" style="display: flex; flex-direction: row; align-items: center; row-gap: 0.75rem; border: rgb(96,165,250,1); height: 2rem; border-radius: 0.25rem; padding: 0.5rem" class=" flex items-center gap-x-1.5 border border-red-300 h-8 rounded p-2 bg-red-200 ">
                    <span class="text-gray-700"> {{ __('Default on Payment Claims') }} </span>
                </a>
                @elseif($type == 'payment_time')
                <a href="{{ config('app.frontend_url').'/'. 'claims?escalation_type=payment_time' }}" style="display: flex; flex-direction : row; align-items: center; row-gap: 0.75rem; border: rgb(96,165,250,1); height: 2rem; border-radius: 0.25rem; padding: 0.5rem" class=" flex items-center gap-x-1.5 border border-red-300 h-8 rounded p-2 bg-red-200 ">
                    <span class="text-gray-700"> {{ __('view Payment Time Claims') }} </span>
                </a>
                @elseif($type == 'claim_reject')
                <a href="{{ config('app.frontend_url').'/'. 'claims?escalation_type=claim_reject' }}" style="display: flex; flex-direction : row; align-items: center; row-gap: 0.75rem; border: rgb(96,165,250,1); height: 2rem; border-radius: 0.25rem; padding: 0.5rem" class=" flex items-center gap-x-1.5 border border-red-300 h-8 rounded p-2 bg-red-200 ">
                    <span class="text-gray-700"> {{ __('view Claim client requests') }} </span>
                </a>
                @elseif($type == 'status_request')
                <a href="{{ config('app.frontend_url').'/'. 'claims?escalation_type=status_request' }}" style="display: flex; flex-direction : row; align-items: center; row-gap: 0.75rem; border: rgb(96,165,250,1); height: 2rem; border-radius: 0.25rem; padding: 0.5rem" class=" flex items-center gap-x-1.5 border border-red-300 h-8 rounded p-2 bg-red-200 ">
                    <span class="text-gray-700"> {{ __('view Status Request Claims') }} </span>
                </a>
                @endif
            </div>
           
        </div>
    </div>
</x-mail.base>
@endforeach
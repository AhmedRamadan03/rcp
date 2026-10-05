@props([
    "notification" => [],
    "collector" => [],
    "regarding" => "",
    "collectorTitle" => "",
    "color" => "green",
    "view_status" => 0
])
{{--{{dd(json_decode($notification[$regarding][0]->links))}}--}}
@if(array_key_exists($regarding, $notification) AND count($notification[$regarding]))
    @if(count($notification[$regarding]) > 1 && $collector[$regarding])
        <div>
            <div class="flex items-start gap-x-3 rounded-md p-3 mb-0.5 bg-{{$color}}-50 border border-{{$color}}-300 " >
                <div>
                    <img src="{{ asset('public/icons/noti-plus.svg') }}" alt="">
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="text-xs text-gray-400" >{{$notification[$regarding][count($notification[$regarding]) - 1]?->created_at->diffForHumans()}}</span>
                            <h1 class="font-extrabold text-gray-700">{{ count($notification[$regarding]) . $collectorTitle }}</h1>
                        </div>
                        <div>
                            <button wire:click="collect('{{$regarding}}')" class="border border-{{$color}}-300 w-8 h-8 rounded-full p-2 bg-{{$color}}-300 ">
                                <img src="{{ asset('public/icons/arrow-down.svg') }}" alt="arrow-down">
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-1.5">
                <div class="h-2 rounded-xl rounded-t-none bg-{{$color}}-200 border border-{{$color}}-300 border-t-0"></div>
            </div>
        </div>
    @else
        @if(count($notification[$regarding]) > 1)
            <div class="bg-{{$color}}-100 border rounded-md border-{{$color}}-300 p-3" >
                <div class="flex justify-between items-center pb-1">
                    <div>
                        <span class="text-xs text-gray-400" >{{$notification[$regarding][count($notification[$regarding]) - 1]?->created_at->diffForHumans()}}</span>
                        <h1 class="font-extrabold text-gray-700">{{count($notification[$regarding]) . $collectorTitle }}</h1>
                    </div>
                    <div class="flex items-center gap-x-3 ">

                        @if($view_status == 0)
                            <button wire:click="asRead({{ $notification[$regarding]->pluck('id')  }})"  class=" flex items-center gap-x-1.5 border border-{{$color}}-300 h-8 rounded p-2 bg-{{$color}}-200 ">
                                <img src="{{ asset('public/icons/check-check.svg') }}" width="12" height="12" alt="check-check">
                                <span class="text-gray-700">{{ __('notification.mark_all_as_read')  }}</span>
                            </button>
                        @endif

                        <button wire:click="collect('{{$regarding}}')" class="border border-{{$color}}-300 w-8 h-8 rounded-full p-2 bg-{{$color}}-200 ">
                            <img src="{{ asset('public/icons/arrow-up.svg') }}" alt="arrow-UP">
                        </button>
                    </div>
                </div>
        @endif

        <div class="space-y-2" >
             @foreach($notification[$regarding] as $notifi)
                 <div class="flex items-start gap-x-3 rounded-md p-2 mb-0.5 bg-{{$color}}-50 border border-{{$color}}-300 " >
                     <div class="pt-1">

                         <img src="{{ asset('public/icons/noti-plus.svg') }}" alt="noti-plus" width="16" height="16">

                     </div>
                     <div class="flex-1">
                         <div class="flex">
                             <div class="flex-1">
                                 <span class="text-xs text-gray-400" >{{$notifi?->created_at->diffForHumans()}}</span>
                                 <h1 class="font-extrabold text-gray-700">{{ app()->getLocale() == 'ar' ? $notifi?->title_ar : $notifi?->title_en }}</h1>
                             </div>

                             @if($notifi?->viewed)
                                 <span class="text-xs"> Read at {{ $notifi?->updated_at  }} </span>
                             @else
                                 <button wire:click="asRead([{{ $notifi?->id }}])" class=" flex items-center gap-x-1.5 border hover:bg-{{$color}}-300 h-8 rounded-full p-2 bg-{{$color}}-200 ">
                                     <img src="{{ asset('public/icons/x.svg') }}" width="12" height="12" alt="check-check">
                                 </button>
                             @endif

                         </div>
                         <div>
                             <p class="text-sm text-gray-500" >{{ app()->getLocale() == 'ar' ? $notifi?->description_ar : $notifi?->description_en }}</p>
                         </div>
                         <div class="flex items-center gap-x-1.5 py-1.5">
                             @if($notifi?->links)
                                 @foreach( json_decode($notifi?->links) as $link)

                                     @if(isset($link->type) && $link->type == App\Enums\LinkType::FRONT_END->value)
                                         <button x-on:click="$openModal('{{$link?->url}}')" wire:key="{{$loop->index}}" wire:click="setDataId({{json_encode($link)}},[{{$notifi->id}}])"  class=" flex items-center gap-x-1.5 border border-{{$color}}-300 h-8 rounded p-2 bg-{{$color}}-200 ">
                                             <span class="text-gray-700">{{ app()->getLocale() == 'ar' ? $link->label_ar ?? 'عرض تفاصيل الطلب' : $link->label_en ?? 'open link info' }}</span>
                                         </button>
                                     @else
                                         <button wire:key="{{$loop->index}}" wire:click="redirectAsRead('{{$link?->url}}', [{{$notifi->id}}])"  class=" flex items-center gap-x-1.5 border border-{{$color}}-300 h-8 rounded p-2 bg-{{$color}}-200 ">
                                             <span class="text-gray-700">{{ app()->getLocale() == 'ar' ? $link->label_ar ?? 'عرض تفاصيل الطلب' : $link->label_en ?? 'open link info' }}</span>
                                         </button>
                                     @endif

                                 @endforeach
                             @endif
                         </div>
                     </div>
                 </div>
             @endforeach
        </div>

        @if(count($notification[$regarding]) > 1)
            </div>
        @endif
    @endif
@endif

@props(['message' => []])
<div>
    @empty(!$message)
        <div class=" w-full my-4 flax justify-center">
            <div
                class="flex justify-between p-4 rounded-md
                    @if ($message['type'] == 'error')
                        bg-red-50 border border-red-300
                    @elseif($message['type'] == 'unauthorized')
                        bg-blue-300 opacity-55 border border-blue-300
                    @else
                        bg-green-50 border border-green-300
                    @endif
                ">
                <div class="flex gap-3 sm:items-center">
                    <div>
                        @if ($message['type'] == 'error')
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-6 h-6 text-red-500">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>

                        @elseif($message['type'] == 'unauthorized')
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round"
                                      d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @endif
                    </div>
                    <p class="
                        @if ($message['type'] == 'error')
                            text-red-500
                        @elseif($message['type'] == 'unauthorized')
                            text-white
                        @else
                            text-green-500
                        @endif
                        sm:text-sm">
                        {{ $message['body'] }}
                        {{-- <a href="javascript:void(0)" class="underline font-medium hover:text-green-700">check it out?</a> --}}
                    </p>
                </div>
            </div>
        </div>
    @endempty
</div>

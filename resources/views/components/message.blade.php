
{{--<x-notifications />--}}
@if(session()->has('success'))
    <div class="p-3 my-3 text-white bg-green-500 rounded-md message my-4" >
        <div class="flex items-start gap-x-3">
            <img  src="/icons/notif.svg" width="24" height="24" alt="">
            <div>
                <strong class="text=-2xl">Success</strong>
                <div class="flex justify-between items-center">
                    <div> {{ session()->get('success') }} </div>
                    <div class="px-3 flex justify-end border-gray-500 gap-x-2">
                        @isset($link)
                            <x-icon-button
                                hx-get="{{$link}}"
                                hx-swap="innerHTML"
                                hx-target="#main-content"
                                hx-indicator="#indicator"
                                label="{{$link_label}}"
                                icon="arrow-left"
                            />
                        @endisset
                    </div>
                </div>

                @isset($url)
                    <div class="text-left pt-3">
                        <a
                            href="{{$url}}"
                            class="inline-flex items-center gap-x-2 px-2 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            target="_blank"
                        >Download</a>
                    </div>
                @endisset
            </div>
        </div>
    </div>
@endif

@if(session()->has('error'))
    <div class="p-3 my-3 text-gray-900 bg-red-300 rounded-md message my-4" >
        <div class="flex items-start gap-x-3">
            <img src="/icons/notif.svg" width="24" height="24" alt="">
            <div>
                <strong class="text=-2xl">{{ __('system.error') }}</strong>
                <div class="flex justify-between items-center">
                    <div> {{ session()->get('error') }} </div>
                    <div class="px-3 flex justify-end border-gray-500 gap-x-2">
                        @isset($link)
                            <x-icon-button
                                hx-get="{{$link}}"
                                hx-swap="innerHTML"
                                hx-target="#main-content"
                                hx-indicator="#indicator"
                                label="{{$link_label}}"
                                icon="arrow-left"
                            />
                        @endisset
                    </div>
                </div>

                @isset($url)
                    <div class="text-left pt-3">
                        <a
                            href="{{$url}}"
                            class="inline-flex items-center gap-x-2 px-2 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            target="_blank"
                        >Download</a>
                    </div>
                @endisset
            </div>

        </div>
    </div>
@endif

<div  class="text-gray-900 bg-red-300 rounded-md message my-4" >
    @if(isset($errors))
        @if ($errors->any())
            <div class="alert alert-danger p-3 my-3 ">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>


@if(session()->has('unauthorized'))
    <div class="p-3 my-3 text-gray-900 bg-red-300 rounded-md message" >
        <div class="flex items-start gap-x-3">
            <img src="/icons/shield-minus.svg" width="24" height="24" alt="">
            <div>
                <strong class="text-md">{{ __('system.error') }}</strong>
                <div class="flex justify-between items-center">
                    <div class="text-sm"> {{ session()->get('unauthorized') }} </div>
                    <div class="px-3 flex justify-end border-gray-500 gap-x-2">
                        @isset($link)
                            <x-icon-button
                                hx-get="{{$link}}"
                                hx-swap="innerHTML"
                                hx-target="#main-content"
                                hx-indicator="#indicator"
                                label="{{$link_label}}"
                                icon="arrow-left"
                            />
                        @endisset
                    </div>
                </div>

                @isset($url)
                    <div class="text-left pt-3 ">
                        <a
                            href="{{$url}}"
                            class="inline-flex items-center gap-x-2 px-2 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            target="_blank"
                        >Download</a>
                    </div>
                @endisset
            </div>

        </div>
    </div>
@endif

@if(session()->has('notification'))

    <div class="p-3 my-3 text-white bg-indigo-500 rounded-md message" >
        <div class="flex items-start gap-x-3">
            <img src="/icons/notif.svg" width="24" height="24" alt="">
            <div>
                <strong class="text-md">Notification</strong>
                <div class="flex justify-between items-center">
                    <div class="text-sm"> {{ session()->get('notification') }} </div>
                    <div class="px-3 flex justify-end border-gray-500 gap-x-2">
                        @isset($link)
                            <x-icon-button
                                hx-get="{{$link}}"
                                hx-swap="innerHTML"
                                hx-target="#main-content"
                                hx-indicator="#indicator"
                                label="{{$link_label}}"
                                icon="arrow-left"
                            />
                        @endisset
                    </div>
                </div>

                @isset($url)
                    <div class="text-left pt-3 ">
                        <a
                            href="{{$url}}"
                            class="inline-flex items-center gap-x-2 px-2 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            target="_blank"
                        >Download</a>
                    </div>
                @endisset
            </div>

        </div>
    </div>

@endif


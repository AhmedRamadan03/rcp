<nav class="">

    <div>
        <div class="flex flex-col  items-stretch p-2 space-y-2 rounded-md {{ env('APP_NAME') == 'testing_ggi' ? 'bg-red-400' : '' }}">
            <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                <img src="{{ asset('public/icons/dashboard.svg') }}" alt="">
                <span>{{ __('dashboard.dashboard') }}</span>
            </x-nav-link>
        </div>
    </div>
    <ul class="flex flex-col items-stretch p-2 space-y-2 rounded-md ">
        <li>
            <x-nav-link href="{{ route('notifications') }}" :active="request()->routeIs('notifications')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                <img src="{{ asset('public/icons/notif.svg') }}" alt="Notifications">
                <div class="flex w-full pe-1.5 justify-between items-center">
                    <span>{{ __("notification.notifications")  }}</span>
                    @php
                        if (count(\App\Models\Notification::whereJsonContains("to", auth()->id())->where("viewed", "0")->pluck('viewed'))){
                            echo '<span class="p-1 h-1 w-1 bg-red-400 rounded-full " ></span>';
                        }
                    @endphp
                </div>
            </x-nav-link>

        </li>

        <li>
            <x-nav-link href="{{ route('requests') }}" :active="request()->routeIs('requests')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                <img src="{{ asset('public/icons/monitor.svg') }}" alt="Request">
                <div class="flex w-full pe-1.5 justify-between items-center">
                    <span>{{ __("request.requests")  }}</span>
                </div>
            </x-nav-link>

        </li>

        <li>
            <x-nav-link href="{{ route('claims.index') }}" :active="request()->routeIs('claims.index')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                <img src="{{ asset('public/icons/claim.svg') }}" alt="">
                <span>{{ __('claim.claims') }}</span>
            </x-nav-link>
        </li>

        <li>
            <x-nav-link href="{{ route('transactions') }}" :active="request()->routeIs('transactions')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                <img src="{{ asset('public/icons/history.svg') }}" alt="">
                <span>{{ __('dashboard.transactions_history') }}</span>
            </x-nav-link>
        </li>

        @if(auth()->user()->role_id == \App\Enums\Roles::SUPERVISOR->value || auth()->user()->role_id == \App\Enums\Roles::SUPER_ADMIN->value)

            <li>
                <x-nav-link href="{{ route('targets') }}" :active="request()->routeIs('targets')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                    <img src="{{ asset('public/icons/target.svg') }}" alt="">
                    <span>{{ __('dashboard.targets') }}</span>
                </x-nav-link>
            </li>

        @endif

        <li>
            <x-nav-link href="{{ route('reports.index') }}" :active="request()->routeIs('reports.index')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                <img src="{{ asset('public/icons/report.svg') }}" alt="">
                <span>{{ __('report.reports') }}</span>
            </x-nav-link>
        </li>

        <li>
            <x-nav-link href="/calendar" :active="request()->routeIs('calendar')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                <img src="{{ asset('public/icons/calendar.svg') }}" alt="">
                <span>{{ __('dashboard.calendar') }}</span>
            </x-nav-link>
        </li>

        <li>
            <x-nav-link href="{{ route('users.index') }}" :active="request()->routeIs('users.index')" class="flex items-center justify-start  w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                <img src="{{ asset('public/icons/user.svg') }}" alt="">
                <span>{{ __('dashboard.users') }}</span>
            </x-nav-link>
        </li>

        <li>
            <x-nav-link href="{{ route('roles.index') }}" :active="request()->routeIs('roles.index')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                <img src="{{ asset('public/icons/shield.svg') }}" alt="">
                <span>{{ __('dashboard.roles') }}</span>
            </x-nav-link>
        </li>

        <li>
            <x-nav-link href="/settings" :active="request()->routeIs('settings')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                <img src="{{ asset('public/icons/settings.svg') }}" alt="">
                <span>{{ __('dashboard.settings') }}</span>
            </x-nav-link>
        </li>

        @if(auth()->user()->role_id == \App\Enums\Roles::SUPER_ADMIN->value)
            <li>
                <x-nav-link href="{{ route('authentication-log') }}" :active="request()->routeIs('authentication-log')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                    <img src="{{ asset('public/icons/fingerprint.svg') }}" alt="">
                    <span>{{ __('dashboard.authentication_log') }}</span>
                </x-nav-link>
            </li>
        @endif


        <li>
            @include('language-switch')
        </li>

        <li class="py-4">
            <x-nav-link href="/account/profile" :active="request()->routeIs('account')" class="flex items-center justify-start w-12 h-11 p-0 text-sm hover:bg-gray-200 rounded-md">
                <img src="{{ asset('public/icons/user.svg') }}" alt="">
                <span>{{ __('dashboard.account') }}</span>
            </x-nav-link>
        </li>
    </ul>
</nav>

<a {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center gap-x-2 px-2 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-800 tracking-widest hover:bg-gray-300 hover:border-gray-400  focus:bg-gray-400 active:bg-gray-400 focus:outline-none focus:ring-2 focus:border-0 focus:ring-teal-500 focus:ring-offset-2 transition ease-in-out duration-150 cursor-pointer ']) }} >

    @isset($icon)
        <img src="/icons/{{ $icon }}.svg" alt="" width="15">
    @endisset

    <div class="hidden md:block">
        @isset($label)
            {{ __( $label ) }}
        @endisset
    </div>

</a>

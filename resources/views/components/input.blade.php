<div class="w-full flex flex-col" >
    {!!  $slot !!}
    @isset($label)
        <label class="dark:text-gray-50 pb-1 text-gray-800 " >{{ $label }}</label>
    @endisset

    <input
        {!! $attributes->merge(['class' => 'w-full shadow-sm border-2 inline-block px-4 py-2 text-gray-800 duration-150 font-medium dark:bg-slate-700 border-gray-500 rounded-lg hover:border-indigo-500 active:border-indigo-700 md:text-sm ']) !!}  />


</div>

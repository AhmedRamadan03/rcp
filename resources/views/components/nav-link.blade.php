@props(['active'])

@php
$classes = ($active ?? false)
            ? 'ps-3 p-1.5 gap-x-2 w-full px-1 pt-1 bg-gray-200  text-sm font-medium leading-5  focus:outline-none  transition duration-150 ease-in-out'
            : 'ps-3 p-1.5 gap-x-2 w-full px-1 pt-1  text-sm font-medium leading-5  hover:border-gray-300 dark:hover:border-gray-700 focus:outline-none   focus:border-gray-300 dark:focus:border-gray-700 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

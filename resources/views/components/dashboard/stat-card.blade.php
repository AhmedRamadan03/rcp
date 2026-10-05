<div class="w-[25em] border-t-[5px] {{ $borderColor ?? 'border-t-blue-400' }} h-24 px-3 py-3 bg-white rounded-md shadow-md flex flex-col">
    <div class="flex justify-between w-full">
        <div class="flex flex-col">
            <div class="flex">
                <p>{{ $number }}</p>
                <p class="text-blue-500 text-sm mt-1 px-4">
                    ({{ $label }}) 
                </p>
            </div>
            <div class="mt-2">
                <p class="text-gray-500">{{ $description }}</p>
            </div>
        </div>
        <div class="flex">
            <span class="w-12 h-12 {{ $iconBg ?? 'bg-slate-500' }} text-white rounded-full flex items-center justify-center">
                {{-- <img src="{{ $icon }}" alt="Icon" class="w-6 h-6 "> --}}
                <div class="{{ $class ?? 'text-white' }}">
                    {!! file_get_contents(public_path("icons/{$name}.svg")) !!}
                </div>
            </span>  
        </div>
    </div>
</div>

<div class="w-[15em] border-2 border-gray-300 rounded-xl  px-3 py-3 bg-white  flex flex-col justify-center">
    <div class="flex flex-col items-center  w-full">

        <div class="flex ">
            <span class="w-12 h-12 {{ $iconBg ?? 'bg-slate-500' }} text-white rounded-full flex items-center justify-center">
                <i class="fa-solid fa-user-nurse fa-xl"></i> </span>
        </div>
        <div class="mt-2">
            <p class="text-center">{{ $number }}</p>
            <p>{{ $name }}</p>
        </div>



    </div>

</div>
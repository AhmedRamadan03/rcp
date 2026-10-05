<section class="side pt-24">

    <header class="flex flex-row justify-between">
        <div class="title p-2">
            @isset($header)
                {{ $header }}
            @endisset
        </div>




    </header>
    <section class="side-content p-2">
        {{ $slot }}
    </section>
    <footer>
        <div class="options">
            @isset($footer)
                {{ $footer }}</div>
            @endisset
    </footer>
</section>

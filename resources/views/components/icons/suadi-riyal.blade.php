@php
    // Read the SVG file
    $svg = file_get_contents(public_path('icons/saudi-riyal-symbol.svg'));
    // Remove any XML declaration (if present)
    $svg = preg_replace('/<\?xml.*?\?>/', '', $svg);
    // Merge attributes and convert to string so the passed classes are injected into the <svg> tag
    $attrs = (string) $attributes->merge(['class' => '']);
    $svg = preg_replace('/<svg\b/', '<svg ' . $attrs, $svg, 1);
@endphp

{!! $svg !!}

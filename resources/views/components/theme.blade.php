@php
    $fonts = config('theme.fonts');
@endphp
<link href="{{ $fonts['url'] }}" rel="stylesheet">
<style>
    :root {
@foreach (config('theme.colors') as $colorName => $color)
        --theme-{{ str_replace('_', '-', $colorName) }}: {{ $color }};
@endforeach
        {{-- Unescaped: a <style> element does not decode entities, so {{ }} would break the quotes in a font stack. --}}
        --theme-font-sans: '{!! $fonts['sans']['family'] !!}', {!! $fonts['sans']['fallback'] !!};
        --theme-font-mono: '{!! $fonts['mono']['family'] !!}', {!! $fonts['mono']['fallback'] !!};
    }
</style>

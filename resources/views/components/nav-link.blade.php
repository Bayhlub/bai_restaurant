@props(['active'])

{{-- Sits on the coloured area bar, so states are drawn in white rather than indigo. --}}
@php
$classes = ($active ?? false)
    ? 'inline-flex items-center rounded-md bg-white/15 px-3 py-2 text-sm font-semibold text-white transition'
    : 'inline-flex items-center rounded-md px-3 py-2 text-sm font-medium text-white/75 hover:bg-white/10 hover:text-white transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

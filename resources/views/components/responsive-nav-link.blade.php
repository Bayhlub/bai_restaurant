@props(['active'])

@php
$classes = ($active ?? false)
    ? 'block w-full ps-3 pe-4 py-2 border-s-4 border-white text-start text-base font-semibold text-white bg-white/15 focus:outline-none transition'
    : 'block w-full ps-3 pe-4 py-2 border-s-4 border-transparent text-start text-base font-medium text-white/75 hover:text-white hover:bg-white/10 focus:outline-none transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

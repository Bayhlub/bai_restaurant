@props([
    'size' => 'h-8 w-8',
    'showName' => false,
    'secondaryName' => true,
    'tagline' => false,
    'nameClass' => 'text-lg font-bold',
])

@php
    // The current language leads; the other name follows underneath.
    $isLao = app()->getLocale() === 'lo';
    $primaryName = $isLao ? config('restaurant.name_lo') : config('app.name');
    $secondaryNameText = $isLao ? config('app.name') : config('restaurant.name_lo');
@endphp

{{-- Steaming bowl in a ring. Drawn with currentColor so it works white on the
     coloured staff bars and solid black on the thermal receipt. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <svg class="{{ $size }} shrink-0" viewBox="0 0 48 48" fill="none" stroke="currentColor"
         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="24" cy="24" r="21" />
        <path d="M9 25 L39 25 A20 20 0 0 1 9 25 Z" fill="currentColor" stroke="none" />
        <path d="M17 21 c-2.5 -3 2.5 -4.5 0 -7.5" />
        <path d="M24 21 c-2.5 -3.5 2.5 -5.5 0 -9" />
        <path d="M31 21 c-2.5 -3 2.5 -4.5 0 -7.5" />
    </svg>

    @if ($showName)
        <span class="leading-tight">
            <span class="{{ $nameClass }}">{{ $primaryName }}</span>

            @if ($secondaryName)
                <span class="block text-xs font-medium opacity-75">{{ $secondaryNameText }}</span>
            @endif

            @if ($tagline)
                <span class="block text-[0.65rem] font-medium uppercase tracking-[0.2em] opacity-70">{{ __('Lao Cuisine') }}</span>
            @endif
        </span>
    @endif
</span>

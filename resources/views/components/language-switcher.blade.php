@props(['onDark' => false])

@php
    $current = app()->getLocale();
    $languages = ['lo' => 'ລາວ', 'en' => 'EN'];

    $border = $onDark ? 'border-white/30' : 'border-gray-300';
    $active = $onDark ? 'bg-white text-gray-900' : 'bg-gray-800 text-white';
    $idle = $onDark ? 'text-white/80 hover:bg-white/10' : 'bg-white text-gray-600 hover:bg-gray-100';
@endphp

{{-- Links go through a dedicated route rather than the current URL: inside a Livewire
     poll the "current URL" would be /livewire/update, which cannot be opened directly. --}}
<div {{ $attributes->merge(['class' => 'inline-flex overflow-hidden rounded-md border text-sm '.$border]) }}>
    @foreach ($languages as $code => $label)
        <a href="{{ route('lang.switch', $code, absolute: false) }}"
           class="px-2.5 py-1 transition {{ $current === $code ? $active : $idle }}">
            {{ $label }}
        </a>
    @endforeach
</div>

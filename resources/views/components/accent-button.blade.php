@props(['as' => 'button'])

@php $area = \App\Enums\Area::current(); @endphp

{{-- Primary action in the current area's colour. Renders as <a> when given href. --}}
<{{ $attributes->has('href') ? 'a' : $as }}
    {{ $attributes->merge([
        'class' => 'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 '.$area->accentClasses(),
    ]) }}>
    {{ $slot }}
</{{ $attributes->has('href') ? 'a' : $as }}>

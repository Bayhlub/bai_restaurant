@props(['title', 'subtitle' => null])

@php $area = \App\Enums\Area::current(); @endphp

{{-- Title strip tinted with the area's accent; `actions` slot sits on the right. --}}
<div class="border-b {{ $area->headerClasses() }}">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-8">
        <div class="min-w-0">
            <h1 class="truncate text-xl font-bold text-gray-900 sm:text-2xl">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-0.5 text-sm text-gray-600">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>

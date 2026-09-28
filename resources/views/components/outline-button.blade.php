{{-- Secondary action: neutral, pairs with x-accent-button. --}}
<{{ $attributes->has('href') ? 'a' : 'button' }}
    {{ $attributes->merge([
        'class' => 'inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2',
    ]) }}>
    {{ $slot }}
</{{ $attributes->has('href') ? 'a' : 'button' }}>

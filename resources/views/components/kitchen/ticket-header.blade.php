@props(['order'])

@php $minutes = (int) $order->created_at->diffInMinutes(); @endphp

<div class="flex items-center justify-between">
    <div>
        <span class="text-2xl font-black text-gray-900">{{ __('Table') }} {{ $order->session->table->number }}</span>
        <span class="ms-2 text-sm text-gray-500">#{{ $order->daily_number }}</span>
    </div>
    <div class="text-right text-sm {{ $minutes >= 10 ? 'font-bold text-red-600' : 'text-gray-500' }}">
        {{ $order->created_at->format('H:i') }}<br>
        <span class="text-xs">{{ $minutes }} {{ __('min') }}</span>
    </div>
</div>

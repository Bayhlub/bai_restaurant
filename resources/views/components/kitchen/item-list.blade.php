@props(['order'])

<ul class="mt-2 divide-y">
    @foreach ($order->items as $item)
        <li wire:key="item-{{ $item->id }}" class="py-1.5 {{ $item->isRejected() ? 'text-gray-400 line-through' : 'text-gray-900' }}">
            <span class="text-lg font-bold">{{ $item->qty }}×</span>
            <span class="font-medium">{{ $item->name_lo }}</span>
            <span class="text-sm text-gray-500">{{ $item->name_en }}</span>
            @if ($item->note)
                <div class="text-sm font-medium text-orange-700">✎ {{ $item->note }}</div>
            @endif
        </li>
    @endforeach
</ul>

@if ($order->note)
    <div class="mt-2 rounded-md bg-orange-50 px-2 py-1 text-sm text-orange-800">✎ {{ $order->note }}</div>
@endif

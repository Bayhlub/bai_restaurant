<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    /** Pending order ids seen on the last render, used to detect brand-new tickets. */
    public array $knownPendingIds = [];

    public bool $availabilityOpen = false;

    public ?string $toast = null;

    /** Minutes after which a ticket that is still not cooking is flagged as late. */
    public const LATE_AFTER_MINUTES = 10;

    public function mount(): void
    {
        $this->knownPendingIds = $this->pendingOrderIds();
    }

    #[Computed]
    public function orders(): Collection
    {
        return Order::active()
            ->with(['items', 'session.table'])
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function newOrders(): Collection
    {
        return $this->orders->where('status', OrderStatus::Pending)->values();
    }

    #[Computed]
    public function cookingOrders(): Collection
    {
        return $this->orders->whereIn('status', [OrderStatus::Accepted, OrderStatus::Cooking])->values();
    }

    #[Computed]
    public function readyOrders(): Collection
    {
        return $this->orders->where('status', OrderStatus::Ready)->values();
    }

    #[Computed]
    public function menuByCategory(): Collection
    {
        return Category::where('is_active', true)
            ->with(['menuItems' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();
    }

    /** Called by wire:poll; alerts when a ticket appears that we have not seen before. */
    public function refresh(): void
    {
        unset($this->orders);

        $current = $this->pendingOrderIds();
        $fresh = array_diff($current, $this->knownPendingIds);

        if ($fresh !== []) {
            $tables = $this->orders
                ->whereIn('id', $fresh)
                ->map(fn (Order $order) => __('Table').' '.$order->session->table->number)
                ->unique()
                ->implode(', ');

            $this->dispatch('staff-alert',
                title: trans_choice('{1} New order|[2,*] :count new orders', count($fresh), ['count' => count($fresh)]),
                body: $tables,
                tag: 'kitchen-new-order',
            );
        }

        $this->knownPendingIds = $current;
    }

    public function rejectItem(int $itemId): void
    {
        $item = OrderItem::with('order', 'menuItem')->findOrFail($itemId);

        if (! $item->order->isPending()) {
            return;
        }

        $item->reject(__('Sold out'));

        // A rejected dish is almost always sold out; stop customers ordering it again today.
        $item->menuItem?->update(['is_available' => false]);

        $this->toast = __(':item marked sold out. Re-enable it under Availability.', ['item' => $item->name]);
        $this->afterChange();
    }

    public function unrejectItem(int $itemId): void
    {
        $item = OrderItem::with('order')->findOrFail($itemId);

        if (! $item->order->isPending()) {
            return;
        }

        $item->unreject();
        $this->afterChange();
    }

    public function startCooking(int $orderId): void
    {
        Order::findOrFail($orderId)->startCooking();
        $this->afterChange();
    }

    public function markReady(int $orderId): void
    {
        Order::findOrFail($orderId)->markReady();
        $this->afterChange();
    }

    public function markServed(int $orderId): void
    {
        Order::findOrFail($orderId)->markServed();
        $this->afterChange();
    }

    public function toggleAvailability(int $menuItemId): void
    {
        $item = MenuItem::findOrFail($menuItemId);
        $item->update(['is_available' => ! $item->is_available]);

        unset($this->menuByCategory);
    }

    public function dismissToast(): void
    {
        $this->toast = null;
    }

    /** @return array<int, int> */
    private function pendingOrderIds(): array
    {
        return Order::where('status', OrderStatus::Pending)->pluck('id')->all();
    }

    private function afterChange(): void
    {
        unset($this->orders);
        $this->knownPendingIds = $this->pendingOrderIds();
    }
}; ?>

<div class="min-h-[calc(100vh-4rem)]" wire:poll.3s="refresh">
    <x-page-header :title="__('Kitchen')" :subtitle="__('Live orders from the tables')">
        <x-slot name="actions">
            <x-staff-alerts />
            <x-outline-button type="button" wire:click="$set('availabilityOpen', true)">🍽 {{ __('Availability') }}</x-outline-button>
        </x-slot>
    </x-page-header>

    @if ($toast)
        <div class="mx-4 mt-4 flex items-center justify-between rounded-lg border border-amber-300 bg-amber-100 px-4 py-2 text-sm text-amber-900">
            <span>{{ $toast }}</span>
            <button type="button" wire:click="dismissToast" class="ms-3 text-lg font-bold leading-none">×</button>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-3">
        {{-- NEW --}}
        <div class="rounded-xl border-t-4 border-amber-500 bg-white/60 p-3">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-amber-800">
                {{ __('New') }}
                <span class="rounded-full bg-amber-200 px-2 py-0.5 text-xs text-amber-900">{{ $this->newOrders->count() }}</span>
            </h2>
            <div class="space-y-3">
                @foreach ($this->newOrders as $order)
                    @php $late = $order->created_at->diffInMinutes() >= $this::LATE_AFTER_MINUTES; @endphp
                    <div wire:key="order-{{ $order->id }}" class="rounded-xl border bg-white p-3 shadow-sm {{ $late ? "border-red-400 ring-2 ring-red-400" : "border-gray-200" }}">
                        <x-kitchen.ticket-header :order="$order" />

                        <ul class="mt-2 divide-y">
                            @foreach ($order->items as $item)
                                <li wire:key="item-{{ $item->id }}" class="flex items-center justify-between gap-2 py-1.5">
                                    <div class="min-w-0 {{ $item->isRejected() ? 'text-gray-400 line-through' : 'text-gray-900' }}">
                                        <span class="text-lg font-bold">{{ $item->qty }}×</span>
                                        <span class="font-medium">{{ $item->name_lo }}</span>
                                        <span class="text-sm text-gray-500">{{ $item->name_en }}</span>
                                        @if ($item->note)
                                            <div class="text-sm font-medium text-orange-700">✎ {{ $item->note }}</div>
                                        @endif
                                    </div>
                                    @if ($item->isRejected())
                                        <button type="button" wire:click="unrejectItem({{ $item->id }})" class="shrink-0 rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700">{{ __('Undo') }}</button>
                                    @else
                                        <button type="button" wire:click="rejectItem({{ $item->id }})" wire:confirm="{{ __('Mark :item as sold out?', ['item' => $item->name_lo]) }}"
                                                class="shrink-0 rounded-md bg-red-100 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-200">✖ {{ __('Sold out') }}</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if ($order->note)
                            <div class="mt-2 rounded-md bg-orange-50 px-2 py-1 text-sm text-orange-800">✎ {{ $order->note }}</div>
                        @endif

                        @if ($order->hasBillableItems())
                            <button type="button" wire:click="startCooking({{ $order->id }})"
                                    class="mt-3 w-full rounded-lg bg-blue-600 py-2.5 font-bold text-white hover:bg-blue-700">
                                🔥 {{ __('Start cooking') }}
                            </button>
                        @else
                            <button type="button" wire:click="startCooking({{ $order->id }})" wire:confirm="{{ __('Cancel this whole order?') }}"
                                    class="mt-3 w-full rounded-lg bg-red-600 py-2.5 font-bold text-white hover:bg-red-700">
                                ✖ {{ __('Cancel order') }}
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- COOKING --}}
        <div class="rounded-xl border-t-4 border-blue-500 bg-white/60 p-3">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-blue-800">
                {{ __('Cooking') }}
                <span class="rounded-full bg-blue-200 px-2 py-0.5 text-xs text-blue-900">{{ $this->cookingOrders->count() }}</span>
            </h2>
            <div class="space-y-3">
                @foreach ($this->cookingOrders as $order)
                    <div wire:key="order-{{ $order->id }}" class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                        <x-kitchen.ticket-header :order="$order" />
                        <x-kitchen.item-list :order="$order" />
                        <button type="button" wire:click="markReady({{ $order->id }})"
                                class="mt-3 w-full rounded-lg bg-green-600 py-2.5 font-bold text-white hover:bg-green-700">
                            ✔ {{ __('Ready') }}
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- READY --}}
        <div class="rounded-xl border-t-4 border-green-500 bg-white/60 p-3">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-green-800">
                {{ __('Ready') }}
                <span class="rounded-full bg-green-200 px-2 py-0.5 text-xs text-green-900">{{ $this->readyOrders->count() }}</span>
            </h2>
            <div class="space-y-3">
                @foreach ($this->readyOrders as $order)
                    <div wire:key="order-{{ $order->id }}" class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                        <x-kitchen.ticket-header :order="$order" />
                        <x-kitchen.item-list :order="$order" />
                        <button type="button" wire:click="markServed({{ $order->id }})"
                                class="mt-3 w-full rounded-lg bg-gray-800 py-2.5 font-bold text-white hover:bg-gray-900">
                            🍽 {{ __('Served') }}
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Availability panel --}}
    @if ($availabilityOpen)
        <div class="fixed inset-0 z-40 bg-black/40" wire:click="$set('availabilityOpen', false)"></div>
        <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-md overflow-y-auto bg-white p-4 shadow-xl">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900">{{ __('Availability') }}</h2>
                <button type="button" wire:click="$set('availabilityOpen', false)" class="text-2xl leading-none text-gray-500">×</button>
            </div>
            <p class="mb-3 text-sm text-gray-500">{{ __('Tap an item to mark it sold out or available again.') }}</p>

            @foreach ($this->menuByCategory as $category)
                <h3 class="mt-4 mb-1 text-xs font-bold uppercase tracking-wide text-gray-500">{{ $category->name_lo }} · {{ $category->name_en }}</h3>
                <ul class="divide-y">
                    @foreach ($category->menuItems as $item)
                        <li wire:key="avail-{{ $item->id }}">
                            <button type="button" wire:click="toggleAvailability({{ $item->id }})" class="flex w-full items-center justify-between py-2 text-left">
                                <span class="{{ $item->is_available ? 'text-gray-900' : 'text-gray-400 line-through' }}">
                                    {{ $item->name_lo }} <span class="text-sm text-gray-500">{{ $item->name_en }}</span>
                                </span>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $item->is_available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $item->is_available ? __('Available') : __('Sold out') }}
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </aside>
    @endif
</div>

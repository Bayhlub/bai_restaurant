<?php

use App\Livewire\Actions\PlaceOrder;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Table;
use App\Models\TableSession;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.customer')] class extends Component {
    public Table $table;

    public ?int $activeCategoryId = null;

    /** @var array<int, array{qty: int, note: string}> keyed by menu item id */
    public array $cart = [];

    public bool $cartOpen = false;

    public string $orderNote = '';

    public ?string $flash = null;

    public ?string $flashError = null;

    public function mount(string $token): void
    {
        $this->table = Table::where('token', $token)->where('is_active', true)->firstOrFail();
        $this->activeCategoryId = $this->categories->first()?->id;
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::where('is_active', true)
            ->whereHas('menuItems', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }

    #[Computed]
    public function items(): Collection
    {
        if (! $this->activeCategoryId) {
            return collect();
        }

        return MenuItem::where('category_id', $this->activeCategoryId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }

    #[Computed]
    public function session(): ?TableSession
    {
        return $this->table->openSession()->with('orders.items')->first();
    }

    #[Computed]
    public function cartLines(): Collection
    {
        if ($this->cart === []) {
            return collect();
        }

        $items = MenuItem::whereIn('id', array_keys($this->cart))->get()->keyBy('id');

        return collect($this->cart)
            ->map(fn (array $line, int $id) => $items->has($id) ? [
                'id' => $id,
                'item' => $items[$id],
                'qty' => $line['qty'],
                'note' => $line['note'],
                'total' => $items[$id]->price * $line['qty'],
            ] : null)
            ->filter()
            ->values();
    }

    #[Computed]
    public function cartCount(): int
    {
        return (int) collect($this->cart)->sum('qty');
    }

    #[Computed]
    public function cartTotal(): float
    {
        return (float) $this->cartLines->sum('total');
    }

    public function selectCategory(int $categoryId): void
    {
        $this->activeCategoryId = $categoryId;
    }

    public function add(int $menuItemId): void
    {
        $item = MenuItem::orderable()->find($menuItemId);

        if (! $item) {
            $this->flashError = __('Sorry, this item is sold out.');

            return;
        }

        $this->flashError = null;
        $this->cart[$menuItemId] ??= ['qty' => 0, 'note' => ''];
        $this->cart[$menuItemId]['qty'] = min($this->cart[$menuItemId]['qty'] + 1, 99);
    }

    public function decrement(int $menuItemId): void
    {
        if (! isset($this->cart[$menuItemId])) {
            return;
        }

        $this->cart[$menuItemId]['qty']--;

        if ($this->cart[$menuItemId]['qty'] <= 0) {
            unset($this->cart[$menuItemId]);
        }

        if ($this->cart === []) {
            $this->cartOpen = false;
        }
    }

    public function remove(int $menuItemId): void
    {
        unset($this->cart[$menuItemId]);

        if ($this->cart === []) {
            $this->cartOpen = false;
        }
    }

    public function toggleCart(): void
    {
        $this->cartOpen = ! $this->cartOpen && $this->cart !== [];
    }

    public function callStaff(): void
    {
        $this->table->requestService();
        $this->flash = __('Staff have been called and will be with you shortly.');
    }

    public function requestBill(): void
    {
        $session = $this->session;

        if (! $session || $session->orders->isEmpty()) {
            return;
        }

        $session->requestBill();
        unset($this->session);
        $this->flash = __('Bill requested. The cashier will bring your invoice.');
    }

    public function submit(PlaceOrder $placeOrder): void
    {
        $this->flash = $this->flashError = null;

        if ($this->cart === []) {
            $this->flashError = __('Your cart is empty.');

            return;
        }

        $this->validate([
            'orderNote' => 'nullable|string|max:300',
            'cart.*.note' => 'nullable|string|max:150',
        ]);

        try {
            $order = $placeOrder($this->table, $this->cart, $this->orderNote);
        } catch (InvalidArgumentException) {
            $this->flashError = __('Sorry, everything in your cart is sold out.');
            $this->reset('cart', 'cartOpen');

            return;
        }

        $this->reset('cart', 'cartOpen', 'orderNote');
        unset($this->session);

        $this->flash = __('Order #:number sent to the kitchen!', ['number' => $order->daily_number]);

        if ($placeOrder->droppedItemNames !== []) {
            $this->flashError = __('Not available: :items', ['items' => implode(', ', $placeOrder->droppedItemNames)]);
        }

        $this->dispatch('order-placed');
    }
}; ?>

<div class="min-h-screen pb-28" x-data x-on:order-placed.window="window.scrollTo({ top: document.getElementById('orders').offsetTop - 120, behavior: 'smooth' })">
    {{-- Header --}}
    <header class="sticky top-0 z-20 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-2 px-4 py-2">
            <div class="flex min-w-0 items-center gap-2">
                <x-app-logo size="h-9 w-9" class="shrink-0 text-emerald-800" />
                <div class="min-w-0">
                    <div class="truncate text-xs font-semibold text-gray-500">{{ app()->getLocale() === 'lo' ? config('restaurant.name_lo') : config('app.name') }}</div>
                    <div class="text-lg font-bold leading-tight text-gray-900">{{ __('Table') }} {{ $table->number }}</div>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
                <button type="button" wire:click="callStaff" wire:loading.attr="disabled"
                        class="whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-medium {{ $table->needsService() ? 'border-yellow-400 bg-yellow-100 text-yellow-900' : 'border-gray-300 bg-white text-gray-700' }}">
                    🔔 {{ $table->needsService() ? __('Called ✓') : __('Call staff') }}
                </button>
                <x-language-switcher class="text-xs" />
            </div>
        </div>

        {{-- Category tabs --}}
        <nav class="flex gap-2 overflow-x-auto px-4 pb-3 no-scrollbar">
            @foreach ($this->categories as $category)
                <button type="button" wire:key="tab-{{ $category->id }}" wire:click="selectCategory({{ $category->id }})"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition {{ $category->id === $activeCategoryId ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700' }}">
                    {{ $category->name }}
                </button>
            @endforeach
        </nav>
    </header>

    {{-- Flash messages --}}
    @if ($flash)
        <div class="mx-4 mt-3 rounded-lg bg-green-100 px-4 py-3 text-sm text-green-800">{{ $flash }}</div>
    @endif
    @if ($flashError)
        <div class="mx-4 mt-3 rounded-lg bg-red-100 px-4 py-3 text-sm text-red-800">{{ $flashError }}</div>
    @endif

    {{-- Menu items --}}
    <section class="px-4 py-3 space-y-3">
        @forelse ($this->items as $item)
            <div wire:key="menu-{{ $item->id }}" class="flex gap-3 rounded-xl bg-white p-3 shadow-sm {{ $item->is_available ? '' : 'opacity-60' }}">
                @if ($item->image_path)
                    <img src="{{ $item->imageUrl() }}" alt="" class="h-20 w-20 shrink-0 rounded-lg object-cover">
                @else
                    <div class="h-20 w-20 shrink-0 rounded-lg bg-gray-100"></div>
                @endif

                <div class="flex min-w-0 flex-1 flex-col">
                    <div class="font-semibold text-gray-900">{{ $item->name }}</div>
                    @if ($item->description)
                        <div class="line-clamp-2 text-xs text-gray-500">{{ $item->description }}</div>
                    @endif
                    <div class="mt-auto flex items-center justify-between pt-1">
                        <span class="font-semibold text-gray-800">{{ number_format($item->price) }} ₭</span>

                        @if (! $item->is_available)
                            <span class="rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-gray-600">{{ __('Sold out') }}</span>
                        @elseif (isset($cart[$item->id]))
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="decrement({{ $item->id }})" class="h-8 w-8 rounded-full bg-gray-200 text-lg font-bold leading-none text-gray-800">−</button>
                                <span class="w-5 text-center font-semibold">{{ $cart[$item->id]['qty'] }}</span>
                                <button type="button" wire:click="add({{ $item->id }})" class="h-8 w-8 rounded-full bg-gray-900 text-lg font-bold leading-none text-white">+</button>
                            </div>
                        @else
                            <button type="button" wire:click="add({{ $item->id }})" class="rounded-full bg-gray-900 px-4 py-1.5 text-sm font-medium text-white">+ {{ __('Add') }}</button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="py-8 text-center text-sm text-gray-500">{{ __('No items in this category.') }}</p>
        @endforelse
    </section>

    {{-- Orders so far (polled so kitchen decisions show up on the phone) --}}
    <section id="orders" class="px-4 py-3" wire:poll.4s>
        <h2 class="mb-2 text-lg font-bold text-gray-900">{{ __('Your orders') }}</h2>

        @if ($this->session && $this->session->orders->isNotEmpty())
            <div class="space-y-3">
                @foreach ($this->session->orders->sortByDesc('created_at') as $order)
                    <div wire:key="order-{{ $order->id }}" class="rounded-xl bg-white p-3 shadow-sm">
                        <div class="mb-2 flex items-center justify-between">
                            <div class="text-sm text-gray-500">#{{ $order->daily_number }} · {{ $order->created_at->format('H:i') }}</div>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->status->badgeClasses() }}">
                                {{ $order->status->label() }}
                            </span>
                        </div>

                        <ul class="divide-y text-sm">
                            @foreach ($order->items as $line)
                                <li wire:key="line-{{ $line->id }}" class="flex items-start justify-between gap-2 py-1.5 {{ $line->isRejected() ? 'text-gray-400' : 'text-gray-800' }}">
                                    <div class="min-w-0">
                                        <span class="{{ $line->isRejected() ? 'line-through' : '' }}">{{ $line->qty }} × {{ $line->name }}</span>
                                        @if ($line->note)
                                            <div class="text-xs text-gray-500">{{ $line->note }}</div>
                                        @endif
                                        @if ($line->isRejected() && $line->rejection_reason)
                                            <div class="text-xs text-red-600">{{ $line->rejection_reason }}</div>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="{{ $line->isRejected() ? 'line-through' : '' }}">{{ number_format($line->lineTotal()) }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $line->status->badgeClasses() }}">{{ $line->status->label() }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        @if ($order->note)
                            <div class="mt-2 text-xs text-gray-500">{{ __('Note') }}: {{ $order->note }}</div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-3 flex items-center justify-between rounded-xl bg-gray-900 px-4 py-3 text-white">
                <span class="font-medium">{{ __('Total so far') }}</span>
                <span class="text-lg font-bold">{{ number_format($this->session->subtotal()) }} ₭</span>
            </div>
            @if ($this->session->billRequested())
                <div class="mt-3 rounded-xl bg-green-100 px-4 py-3 text-center text-sm font-medium text-green-800">
                    💵 {{ __('Bill requested. The cashier will bring your invoice.') }}
                </div>
            @else
                <button type="button" wire:click="requestBill" wire:loading.attr="disabled"
                        class="mt-3 w-full rounded-xl border-2 border-gray-900 bg-white py-3 text-lg font-bold text-gray-900">
                    💵 {{ __('Request bill') }}
                </button>
                <p class="mt-2 text-center text-xs text-gray-500">{{ __('Tap when you are done and the cashier will come with your invoice.') }}</p>
            @endif
        @else
            <p class="py-6 text-center text-sm text-gray-500">{{ __('Nothing ordered yet. Pick something from the menu above!') }}</p>
        @endif
    </section>

    {{-- Sticky cart bar --}}
    @if ($this->cartCount > 0)
        <div class="fixed inset-x-0 bottom-0 z-30 border-t bg-white p-3 shadow-[0_-4px_12px_rgba(0,0,0,0.08)]">
            <button type="button" wire:click="toggleCart" class="flex w-full items-center justify-between rounded-xl bg-gray-900 px-4 py-3 text-white">
                <span class="flex items-center gap-2">
                    <span class="rounded-full bg-white/20 px-2 py-0.5 text-sm font-semibold">{{ $this->cartCount }}</span>
                    <span class="font-medium">{{ $cartOpen ? __('Hide cart') : __('View cart') }}</span>
                </span>
                <span class="text-lg font-bold">{{ number_format($this->cartTotal) }} ₭</span>
            </button>
        </div>
    @endif

    {{-- Cart sheet --}}
    @if ($cartOpen && $this->cartCount > 0)
        <div class="fixed inset-0 z-20 bg-black/40" wire:click="toggleCart"></div>
        <div class="fixed inset-x-0 bottom-[76px] z-30 max-h-[70vh] overflow-y-auto rounded-t-2xl bg-white p-4 shadow-xl">
            <h3 class="mb-3 text-lg font-bold text-gray-900">{{ __('Your cart') }}</h3>

            <ul class="divide-y">
                @foreach ($this->cartLines as $line)
                    <li wire:key="cart-{{ $line['id'] }}" class="py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-gray-900">{{ $line['item']->name }}</div>
                                <div class="text-sm text-gray-500">{{ number_format($line['item']->price) }} ₭</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="decrement({{ $line['id'] }})" class="h-8 w-8 rounded-full bg-gray-200 text-lg font-bold leading-none">−</button>
                                <span class="w-5 text-center font-semibold">{{ $line['qty'] }}</span>
                                <button type="button" wire:click="add({{ $line['id'] }})" class="h-8 w-8 rounded-full bg-gray-900 text-lg font-bold leading-none text-white">+</button>
                            </div>
                            <div class="w-20 text-right font-semibold">{{ number_format($line['total']) }}</div>
                        </div>
                        <input type="text" wire:model.blur="cart.{{ $line['id'] }}.note" placeholder="{{ __('Note (e.g. not spicy)') }}"
                               class="mt-2 w-full rounded-md border-gray-300 text-sm">
                        @error('cart.'.$line['id'].'.note') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </li>
                @endforeach
            </ul>

            <textarea wire:model.blur="orderNote" rows="2" placeholder="{{ __('Note for the kitchen') }}" class="mt-3 w-full rounded-md border-gray-300 text-sm"></textarea>
            @error('orderNote') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

            <button type="button" wire:click="submit" wire:loading.attr="disabled"
                class="mt-3 w-full rounded-xl bg-green-600 py-3 text-lg font-bold text-white disabled:opacity-50">
                <span wire:loading.remove wire:target="submit">{{ __('Send to kitchen') }} · {{ number_format($this->cartTotal) }} ₭</span>
                <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
            </button>
        </div>
    @endif
</div>

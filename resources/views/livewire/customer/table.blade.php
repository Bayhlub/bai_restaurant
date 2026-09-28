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
    <header class="pattern-lao sticky top-0 z-20 bg-forest-700 shadow-lg">
        <div class="flex items-center justify-between gap-2 px-4 pt-3">
            <div class="flex min-w-0 items-center gap-2.5">
                <x-app-logo size="h-10 w-10" class="shrink-0 text-cream-100" />
                <div class="min-w-0">
                    <div class="truncate text-xs font-medium tracking-wide text-cream-200/80">
                        {{ app()->getLocale() === 'lo' ? config('restaurant.name_lo') : config('app.name') }}
                    </div>
                    <div class="font-display text-xl font-bold leading-tight text-cream-50">
                        {{ __('Table') }} {{ $table->number }}
                    </div>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1.5">
                <button type="button" wire:click="callStaff" wire:loading.attr="disabled"
                        class="whitespace-nowrap rounded-full border px-2.5 py-1 text-xs font-semibold transition {{ $table->needsService() ? 'border-terracotta-400 bg-terracotta-400 text-forest-900' : 'border-cream-200/40 text-cream-100 hover:bg-white/10' }}">
                    🔔 {{ $table->needsService() ? __('Called ✓') : __('Call staff') }}
                </button>
                <x-language-switcher :on-dark="true" class="text-xs" />
            </div>
        </div>

        {{-- Category tabs --}}
        <nav class="no-scrollbar flex gap-2 overflow-x-auto px-4 pb-3 pt-3">
            @foreach ($this->categories as $category)
                <button type="button" wire:key="tab-{{ $category->id }}" wire:click="selectCategory({{ $category->id }})"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition {{ $category->id === $activeCategoryId ? 'bg-cream-50 text-forest-800 shadow-sm' : 'bg-white/10 text-cream-100 hover:bg-white/20' }}">
                    {{ $category->name }}
                </button>
            @endforeach
        </nav>
    </header>

    {{-- Flash messages --}}
    @if ($flash)
        <div class="mx-4 mt-4 rounded-xl border border-forest-100 bg-forest-50 px-4 py-3 text-sm font-medium text-forest-800">{{ $flash }}</div>
    @endif
    @if ($flashError)
        <div class="mx-4 mt-4 rounded-xl border border-terracotta-100 bg-terracotta-50 px-4 py-3 text-sm font-medium text-terracotta-700">{{ $flashError }}</div>
    @endif

    {{-- Menu items --}}
    <section class="space-y-3 px-4 py-4">
        @forelse ($this->items as $item)
            <div wire:key="menu-{{ $item->id }}"
                 class="flex gap-3 overflow-hidden rounded-2xl bg-white p-3 shadow-[0_2px_12px_rgba(22,58,42,0.07)] ring-1 ring-cream-200 {{ $item->is_available ? '' : 'opacity-60' }}">
                @if ($item->image_path)
                    <img src="{{ $item->imageUrl() }}" alt="" class="h-24 w-24 shrink-0 rounded-xl object-cover">
                @else
                    <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-xl bg-cream-100 text-2xl text-cream-300">🍽</div>
                @endif

                <div class="flex min-w-0 flex-1 flex-col">
                    <div class="font-display text-lg font-bold leading-snug text-forest-900">{{ $item->name }}</div>
                    @if ($item->description)
                        <div class="line-clamp-2 text-xs leading-relaxed text-forest-800/60">{{ $item->description }}</div>
                    @endif
                    <div class="mt-auto flex items-center justify-between pt-2">
                        <span class="font-display text-lg font-bold text-terracotta-600">{{ number_format($item->price) }} ₭</span>

                        @if (! $item->is_available)
                            <span class="rounded-full bg-cream-200 px-3 py-1 text-xs font-semibold text-forest-800/60">{{ __('Sold out') }}</span>
                        @elseif (isset($cart[$item->id]))
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="decrement({{ $item->id }})" class="h-9 w-9 rounded-full bg-cream-200 text-xl font-bold leading-none text-forest-800 transition active:scale-95">−</button>
                                <span class="w-5 text-center font-bold text-forest-900">{{ $cart[$item->id]['qty'] }}</span>
                                <button type="button" wire:click="add({{ $item->id }})" class="h-9 w-9 rounded-full bg-forest-700 text-xl font-bold leading-none text-cream-50 shadow-sm transition active:scale-95">+</button>
                            </div>
                        @else
                            <button type="button" wire:click="add({{ $item->id }})"
                                    class="rounded-full bg-forest-700 px-4 py-2 text-sm font-semibold text-cream-50 shadow-sm transition hover:bg-forest-600 active:scale-95">
                                + {{ __('Add') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="py-10 text-center text-sm text-forest-800/50">{{ __('No items in this category.') }}</p>
        @endforelse
    </section>

    {{-- Orders so far (polled so kitchen decisions show up on the phone) --}}
    <section id="orders" class="px-4 py-3" wire:poll.4s>
        <div class="mb-3 flex items-center gap-3">
            <h2 class="font-display text-xl font-bold text-forest-900">{{ __('Your orders') }}</h2>
            <span class="h-px flex-1 bg-cream-300"></span>
        </div>

        @if ($this->session && $this->session->orders->isNotEmpty())
            <div class="space-y-3">
                @foreach ($this->session->orders->sortByDesc('created_at') as $order)
                    <div wire:key="order-{{ $order->id }}" class="rounded-2xl bg-white p-3 shadow-[0_2px_12px_rgba(22,58,42,0.07)] ring-1 ring-cream-200">
                        <div class="mb-2 flex items-center justify-between">
                            <div class="text-sm font-medium text-forest-800/60">#{{ $order->daily_number }} · {{ $order->created_at->format('H:i') }}</div>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->status->badgeClasses() }}">
                                {{ $order->status->label() }}
                            </span>
                        </div>

                        <ul class="divide-y divide-cream-200 text-sm">
                            @foreach ($order->items as $line)
                                <li wire:key="line-{{ $line->id }}" class="flex items-start justify-between gap-2 py-2 {{ $line->isRejected() ? 'text-forest-800/40' : 'text-forest-900' }}">
                                    <div class="min-w-0">
                                        <span class="font-medium {{ $line->isRejected() ? 'line-through' : '' }}">{{ $line->qty }} × {{ $line->name }}</span>
                                        @if ($line->note)
                                            <div class="text-xs text-forest-800/50">{{ $line->note }}</div>
                                        @endif
                                        @if ($line->isRejected() && $line->rejection_reason)
                                            <div class="text-xs font-medium text-terracotta-600">{{ $line->rejection_reason }}</div>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <span class="font-medium {{ $line->isRejected() ? 'line-through' : '' }}">{{ number_format($line->lineTotal()) }}</span>
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $line->status->badgeClasses() }}">{{ $line->status->label() }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        @if ($order->note)
                            <div class="mt-2 text-xs text-forest-800/50">{{ __('Note') }}: {{ $order->note }}</div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="pattern-lao mt-4 flex items-center justify-between rounded-2xl bg-forest-800 px-5 py-4 text-cream-50 shadow-md">
                <span class="text-sm font-medium uppercase tracking-wide text-cream-200/80">{{ __('Total so far') }}</span>
                <span class="font-display text-2xl font-bold">{{ number_format($this->session->subtotal()) }} ₭</span>
            </div>

            @if ($this->session->billRequested())
                <div class="mt-3 rounded-2xl border border-forest-100 bg-forest-50 px-4 py-3 text-center text-sm font-semibold text-forest-800">
                    💵 {{ __('Bill requested. The cashier will bring your invoice.') }}
                </div>
            @else
                <button type="button" wire:click="requestBill" wire:loading.attr="disabled"
                        class="mt-3 w-full rounded-2xl border-2 border-terracotta-500 bg-white py-3 font-display text-lg font-bold text-terracotta-600 transition hover:bg-terracotta-50 active:scale-[0.99]">
                    💵 {{ __('Request bill') }}
                </button>
                <p class="mt-2 text-center text-xs text-forest-800/50">{{ __('Tap when you are done and the cashier will come with your invoice.') }}</p>
            @endif
        @else
            <div class="rounded-2xl border border-dashed border-cream-300 bg-white/60 py-10 text-center">
                <div class="text-3xl">🍜</div>
                <p class="mt-2 px-6 text-sm text-forest-800/60">{{ __('Nothing ordered yet. Pick something from the menu above!') }}</p>
            </div>
        @endif
    </section>

    {{-- Sticky cart bar --}}
    @if ($this->cartCount > 0)
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-cream-200 bg-cream-50/95 p-3 shadow-[0_-4px_16px_rgba(22,58,42,0.10)] backdrop-blur">
            <button type="button" wire:click="toggleCart"
                    class="flex w-full items-center justify-between rounded-2xl bg-forest-700 px-5 py-3.5 text-cream-50 shadow-md transition active:scale-[0.99]">
                <span class="flex items-center gap-2.5">
                    <span class="rounded-full bg-terracotta-500 px-2.5 py-0.5 text-sm font-bold">{{ $this->cartCount }}</span>
                    <span class="font-semibold">{{ $cartOpen ? __('Hide cart') : __('View cart') }}</span>
                </span>
                <span class="font-display text-xl font-bold">{{ number_format($this->cartTotal) }} ₭</span>
            </button>
        </div>
    @endif

    {{-- Cart sheet --}}
    @if ($cartOpen && $this->cartCount > 0)
        <div class="fixed inset-0 z-20 bg-forest-900/50" wire:click="toggleCart"></div>
        <div class="fixed inset-x-0 bottom-[84px] z-30 max-h-[70vh] overflow-y-auto rounded-t-3xl bg-cream-50 p-5 shadow-2xl">
            <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-cream-300"></div>
            <h3 class="mb-3 font-display text-xl font-bold text-forest-900">{{ __('Your cart') }}</h3>

            <ul class="divide-y divide-cream-200">
                @foreach ($this->cartLines as $line)
                    <li wire:key="cart-{{ $line['id'] }}" class="py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-forest-900">{{ $line['item']->name }}</div>
                                <div class="text-sm text-terracotta-600">{{ number_format($line['item']->price) }} ₭</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="decrement({{ $line['id'] }})" class="h-9 w-9 rounded-full bg-cream-200 text-xl font-bold leading-none text-forest-800 transition active:scale-95">−</button>
                                <span class="w-5 text-center font-bold text-forest-900">{{ $line['qty'] }}</span>
                                <button type="button" wire:click="add({{ $line['id'] }})" class="h-9 w-9 rounded-full bg-forest-700 text-xl font-bold leading-none text-cream-50 transition active:scale-95">+</button>
                            </div>
                            <div class="w-20 text-right font-display font-bold text-forest-900">{{ number_format($line['total']) }}</div>
                        </div>
                        <input type="text" wire:model.blur="cart.{{ $line['id'] }}.note" placeholder="{{ __('Note (e.g. not spicy)') }}"
                               class="mt-2 w-full rounded-lg border-cream-300 bg-white text-sm placeholder:text-forest-800/40 focus:border-forest-500 focus:ring-forest-500">
                        @error('cart.'.$line['id'].'.note') <p class="text-xs text-terracotta-600">{{ $message }}</p> @enderror
                    </li>
                @endforeach
            </ul>

            <textarea wire:model.blur="orderNote" rows="2" placeholder="{{ __('Note for the kitchen') }}"
                      class="mt-3 w-full rounded-lg border-cream-300 bg-white text-sm placeholder:text-forest-800/40 focus:border-forest-500 focus:ring-forest-500"></textarea>
            @error('orderNote') <p class="text-xs text-terracotta-600">{{ $message }}</p> @enderror

            <button type="button" wire:click="submit" wire:loading.attr="disabled"
                class="mt-3 w-full rounded-2xl bg-terracotta-500 py-3.5 font-display text-lg font-bold text-white shadow-md transition hover:bg-terracotta-600 active:scale-[0.99] disabled:opacity-50">
                <span wire:loading.remove wire:target="submit">{{ __('Send to kitchen') }} · {{ number_format($this->cartTotal) }} ₭</span>
                <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
            </button>
        </div>
    @endif
</div>

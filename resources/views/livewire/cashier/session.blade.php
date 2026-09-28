<?php

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Livewire\Actions\CloseBill;
use App\Models\TableSession;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public TableSession $session;

    public string $discount = '0';

    public string $cashReceived = '';

    public ?string $error = null;

    /** Quick cash buttons, in LAK. */
    public const QUICK_CASH = [20000, 50000, 100000, 200000, 500000];

    public function mount(TableSession $session): void
    {
        $this->session = $session->load('table', 'orders.items', 'invoice');
    }

    #[Computed]
    public function subtotal(): float
    {
        return $this->session->subtotal();
    }

    #[Computed]
    public function total(): float
    {
        return max(0, $this->subtotal - (float) $this->discount);
    }

    #[Computed]
    public function change(): ?float
    {
        if ($this->cashReceived === '' || ! is_numeric($this->cashReceived)) {
            return null;
        }

        return (float) $this->cashReceived - $this->total;
    }

    #[Computed]
    public function unfinishedOrders(): int
    {
        return $this->session->orders
            ->whereIn('status', [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Cooking, OrderStatus::Ready])
            ->count();
    }

    public function refresh(): void
    {
        $this->session->refresh()->load('orders.items');
        unset($this->subtotal, $this->total, $this->change, $this->unfinishedOrders);
    }

    public function setCash(int $amount): void
    {
        $this->cashReceived = (string) $amount;
    }

    public function exactCash(): void
    {
        $this->cashReceived = (string) (int) $this->total;
    }

    public function closeBill(CloseBill $closeBill): void
    {
        $this->error = null;

        $this->validate([
            'discount' => 'required|numeric|min:0',
            'cashReceived' => 'required|numeric|min:0',
        ], attributes: [
            'discount' => __('Discount'),
            'cashReceived' => __('Cash received'),
        ]);

        try {
            $invoice = $closeBill($this->session, auth()->user(), (float) $this->discount, (float) $this->cashReceived);
        } catch (InvalidArgumentException $e) {
            $this->error = __($e->getMessage());

            return;
        }

        $this->redirect(route('invoice.print', $invoice));
    }
}; ?>

<div wire:poll.5s="refresh">
    <x-page-header :title="__('Table').' '.$session->table->number"
                   :subtitle="__('Opened').' '.$session->opened_at->format('H:i').' · '.__(':count orders', ['count' => $session->orders->count()])">
        <x-slot name="actions">
            <x-outline-button href="{{ route('cashier') }}" wire:navigate>← {{ __('All tables') }}</x-outline-button>
            @if ($session->status === SessionStatus::Paid && $session->invoice)
                <x-accent-button href="{{ route('invoice.print', $session->invoice) }}" target="_blank">
                    🖨 {{ __('Print invoice') }} {{ $session->invoice->number }}
                </x-accent-button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            {{-- Orders --}}
            <div class="lg:col-span-3 space-y-3">
                @foreach ($session->orders as $order)
                    <div wire:key="order-{{ $order->id }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="text-gray-500">#{{ $order->daily_number }} · {{ $order->created_at->format('H:i') }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
                        </div>
                        <table class="w-full text-sm">
                            <tbody class="divide-y">
                                @foreach ($order->items as $item)
                                    <tr wire:key="item-{{ $item->id }}" class="{{ $item->isRejected() ? 'text-gray-400 line-through' : 'text-gray-800' }}">
                                        <td class="py-1.5 w-10 font-semibold">{{ $item->qty }}×</td>
                                        <td class="py-1.5">
                                            {{ $item->name_lo }} <span class="text-gray-500">{{ $item->name_en }}</span>
                                            @if ($item->isRejected())
                                                <span class="ms-1 text-xs text-red-600 no-underline">({{ $item->rejection_reason }})</span>
                                            @endif
                                        </td>
                                        <td class="py-1.5 text-right text-gray-500">{{ number_format($item->unit_price) }}</td>
                                        <td class="py-1.5 text-right font-medium w-28">{{ number_format($item->lineTotal()) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>

            {{-- Bill --}}
            <div class="lg:col-span-2">
                <div class="sticky top-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-lg font-bold text-gray-900">{{ __('Bill') }}</h2>

                    @if ($session->status === SessionStatus::Paid)
                        <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">
                            {{ __('Paid') }} {{ $session->invoice?->paid_at?->format('d/m/Y H:i') }}<br>
                            <span class="font-bold">{{ number_format($session->invoice?->total ?? 0) }} ₭</span>
                        </div>
                    @else
                        @if ($session->billRequested())
                            <div class="mb-3 rounded-md bg-blue-50 px-3 py-2 text-sm font-medium text-blue-800">
                                💵 {{ __('Customer requested the bill at :time', ['time' => $session->bill_requested_at->format('H:i')]) }}
                            </div>
                        @endif

                        @if ($this->unfinishedOrders > 0)
                            <div class="mb-3 rounded-md bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                                ⚠ {{ trans_choice('{1} :count order is still in the kitchen.|[2,*] :count orders are still in the kitchen.', $this->unfinishedOrders, ['count' => $this->unfinishedOrders]) }}
                            </div>
                        @endif

                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600">{{ __('Subtotal') }}</dt>
                                <dd class="font-medium">{{ number_format($this->subtotal) }} ₭</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-gray-600">{{ __('Discount') }}</dt>
                                <dd><x-text-input type="number" min="0" step="1000" wire:model.live="discount" class="w-32 text-right" /></dd>
                            </div>
                            <x-input-error :messages="$errors->get('discount')" />
                            <div class="flex justify-between border-t pt-2 text-lg">
                                <dt class="font-bold text-gray-900">{{ __('Total') }}</dt>
                                <dd class="font-black text-gray-900">{{ number_format($this->total) }} ₭</dd>
                            </div>
                        </dl>

                        <div class="mt-4">
                            <x-input-label for="cashReceived" :value="__('Cash received')" />
                            <x-text-input id="cashReceived" type="number" min="0" step="1000" wire:model.live="cashReceived" class="mt-1 block w-full text-right text-lg" />
                            <x-input-error :messages="$errors->get('cashReceived')" class="mt-1" />

                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <button type="button" wire:click="exactCash" class="rounded-md bg-emerald-700 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-600">{{ __('Exact') }}</button>
                                @foreach ($this::QUICK_CASH as $amount)
                                    <button type="button" wire:click="setCash({{ $amount }})" class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200">{{ number_format($amount / 1000) }}k</button>
                                @endforeach
                            </div>
                        </div>

                        @if ($this->change !== null)
                            <div class="mt-4 flex items-center justify-between rounded-md p-3 {{ $this->change < 0 ? 'bg-red-50 text-red-800' : 'bg-green-50 text-green-800' }}">
                                <span class="font-medium">{{ $this->change < 0 ? __('Still owed') : __('Change') }}</span>
                                <span class="text-xl font-black">{{ number_format(abs($this->change)) }} ₭</span>
                            </div>
                        @endif

                        @if ($error)
                            <p class="mt-3 text-sm text-red-600">{{ $error }}</p>
                        @endif

                        <button type="button" wire:click="closeBill" wire:loading.attr="disabled"
                                wire:confirm="{{ __('Close this bill and print the invoice?') }}"
                                @disabled($this->subtotal <= 0 || $this->change === null || $this->change < 0)
                                class="mt-4 w-full rounded-lg bg-emerald-700 py-3 text-lg font-bold text-white shadow-sm transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-40">
                            💵 {{ __('Pay & print invoice') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

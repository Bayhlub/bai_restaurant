<?php

use App\Enums\OrderItemStatus;
use App\Models\Invoice;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    #[Url]
    public string $preset = 'today';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public const PRESETS = ['today', 'yesterday', 'week', 'month', 'custom'];

    public function mount(): void
    {
        if (! in_array($this->preset, self::PRESETS, true)) {
            $this->preset = 'today';
        }

        if ($this->from === '' || $this->to === '') {
            $this->applyPreset($this->preset);
        }
    }

    public function updatedPreset(string $value): void
    {
        $this->applyPreset($value);
    }

    public function applyPreset(string $preset): void
    {
        $today = CarbonImmutable::today();

        [$from, $to] = match ($preset) {
            'yesterday' => [$today->subDay(), $today->subDay()],
            'week' => [$today->startOfWeek(), $today],
            'month' => [$today->startOfMonth(), $today],
            'custom' => [CarbonImmutable::parse($this->from ?: $today), CarbonImmutable::parse($this->to ?: $today)],
            default => [$today, $today],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
    }

    public function updatedFrom(): void
    {
        $this->preset = 'custom';
    }

    public function updatedTo(): void
    {
        $this->preset = 'custom';
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function range(): array
    {
        $from = CarbonImmutable::parse($this->from)->startOfDay();
        $to = CarbonImmutable::parse($this->to)->endOfDay();

        return $from->lte($to) ? [$from, $to] : [$to->startOfDay(), $from->endOfDay()];
    }

    private function invoices(): Builder
    {
        [$from, $to] = $this->range();

        return Invoice::whereBetween('paid_at', [$from, $to]);
    }

    #[Computed]
    public function summary(): array
    {
        $row = $this->invoices()
            ->selectRaw('COUNT(*) as invoices, COALESCE(SUM(total), 0) as revenue, COALESCE(SUM(discount), 0) as discounts')
            ->first();

        return [
            'invoices' => (int) $row->invoices,
            'revenue' => (float) $row->revenue,
            'discounts' => (float) $row->discounts,
            'average' => $row->invoices > 0 ? $row->revenue / $row->invoices : 0.0,
        ];
    }

    #[Computed]
    public function byDay(): Collection
    {
        return $this->invoices()
            ->selectRaw('DATE(paid_at) as day, COUNT(*) as invoices, SUM(total) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    #[Computed]
    public function topItems(): Collection
    {
        [$from, $to] = $this->range();

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('invoices', 'invoices.table_session_id', '=', 'orders.table_session_id')
            ->where('order_items.status', '!=', OrderItemStatus::Rejected)
            ->whereBetween('invoices.paid_at', [$from, $to])
            ->groupBy('order_items.name_lo', 'order_items.name_en')
            ->selectRaw('order_items.name_lo, order_items.name_en, SUM(order_items.qty) as qty, SUM(order_items.qty * order_items.unit_price) as revenue')
            ->orderByDesc('qty')
            ->limit(15)
            ->get();
    }

    #[Computed]
    public function byCashier(): Collection
    {
        return $this->invoices()
            ->leftJoin('users', 'users.id', '=', 'invoices.cashier_id')
            ->selectRaw('COALESCE(users.name, ?) as cashier, COUNT(*) as invoices, SUM(invoices.total) as revenue', ['—'])
            ->groupBy('cashier')
            ->orderByDesc('revenue')
            ->get();
    }

    #[Computed]
    public function rejectedCount(): int
    {
        [$from, $to] = $this->range();

        return (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.status', OrderItemStatus::Rejected)
            ->whereBetween('orders.created_at', [$from, $to])
            ->sum('order_items.qty');
    }
}; ?>

<div>
    <x-page-header :title="__('Reports')" :subtitle="__('Sales, dishes and cashier totals')">
        <x-slot name="actions">
            <div class="inline-flex overflow-hidden rounded-lg border border-gray-300 text-sm shadow-sm">
                @foreach (['today' => __('Today'), 'yesterday' => __('Yesterday'), 'week' => __('This week'), 'month' => __('This month')] as $key => $label)
                    <button type="button" wire:click="$set('preset', '{{ $key }}')"
                            class="px-3 py-1.5 font-medium transition {{ $preset === $key ? 'bg-slate-800 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex items-center gap-1 text-sm">
                <x-text-input type="date" wire:model.live="from" class="py-1" />
                <span class="text-gray-500">–</span>
                <x-text-input type="date" wire:model.live="to" class="py-1" />
            </div>
        </x-slot>
    </x-page-header>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

        {{-- Summary tiles --}}
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Revenue') }}</div>
                <div class="mt-1 text-2xl font-black text-gray-900">{{ number_format($this->summary['revenue']) }} ₭</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Invoices') }}</div>
                <div class="mt-1 text-2xl font-black text-gray-900">{{ $this->summary['invoices'] }}</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Average bill') }}</div>
                <div class="mt-1 text-2xl font-black text-gray-900">{{ number_format($this->summary['average']) }} ₭</div>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Discounts') }}</div>
                <div class="mt-1 text-2xl font-black text-gray-900">{{ number_format($this->summary['discounts']) }} ₭</div>
                <div class="text-xs text-gray-500">{{ __(':count items rejected', ['count' => $this->rejectedCount]) }}</div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Revenue per day --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 font-semibold text-gray-800">{{ __('Revenue by day') }}</h2>
                @if ($this->byDay->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('No sales in this period.') }}</p>
                @else
                    @php $max = max(1, $this->byDay->max('revenue')); @endphp
                    <div class="space-y-2">
                        @foreach ($this->byDay as $day)
                            <div wire:key="day-{{ $day->day }}" class="flex items-center gap-3 text-sm">
                                <div class="w-24 shrink-0 text-gray-600">{{ \Carbon\Carbon::parse($day->day)->format('D d/m') }}</div>
                                <div class="h-5 flex-1 rounded bg-gray-100">
                                    <div class="h-5 rounded bg-slate-700" style="width: {{ round($day->revenue / $max * 100) }}%"></div>
                                </div>
                                <div class="w-28 shrink-0 text-right font-medium">{{ number_format($day->revenue) }}</div>
                                <div class="w-10 shrink-0 text-right text-xs text-gray-500">{{ $day->invoices }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Top dishes --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 font-semibold text-gray-800">{{ __('Top dishes') }}</h2>
                @if ($this->topItems->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('No sales in this period.') }}</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-500">
                            <tr><th class="py-1">{{ __('Dish') }}</th><th class="py-1 text-right">{{ __('Qty') }}</th><th class="py-1 text-right">{{ __('Revenue') }}</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($this->topItems as $item)
                                <tr wire:key="top-{{ $loop->index }}">
                                    <td class="py-1.5">{{ $item->name_lo }} <span class="text-gray-500">{{ $item->name_en }}</span></td>
                                    <td class="py-1.5 text-right font-medium">{{ $item->qty }}</td>
                                    <td class="py-1.5 text-right">{{ number_format($item->revenue) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Per cashier --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 font-semibold text-gray-800">{{ __('By cashier') }}</h2>
                @if ($this->byCashier->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('No sales in this period.') }}</p>
                @else
                    <table class="w-full text-sm">
                        <tbody class="divide-y">
                            @foreach ($this->byCashier as $row)
                                <tr wire:key="cashier-{{ $loop->index }}">
                                    <td class="py-1.5">{{ $row->cashier }}</td>
                                    <td class="py-1.5 text-right text-gray-500">{{ $row->invoices }} {{ __('invoices') }}</td>
                                    <td class="py-1.5 text-right font-medium">{{ number_format($row->revenue) }} ₭</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

<?php

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Table;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    /** Sessions already asking for the bill on the last render, so only new ones alert. */
    public array $knownBillRequestIds = [];

    /** Tables already calling for staff on the last render. */
    public array $knownServiceCallIds = [];

    public function mount(): void
    {
        $this->knownBillRequestIds = $this->billRequestIds();
        $this->knownServiceCallIds = $this->serviceCallIds();
    }

    /** Called by wire:poll; alerts on bills requested and staff called since the last poll. */
    public function refresh(): void
    {
        unset($this->tables);

        $bills = $this->billRequestIds();
        $calls = $this->serviceCallIds();

        if (($new = array_diff($bills, $this->knownBillRequestIds)) !== []) {
            $this->alert(__('Bill requested'), $this->tableNumbersForSessions($new), 'cashier-bill-requested');
        }

        if (($new = array_diff($calls, $this->knownServiceCallIds)) !== []) {
            $this->alert(__('Staff called'), $this->tableNumbersForTables($new), 'cashier-staff-called');
        }

        $this->knownBillRequestIds = $bills;
        $this->knownServiceCallIds = $calls;
    }

    private function alert(string $title, string $body, string $tag): void
    {
        $this->dispatch('staff-alert', title: $title, body: $body, tag: $tag);
    }

    /** @return array<int, int> */
    private function billRequestIds(): array
    {
        return $this->tables
            ->filter(fn (Table $table) => $table->openSession?->billRequested())
            ->map(fn (Table $table) => $table->openSession->id)
            ->values()
            ->all();
    }

    /** @return array<int, int> */
    private function serviceCallIds(): array
    {
        return $this->tables
            ->filter(fn (Table $table) => $table->needsService())
            ->pluck('id')
            ->all();
    }

    /** @param  array<int, int>  $sessionIds */
    private function tableNumbersForSessions(array $sessionIds): string
    {
        return $this->tables
            ->filter(fn (Table $table) => in_array($table->openSession?->id, $sessionIds, true))
            ->map(fn (Table $table) => __('Table').' '.$table->number)
            ->implode(', ');
    }

    /** @param  array<int, int>  $tableIds */
    private function tableNumbersForTables(array $tableIds): string
    {
        return $this->tables
            ->whereIn('id', $tableIds)
            ->map(fn (Table $table) => __('Table').' '.$table->number)
            ->implode(', ');
    }

    #[Computed]
    public function tables(): Collection
    {
        return Table::where('is_active', true)
            ->with(['openSession.orders.items'])
            ->orderByRaw('CAST(number AS INTEGER)')
            ->orderBy('number')
            ->get();
    }

    public function clearServiceRequest(int $tableId): void
    {
        Table::findOrFail($tableId)->clearServiceRequest();
        unset($this->tables);
    }

    #[Computed]
    public function todaysInvoices(): Collection
    {
        return Invoice::with('session.table')
            ->whereDate('paid_at', today())
            ->latest('paid_at')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function todaysTotal(): float
    {
        return (float) Invoice::whereDate('paid_at', today())->sum('total');
    }

    /**
     * Summary numbers for an occupied table's card.
     *
     * @return array{orders: int, in_kitchen: int, ready: int, total: float}
     */
    public function summary(Table $table): array
    {
        $orders = $table->openSession->orders;

        return [
            'orders' => $orders->count(),
            'in_kitchen' => $orders->whereIn('status', [OrderStatus::Pending, OrderStatus::Accepted, OrderStatus::Cooking])->count(),
            'ready' => $orders->where('status', OrderStatus::Ready)->count(),
            'total' => $orders->flatMap->items
                ->where('status', '!=', OrderItemStatus::Rejected)
                ->sum(fn ($item) => $item->lineTotal()),
        ];
    }
}; ?>

<div wire:poll.5s="refresh">
    <x-page-header :title="__('Cashier')" :subtitle="__('Tables, bills and payments')">
        <x-slot name="actions">
            <x-staff-alerts />
            <div class="rounded-lg border border-emerald-300 bg-white px-4 py-2 text-right shadow-sm">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('Today') }}</div>
                <div class="text-lg font-bold text-emerald-800">{{ number_format($this->todaysTotal) }} ₭</div>
            </div>
        </x-slot>
    </x-page-header>

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

        @php $billRequests = $this->tables->filter(fn ($t) => $t->openSession?->billRequested()); @endphp
        @if ($billRequests->isNotEmpty())
            <div class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-blue-300 bg-blue-100 px-4 py-3 text-sm text-blue-900">
                <span class="font-semibold">💵 {{ __('Bill requested') }}:</span>
                @foreach ($billRequests as $table)
                    <a href="{{ route('cashier.session', $table->openSession) }}" wire:navigate wire:key="bill-{{ $table->id }}"
                       class="rounded-md bg-white px-3 py-1 font-semibold shadow-sm hover:bg-blue-50">
                        {{ __('Table') }} {{ $table->number }} · {{ $table->openSession->bill_requested_at->format('H:i') }} · {{ number_format($this->summary($table)['total']) }} ₭ →
                    </a>
                @endforeach
            </div>
        @endif

        @php $calling = $this->tables->filter->needsService(); @endphp
        @if ($calling->isNotEmpty())
            <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-yellow-300 bg-yellow-100 px-4 py-3 text-sm text-yellow-900">
                <span class="font-semibold">🔔 {{ __('Staff called') }}:</span>
                @foreach ($calling as $table)
                    <button type="button" wire:key="call-{{ $table->id }}" wire:click="clearServiceRequest({{ $table->id }})"
                            class="rounded-md bg-white px-3 py-1 font-semibold shadow-sm hover:bg-yellow-50">
                        {{ __('Table') }} {{ $table->number }} · {{ $table->service_requested_at->format('H:i') }} ✓
                    </button>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($this->tables as $table)
                @if ($table->openSession)
                    @php $summary = $this->summary($table); @endphp
                    <a href="{{ route('cashier.session', $table->openSession) }}" wire:navigate wire:key="table-{{ $table->id }}"
                       class="block rounded-xl border-2 p-4 shadow-sm transition hover:shadow-md {{ $table->openSession->billRequested() ? 'border-blue-500 bg-blue-50' : ($summary['ready'] > 0 ? 'border-green-500 bg-white' : ($summary['in_kitchen'] > 0 ? 'border-yellow-400 bg-white' : 'border-gray-300 bg-white')) }}">
                        <div class="flex items-start justify-between">
                            <span class="text-3xl font-black text-gray-900">{{ $table->number }} @if ($table->openSession->billRequested())<span class="text-xl">💵</span>@endif @if ($table->needsService())<span class="text-xl">🔔</span>@endif</span>
                            <span class="text-xs text-gray-500">{{ $table->openSession->opened_at->format('H:i') }}</span>
                        </div>
                        <div class="mt-2 text-lg font-bold text-gray-800">{{ number_format($summary['total']) }} ₭</div>
                        <div class="mt-1 flex flex-wrap gap-1 text-xs">
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-700">{{ __(':count orders', ['count' => $summary['orders']]) }}</span>
                            @if ($summary['in_kitchen'] > 0)
                                <span class="rounded-full bg-yellow-100 px-2 py-0.5 text-yellow-800">{{ $summary['in_kitchen'] }} {{ __('in kitchen') }}</span>
                            @endif
                            @if ($summary['ready'] > 0)
                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-green-800">{{ $summary['ready'] }} {{ __('ready') }}</span>
                            @endif
                        </div>
                    </a>
                @else
                    <div wire:key="table-{{ $table->id }}" class="rounded-xl border-2 border-dashed p-4 {{ $table->needsService() ? 'border-yellow-400 bg-yellow-50 text-gray-700' : 'border-gray-200 bg-gray-50 text-gray-400' }}">
                        <span class="text-3xl font-black">{{ $table->number }} @if ($table->needsService())<span class="text-xl">🔔</span>@endif</span>
                        <div class="mt-2 text-sm">{{ __('Free') }}</div>
                    </div>
                @endif
            @endforeach
        </div>

        @if ($this->todaysInvoices->isNotEmpty())
            <h2 class="mt-8 mb-2 text-sm font-bold uppercase tracking-wide text-gray-500">{{ __('Recent invoices') }}</h2>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <tbody class="divide-y">
                        @foreach ($this->todaysInvoices as $invoice)
                            <tr wire:key="inv-{{ $invoice->id }}">
                                <td class="hidden px-4 py-2 font-mono text-xs text-gray-600 sm:table-cell">{{ $invoice->number }}</td>
                                <td class="px-4 py-2">{{ __('Table') }} {{ $invoice->session->table->number }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $invoice->paid_at->format('H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold">{{ number_format($invoice->total) }} ₭</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('invoice.print', $invoice) }}" target="_blank" class="text-indigo-600 hover:underline">{{ __('Print') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

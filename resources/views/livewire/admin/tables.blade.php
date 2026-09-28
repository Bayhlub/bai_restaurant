<?php

use App\Models\Table;
use App\Support\QrCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $editingTableId = null;

    public string $number = '';

    public int $seats = 4;

    public bool $isActive = true;

    public ?int $previewTableId = null;

    #[Computed]
    public function tables(): Collection
    {
        return Table::withCount('sessions')
            ->with('openSession')
            ->orderByRaw('CAST(number AS INTEGER)')
            ->orderBy('number')
            ->get();
    }

    /** Called by wire:poll so Free/Occupied keeps up with what customers and the cashier do. */
    public function refresh(): void
    {
        unset($this->tables);
    }

    #[Computed]
    public function previewTable(): ?Table
    {
        return $this->previewTableId ? Table::find($this->previewTableId) : null;
    }

    public function newTable(): void
    {
        $this->resetValidation();
        $this->reset('editingTableId', 'number', 'seats', 'isActive');
        $this->number = (string) ((int) Table::max('number') + 1);
        $this->dispatch('open-modal', 'table-form');
    }

    public function editTable(int $tableId): void
    {
        $table = Table::findOrFail($tableId);

        $this->resetValidation();
        $this->editingTableId = $table->id;
        $this->number = $table->number;
        $this->seats = $table->seats;
        $this->isActive = $table->is_active;
        $this->dispatch('open-modal', 'table-form');
    }

    public function saveTable(): void
    {
        $data = $this->validate([
            'number' => 'required|string|max:20|unique:tables,number,'.$this->editingTableId,
            'seats' => 'required|integer|min:1|max:50',
        ]);

        $attributes = $data + ['is_active' => $this->isActive];

        if ($this->editingTableId) {
            Table::findOrFail($this->editingTableId)->update($attributes);
        } else {
            Table::create($attributes);
        }

        $this->dispatch('close-modal', 'table-form');
    }

    /** Issue a new QR token, e.g. after a printed code has been lost or copied. */
    public function regenerateToken(int $tableId): void
    {
        Table::findOrFail($tableId)->update(['token' => Str::random(32)]);
    }

    public function deleteTable(int $tableId): void
    {
        $table = Table::withCount('sessions')->findOrFail($tableId);

        if ($table->sessions_count > 0) {
            $this->addError('table', __('This table has order history; deactivate it instead of deleting.'));

            return;
        }

        $table->delete();
    }

    public function showQr(int $tableId): void
    {
        $this->previewTableId = $tableId;
        $this->dispatch('open-modal', 'qr-preview');
    }

    public function qrSvg(Table $table): string
    {
        return QrCode::svg($table->customerUrl(), 220);
    }
}; ?>

<div wire:poll.5s="refresh">
    <x-page-header :title="__('Tables')" :subtitle="__('Seats, live status and QR codes')">
        <x-slot name="actions">
            <x-outline-button href="{{ route('admin.tables.qr') }}" target="_blank">🖨 {{ __('Print all QR codes') }}</x-outline-button>
            <x-accent-button type="button" wire:click="newTable">+ {{ __('Add table') }}</x-accent-button>
        </x-slot>
    </x-page-header>

    <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        @error('table')
            <p class="pb-2 text-sm text-red-600">{{ $message }}</p>
        @enderror

        <p class="pb-3 text-sm text-gray-500">
            {{ __('QR codes point to') }} <code class="rounded bg-gray-200 px-1.5 py-0.5 text-gray-800">{{ rtrim(config('restaurant.customer_url'), '/') }}/t/…</code>
            <span class="text-gray-400">({{ __('CUSTOMER_URL in .env') }})</span>
        </p>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-2">{{ __('Table') }}</th>
                        <th class="px-4 py-2">{{ __('Seats') }}</th>
                        <th class="px-4 py-2">{{ __('Status') }}</th>
                        <th class="px-4 py-2">{{ __('QR code') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->tables as $table)
                        <tr wire:key="table-{{ $table->id }}" class="{{ $table->is_active ? '' : 'opacity-50' }}">
                            <td class="px-4 py-3 font-semibold text-gray-800 text-lg">{{ $table->number }}</td>
                            <td class="px-4 py-3">{{ $table->seats }}</td>
                            <td class="px-4 py-3">
                                @if (! $table->is_active)
                                    <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-600">{{ __('Inactive') }}</span>
                                @elseif ($table->openSession)
                                    <span class="px-2 py-1 rounded text-xs bg-yellow-100 text-yellow-800">{{ __('Occupied') }}</span>
                                    <span class="ms-1 text-xs text-gray-500">{{ __('since') }} {{ $table->openSession->opened_at->format('H:i') }}</span>
                                    @if ($table->needsService())<span title="{{ __('Staff called') }}">🔔</span>@endif
                                    @if ($table->openSession->billRequested())<span title="{{ __('Bill requested') }}">💵</span>@endif
                                @else
                                    <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">{{ __('Free') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <button type="button" class="font-medium text-slate-700 hover:underline" wire:click="showQr({{ $table->id }})">{{ __('Show') }}</button>
                                <button type="button" class="ms-2 text-gray-500 hover:underline" wire:click="regenerateToken({{ $table->id }})" wire:confirm="{{ __('The old printed QR code will stop working. Continue?') }}">{{ __('Regenerate') }}</button>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button type="button" class="font-medium text-slate-700 hover:underline" wire:click="editTable({{ $table->id }})">{{ __('Edit') }}</button>
                                <button type="button" class="ms-2 font-medium text-red-600 hover:underline" wire:click="deleteTable({{ $table->id }})" wire:confirm="{{ __('Delete this table?') }}">{{ __('Delete') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-gray-500">{{ __('No tables yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-modal name="table-form" maxWidth="sm">
        <form wire:submit="saveTable" class="p-6 space-y-4">
            <h2 class="text-lg font-medium text-gray-900">{{ $editingTableId ? __('Edit table') : __('New table') }}</h2>

            <div>
                <x-input-label for="number" :value="__('Table number / name')" />
                <x-text-input id="number" wire:model="number" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('number')" class="mt-1" />
            </div>
            <div class="flex gap-4 items-end">
                <div class="w-32">
                    <x-input-label for="seats" :value="__('Seats')" />
                    <x-text-input id="seats" type="number" min="1" wire:model="seats" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('seats')" class="mt-1" />
                </div>
                <label class="flex items-center gap-2 pb-2 text-sm">
                    <input type="checkbox" wire:model="isActive" class="rounded border-gray-300"> {{ __('Active') }}
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </div>
        </form>
    </x-modal>

    <x-modal name="qr-preview" maxWidth="sm">
        @if ($this->previewTable)
            <div class="p-6 text-center space-y-3">
                <h2 class="text-lg font-medium text-gray-900">{{ __('Table') }} {{ $this->previewTable->number }}</h2>
                <div class="inline-block">{!! $this->qrSvg($this->previewTable) !!}</div>
                <p class="text-xs text-gray-500 break-all">{{ $this->previewTable->customerUrl() }}</p>
                <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Close') }}</x-secondary-button>
            </div>
        @endif
    </x-modal>
</div>

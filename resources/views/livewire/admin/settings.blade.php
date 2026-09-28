<?php

use App\Support\Settings;
use Livewire\Volt\Component;

new class extends Component {
    public string $nameEn = '';

    public string $nameLo = '';

    public string $address = '';

    public string $phone = '';

    public string $footerLo = '';

    public string $footerEn = '';

    public bool $saved = false;

    public function mount(): void
    {
        // Show what is in use now, whether that came from the database or .env.
        $this->nameEn = config('app.name');
        $this->nameLo = config('restaurant.name_lo');
        $this->address = config('restaurant.address') ?? '';
        $this->phone = config('restaurant.phone') ?? '';
        $this->footerLo = config('restaurant.receipt_footer_lo') ?? '';
        $this->footerEn = config('restaurant.receipt_footer_en') ?? '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'nameEn' => 'required|string|max:60',
            'nameLo' => 'required|string|max:60',
            'address' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:40',
            'footerLo' => 'nullable|string|max:120',
            'footerEn' => 'nullable|string|max:120',
        ], attributes: [
            'nameEn' => __('Name (English)'),
            'nameLo' => __('Name (Lao)'),
            'address' => __('Address'),
            'phone' => __('Phone'),
        ]);

        Settings::save([
            'name_en' => $data['nameEn'],
            'name_lo' => $data['nameLo'],
            'address' => $data['address'],
            'phone' => $data['phone'],
            'footer_lo' => $data['footerLo'],
            'footer_en' => $data['footerEn'],
        ]);

        Settings::applyToConfig();

        $this->saved = true;
    }
}; ?>

<div>
    <x-page-header :title="__('Restaurant details')" :subtitle="__('Shown in the app, on QR cards and on printed invoices')" />

    <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
        @if ($saved)
            <div class="mb-4 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
                {{ __('Saved. The new details appear everywhere immediately.') }}
            </div>
        @endif

        <form wire:submit="save" class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-semibold text-gray-800">{{ __('Name') }}</h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="nameLo" :value="__('Name (Lao)')" />
                        <x-text-input id="nameLo" wire:model="nameLo" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nameLo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="nameEn" :value="__('Name (English)')" />
                        <x-text-input id="nameEn" wire:model="nameEn" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nameEn')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 font-semibold text-gray-800">{{ __('Contact') }}</h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="address" :value="__('Address')" />
                        <x-text-input id="address" wire:model="address" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('address')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="phone" :value="__('Phone')" />
                        <x-text-input id="phone" wire:model="phone" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="mb-1 font-semibold text-gray-800">{{ __('Receipt message') }}</h2>
                <p class="mb-4 text-sm text-gray-500">{{ __('Printed at the bottom of every invoice.') }}</p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="footerLo" :value="__('Message (Lao)')" />
                        <x-text-input id="footerLo" wire:model="footerLo" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('footerLo')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="footerEn" :value="__('Message (English)')" />
                        <x-text-input id="footerEn" wire:model="footerEn" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('footerEn')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <x-accent-button type="submit">{{ __('Save') }}</x-accent-button>
                <span wire:loading wire:target="save" class="text-sm text-gray-500">{{ __('Saving…') }}</span>
            </div>
        </form>

        <p class="mt-6 text-sm text-gray-500">
            {{ __('The address guests reach the app on is a technical setting and stays in .env as CUSTOMER_URL, because changing it means reprinting the table QR codes.') }}
        </p>
    </div>
</div>

<?php

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public ?int $selectedCategoryId = null;

    // Category form ---------------------------------------------------------
    public ?int $editingCategoryId = null;

    public string $categoryNameLo = '';

    public string $categoryNameEn = '';

    public int $categorySortOrder = 0;

    public bool $categoryIsActive = true;

    // Item form ------------------------------------------------------------
    public ?int $editingItemId = null;

    public string $itemNameLo = '';

    public string $itemNameEn = '';

    public string $itemDescriptionLo = '';

    public string $itemDescriptionEn = '';

    public string $itemPrice = '';

    public int $itemSortOrder = 0;

    public bool $itemIsAvailable = true;

    public bool $itemIsActive = true;

    #[Validate('nullable|image|max:2048')]
    public ?TemporaryUploadedFile $itemImage = null;

    public ?string $existingImagePath = null;

    public function mount(): void
    {
        $this->selectedCategoryId = Category::orderBy('sort_order')->value('id');
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::withCount('menuItems')->orderBy('sort_order')->orderBy('name_en')->get();
    }

    #[Computed]
    public function items(): Collection
    {
        if (! $this->selectedCategoryId) {
            return collect();
        }

        return MenuItem::where('category_id', $this->selectedCategoryId)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }

    #[Computed]
    public function selectedCategory(): ?Category
    {
        return $this->selectedCategoryId ? Category::find($this->selectedCategoryId) : null;
    }

    public function selectCategory(int $categoryId): void
    {
        $this->selectedCategoryId = $categoryId;
    }

    // Category actions -----------------------------------------------------

    public function newCategory(): void
    {
        $this->resetValidation();
        $this->reset('editingCategoryId', 'categoryNameLo', 'categoryNameEn', 'categorySortOrder', 'categoryIsActive');
        $this->categorySortOrder = (int) Category::max('sort_order') + 1;
        $this->dispatch('open-modal', 'category-form');
    }

    public function editCategory(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);

        $this->resetValidation();
        $this->editingCategoryId = $category->id;
        $this->categoryNameLo = $category->name_lo;
        $this->categoryNameEn = $category->name_en;
        $this->categorySortOrder = $category->sort_order;
        $this->categoryIsActive = $category->is_active;
        $this->dispatch('open-modal', 'category-form');
    }

    public function saveCategory(): void
    {
        $data = $this->validate([
            'categoryNameLo' => 'required|string|max:100',
            'categoryNameEn' => 'required|string|max:100',
            'categorySortOrder' => 'integer|min:0|max:999',
        ]);

        $attributes = [
            'name_lo' => $data['categoryNameLo'],
            'name_en' => $data['categoryNameEn'],
            'sort_order' => $data['categorySortOrder'],
            'is_active' => $this->categoryIsActive,
        ];

        $category = $this->editingCategoryId
            ? tap(Category::findOrFail($this->editingCategoryId))->update($attributes)
            : Category::create($attributes);

        $this->selectedCategoryId = $category->id;
        $this->dispatch('close-modal', 'category-form');
    }

    public function deleteCategory(int $categoryId): void
    {
        $category = Category::withCount('menuItems')->findOrFail($categoryId);

        if ($category->menu_items_count > 0) {
            $this->addError('category', __('Move or delete its items first.'));

            return;
        }

        $category->delete();

        if ($this->selectedCategoryId === $categoryId) {
            $this->selectedCategoryId = Category::orderBy('sort_order')->value('id');
        }
    }

    // Item actions ---------------------------------------------------------

    public function newItem(): void
    {
        $this->resetValidation();
        $this->reset(
            'editingItemId', 'itemNameLo', 'itemNameEn', 'itemDescriptionLo', 'itemDescriptionEn',
            'itemPrice', 'itemSortOrder', 'itemIsAvailable', 'itemIsActive', 'itemImage', 'existingImagePath',
        );
        $this->itemSortOrder = (int) MenuItem::where('category_id', $this->selectedCategoryId)->max('sort_order') + 1;
        $this->dispatch('open-modal', 'item-form');
    }

    public function editItem(int $itemId): void
    {
        $item = MenuItem::findOrFail($itemId);

        $this->resetValidation();
        $this->reset('itemImage');
        $this->editingItemId = $item->id;
        $this->itemNameLo = $item->name_lo;
        $this->itemNameEn = $item->name_en;
        $this->itemDescriptionLo = $item->description_lo ?? '';
        $this->itemDescriptionEn = $item->description_en ?? '';
        $this->itemPrice = (string) (int) $item->price;
        $this->itemSortOrder = $item->sort_order;
        $this->itemIsAvailable = $item->is_available;
        $this->itemIsActive = $item->is_active;
        $this->existingImagePath = $item->image_path;
        $this->dispatch('open-modal', 'item-form');
    }

    public function saveItem(): void
    {
        $data = $this->validate([
            'itemNameLo' => 'required|string|max:150',
            'itemNameEn' => 'required|string|max:150',
            'itemDescriptionLo' => 'nullable|string|max:500',
            'itemDescriptionEn' => 'nullable|string|max:500',
            'itemPrice' => 'required|numeric|min:0|max:99999999',
            'itemSortOrder' => 'integer|min:0|max:999',
            'itemImage' => 'nullable|image|max:2048',
        ]);

        $attributes = [
            'category_id' => $this->selectedCategoryId,
            'name_lo' => $data['itemNameLo'],
            'name_en' => $data['itemNameEn'],
            'description_lo' => $data['itemDescriptionLo'] ?: null,
            'description_en' => $data['itemDescriptionEn'] ?: null,
            'price' => $data['itemPrice'],
            'sort_order' => $data['itemSortOrder'],
            'is_available' => $this->itemIsAvailable,
            'is_active' => $this->itemIsActive,
        ];

        if ($this->itemImage) {
            if ($this->existingImagePath) {
                Storage::disk('public')->delete($this->existingImagePath);
            }

            $attributes['image_path'] = $this->itemImage->store('menu', 'public');
        }

        if ($this->editingItemId) {
            MenuItem::findOrFail($this->editingItemId)->update($attributes);
        } else {
            MenuItem::create($attributes);
        }

        $this->dispatch('close-modal', 'item-form');
    }

    public function toggleAvailable(int $itemId): void
    {
        $item = MenuItem::findOrFail($itemId);
        $item->update(['is_available' => ! $item->is_available]);
    }

    public function deleteItem(int $itemId): void
    {
        $item = MenuItem::findOrFail($itemId);

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $item->delete();
    }
}; ?>

<div>
    <x-page-header :title="__('Menu')" :subtitle="__('Categories, dishes, prices and photos')" />

    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            {{-- Categories --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 p-4">
                    <h2 class="font-semibold text-gray-700">{{ __('Categories') }}</h2>
                    <x-accent-button type="button" wire:click="newCategory">+ {{ __('Add') }}</x-accent-button>
                </div>

                @error('category')
                    <p class="px-4 pt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <ul class="divide-y">
                    @forelse ($this->categories as $category)
                        <li wire:key="cat-{{ $category->id }}"
                            class="flex cursor-pointer items-center justify-between border-s-4 px-4 py-3 transition {{ $category->id === $selectedCategoryId ? 'border-slate-700 bg-slate-50' : 'border-transparent hover:bg-gray-50' }}"
                            wire:click="selectCategory({{ $category->id }})">
                            <div>
                                <div class="font-medium {{ $category->is_active ? 'text-gray-800' : 'text-gray-400 line-through' }}">
                                    {{ $category->name_lo }}
                                </div>
                                <div class="text-sm text-gray-500">{{ $category->name_en }} · {{ $category->menu_items_count }} {{ __('items') }}</div>
                            </div>
                            <div class="flex gap-2 text-sm">
                                <button type="button" class="font-medium text-slate-700 hover:underline" wire:click.stop="editCategory({{ $category->id }})">{{ __('Edit') }}</button>
                                <button type="button" class="font-medium text-red-600 hover:underline" wire:click.stop="deleteCategory({{ $category->id }})" wire:confirm="{{ __('Delete this category?') }}">{{ __('Delete') }}</button>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-sm text-gray-500">{{ __('No categories yet.') }}</li>
                    @endforelse
                </ul>
            </div>

            {{-- Items --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm md:col-span-2">
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 p-4">
                    <h2 class="font-semibold text-gray-700">
                        {{ $this->selectedCategory?->name_lo ?? __('Items') }}
                        @if ($this->selectedCategory)
                            <span class="font-normal text-gray-400">/ {{ $this->selectedCategory->name_en }}</span>
                        @endif
                    </h2>
                    <x-accent-button type="button" wire:click="newItem" :disabled="! $selectedCategoryId">+ {{ __('Add item') }}</x-accent-button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="w-16 px-4 py-2"></th>
                                <th class="px-4 py-2">{{ __('Name') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('Price') }}</th>
                                <th class="px-4 py-2 text-center">{{ __('Available') }}</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($this->items as $item)
                                <tr wire:key="item-{{ $item->id }}" class="{{ $item->is_active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-2">
                                        @if ($item->image_path)
                                            <img src="{{ $item->imageUrl() }}" alt="" class="h-10 w-10 rounded object-cover">
                                        @else
                                            <div class="h-10 w-10 rounded bg-gray-100"></div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="font-medium text-gray-800">{{ $item->name_lo }}</div>
                                        <div class="text-gray-500">{{ $item->name_en }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">{{ number_format($item->price) }} ₭</td>
                                    <td class="px-4 py-2 text-center">
                                        <button type="button" wire:click="toggleAvailable({{ $item->id }})"
                                            class="px-2 py-1 rounded text-xs font-semibold {{ $item->is_available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $item->is_available ? __('Yes') : __('Sold out') }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-2 text-right whitespace-nowrap">
                                        <button type="button" class="font-medium text-slate-700 hover:underline" wire:click="editItem({{ $item->id }})">{{ __('Edit') }}</button>
                                        <button type="button" class="ms-2 font-medium text-red-600 hover:underline" wire:click="deleteItem({{ $item->id }})" wire:confirm="{{ __('Delete this item?') }}">{{ __('Delete') }}</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-gray-500">{{ __('No items in this category.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Category modal --}}
    <x-modal name="category-form" maxWidth="md">
        <form wire:submit="saveCategory" class="p-6 space-y-4">
            <h2 class="text-lg font-medium text-gray-900">{{ $editingCategoryId ? __('Edit category') : __('New category') }}</h2>

            <div>
                <x-input-label for="categoryNameLo" :value="__('Name (Lao)')" />
                <x-text-input id="categoryNameLo" wire:model="categoryNameLo" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('categoryNameLo')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="categoryNameEn" :value="__('Name (English)')" />
                <x-text-input id="categoryNameEn" wire:model="categoryNameEn" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('categoryNameEn')" class="mt-1" />
            </div>
            <div class="flex gap-4 items-end">
                <div class="w-32">
                    <x-input-label for="categorySortOrder" :value="__('Order')" />
                    <x-text-input id="categorySortOrder" type="number" min="0" wire:model="categorySortOrder" class="mt-1 block w-full" />
                </div>
                <label class="flex items-center gap-2 pb-2 text-sm">
                    <input type="checkbox" wire:model="categoryIsActive" class="rounded border-gray-300"> {{ __('Active') }}
                </label>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </div>
        </form>
    </x-modal>

    {{-- Item modal --}}
    <x-modal name="item-form" maxWidth="lg">
        <form wire:submit="saveItem" class="p-6 space-y-4">
            <h2 class="text-lg font-medium text-gray-900">{{ $editingItemId ? __('Edit item') : __('New item') }}</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="itemNameLo" :value="__('Name (Lao)')" />
                    <x-text-input id="itemNameLo" wire:model="itemNameLo" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('itemNameLo')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="itemNameEn" :value="__('Name (English)')" />
                    <x-text-input id="itemNameEn" wire:model="itemNameEn" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('itemNameEn')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="itemDescriptionLo" :value="__('Description (Lao)')" />
                    <textarea id="itemDescriptionLo" wire:model="itemDescriptionLo" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                </div>
                <div>
                    <x-input-label for="itemDescriptionEn" :value="__('Description (English)')" />
                    <textarea id="itemDescriptionEn" wire:model="itemDescriptionEn" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                </div>
                <div>
                    <x-input-label for="itemPrice" :value="__('Price (₭)')" />
                    <x-text-input id="itemPrice" type="number" min="0" step="1" wire:model="itemPrice" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('itemPrice')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="itemSortOrder" :value="__('Order')" />
                    <x-text-input id="itemSortOrder" type="number" min="0" wire:model="itemSortOrder" class="mt-1 block w-full" />
                </div>
                <div class="col-span-2">
                    <x-input-label for="itemImage" :value="__('Photo')" />
                    <div class="mt-1 flex items-center gap-4">
                        @if ($itemImage)
                            <img src="{{ $itemImage->temporaryUrl() }}" alt="" class="h-16 w-16 rounded object-cover">
                        @elseif ($existingImagePath)
                            <img src="/storage/{{ $existingImagePath }}" alt="" class="h-16 w-16 rounded object-cover">
                        @endif
                        <input id="itemImage" type="file" accept="image/*" wire:model="itemImage" class="text-sm">
                        <span wire:loading wire:target="itemImage" class="text-sm text-gray-500">{{ __('Uploading…') }}</span>
                    </div>
                    <x-input-error :messages="$errors->get('itemImage')" class="mt-1" />
                </div>
                <div class="col-span-2 flex gap-6 text-sm">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="itemIsAvailable" class="rounded border-gray-300"> {{ __('Available') }}
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="itemIsActive" class="rounded border-gray-300"> {{ __('Active (shown on menu)') }}
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                <x-primary-button wire:loading.attr="disabled" wire:target="itemImage">{{ __('Save') }}</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>

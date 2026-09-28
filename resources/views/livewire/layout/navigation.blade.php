<?php

use App\Enums\Area;
use App\Enums\UserRole;
use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    /**
     * Navigation links the current user is allowed to see.
     *
     * @return array<int, array{route: string, label: string, pattern: string, icon: string}>
     */
    public function links(): array
    {
        $user = auth()->user();
        $links = [];

        if ($user->hasRole(UserRole::Kitchen, UserRole::Admin)) {
            $links[] = ['route' => 'kitchen', 'label' => __('Kitchen'), 'pattern' => 'kitchen', 'icon' => '🍳'];
        }

        if ($user->hasRole(UserRole::Cashier, UserRole::Admin)) {
            $links[] = ['route' => 'cashier', 'label' => __('Cashier'), 'pattern' => 'cashier*', 'icon' => '💵'];
        }

        if ($user->isAdmin()) {
            $links[] = ['route' => 'admin.menu', 'label' => __('Menu'), 'pattern' => 'admin.menu', 'icon' => '📋'];
            $links[] = ['route' => 'admin.tables', 'label' => __('Tables'), 'pattern' => 'admin.tables*', 'icon' => '🪑'];
            $links[] = ['route' => 'admin.reports', 'label' => __('Reports'), 'pattern' => 'admin.reports', 'icon' => '📊'];
        }

        return $links;
    }

    public function area(): Area
    {
        return Area::current();
    }
}; ?>

@php $area = $this->area(); @endphp

{{-- The bar takes the colour of the area being viewed, so a glance at any device says where you are. --}}
<nav x-data="{ open: false }" class="{{ $area->barClasses() }} shadow-md">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex items-center gap-8">
                <!-- Brand + current area -->
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 text-white">
                    <x-app-logo size="h-9 w-9" :show-name="true" name-class="text-base font-bold" />
                    <span class="hidden rounded bg-white/20 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide sm:inline">
                        {{ $area->label() }}
                    </span>
                </a>

                <!-- Navigation Links -->
                <div class="hidden gap-1 sm:flex">
                    @foreach ($this->links() as $link)
                        <x-nav-link :href="route($link['route'])" :active="request()->routeIs($link['pattern'])" wire:navigate>
                            <span class="me-1.5">{{ $link['icon'] }}</span>{{ $link['label'] }}
                        </x-nav-link>
                    @endforeach
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden items-center gap-3 sm:flex">
                <x-language-switcher :on-dark="true" />

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium text-white/90 transition hover:bg-white/10 hover:text-white focus:outline-none">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 text-white/80 transition hover:bg-white/10 hover:text-white focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-white/20 sm:hidden">
        <div class="space-y-1 py-2">
            @foreach ($this->links() as $link)
                <x-responsive-nav-link :href="route($link['route'])" :active="request()->routeIs($link['pattern'])" wire:navigate>
                    <span class="me-1.5">{{ $link['icon'] }}</span>{{ $link['label'] }}
                </x-responsive-nav-link>
            @endforeach
        </div>

        <!-- Responsive Settings Options -->
        <div class="border-t border-white/20 py-3">
            <div class="flex items-center justify-between px-4">
                <div>
                    <div class="text-base font-medium text-white" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                    <div class="text-sm text-white/70">{{ auth()->user()->email }}</div>
                </div>
                <x-language-switcher :on-dark="true" />
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>

<?php

namespace App\Enums;

use Illuminate\Support\Facades\Route;

/**
 * The staff area a page belongs to. Each one has its own accent colour so staff
 * can tell at a glance which screen a device is showing.
 *
 * Class strings are written out in full because Tailwind only keeps classes it
 * can find literally in the source.
 */
enum Area: string
{
    case Admin = 'admin';
    case Kitchen = 'kitchen';
    case Cashier = 'cashier';

    /** The area of the page currently being rendered. */
    public static function current(): self
    {
        $route = Route::currentRouteName() ?? '';

        return match (true) {
            str_starts_with($route, 'kitchen') => self::Kitchen,
            str_starts_with($route, 'cashier'), str_starts_with($route, 'invoice') => self::Cashier,
            default => self::Admin,
        };
    }

    public function label(): string
    {
        return __('roles.'.$this->value);
    }

    /** Top navigation bar. */
    public function barClasses(): string
    {
        return match ($this) {
            self::Admin => 'bg-slate-800',
            self::Kitchen => 'bg-amber-700',
            self::Cashier => 'bg-emerald-700',
        };
    }

    /** Hover/active states for links inside the bar. */
    public function barHoverClasses(): string
    {
        return match ($this) {
            self::Admin => 'hover:bg-slate-700',
            self::Kitchen => 'hover:bg-amber-600',
            self::Cashier => 'hover:bg-emerald-600',
        };
    }

    /** Tinted strip behind the page title. */
    public function headerClasses(): string
    {
        return match ($this) {
            self::Admin => 'bg-slate-50 border-slate-200',
            self::Kitchen => 'bg-amber-50 border-amber-200',
            self::Cashier => 'bg-emerald-50 border-emerald-200',
        };
    }

    /** Solid accent used for primary actions and counters. */
    public function accentClasses(): string
    {
        return match ($this) {
            self::Admin => 'bg-slate-800 hover:bg-slate-700 focus:ring-slate-500',
            self::Kitchen => 'bg-amber-700 hover:bg-amber-600 focus:ring-amber-500',
            self::Cashier => 'bg-emerald-700 hover:bg-emerald-600 focus:ring-emerald-500',
        };
    }

    /** Text-only accent, e.g. links and section labels. */
    public function textClasses(): string
    {
        return match ($this) {
            self::Admin => 'text-slate-700',
            self::Kitchen => 'text-amber-800',
            self::Cashier => 'text-emerald-800',
        };
    }
}

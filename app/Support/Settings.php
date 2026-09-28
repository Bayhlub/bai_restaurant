<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Restaurant details the owner edits in the admin screen. Stored in the
 * settings table, cached, and pushed into config on boot so the rest of the
 * app keeps reading config('app.name'), config('restaurant.address') and so on.
 *
 * Anything left blank falls back to the value in .env.
 */
class Settings
{
    private const CACHE_KEY = 'restaurant.settings';

    /**
     * Editable keys mapped to the config entry each one overrides.
     *
     * @var array<string, string>
     */
    public const KEYS = [
        'name_en' => 'app.name',
        'name_lo' => 'restaurant.name_lo',
        'address' => 'restaurant.address',
        'phone' => 'restaurant.phone',
        'footer_lo' => 'restaurant.receipt_footer_lo',
        'footer_en' => 'restaurant.receipt_footer_en',
    ];

    /**
     * Stored values, keyed as in KEYS. Empty when the table does not exist yet
     * (a fresh install before migrating), which leaves the .env defaults in place.
     *
     * @return array<string, string|null>
     */
    public static function all(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());
        } catch (Throwable) {
            return [];
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::all()[$key] ?? null;

        return filled($value) ? $value : $default;
    }

    /**
     * Persist the given keys and refresh the cache.
     *
     * @param  array<string, string|null>  $values
     */
    public static function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::KEYS)) {
                continue;
            }

            Setting::updateOrCreate(['key' => $key], ['value' => filled($value) ? trim($value) : null]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** Overlay the stored values onto config; blank ones keep the .env default. */
    public static function applyToConfig(): void
    {
        $stored = self::all();

        foreach (self::KEYS as $key => $configPath) {
            if (filled($stored[$key] ?? null)) {
                config([$configPath => $stored[$key]]);
            }
        }
    }
}

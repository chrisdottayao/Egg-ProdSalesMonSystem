<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * S12/S13 — the admin-set selling price per egg size. Each size is one
 * settings row, egg_price_{size} (e.g. egg_price_no_value), falling back to
 * config/egg_prices.php.
 */
class EggPrices
{
    /** Sales sizes, in display order — the same list the sales importer accepts. */
    public static function sizes(): array
    {
        return array_keys(config('egg_prices.defaults'));
    }

    public static function settingKey(string $size): string
    {
        return 'egg_price_' . Str::snake(strtolower($size));
    }

    /** Form field name for a size (no spaces). */
    public static function fieldKey(string $size): string
    {
        return Str::snake(strtolower($size));
    }

    public static function for(string $size): ?float
    {
        $default = config("egg_prices.defaults.{$size}");
        if ($default === null) {
            return null;
        }

        return (float) Setting::get(self::settingKey($size), $default);
    }

    /**
     * S13: the price to record for a sale — the given one if present (a
     * special price is always kept), else the set price for the size.
     */
    public static function priceOrDefault(mixed $given, string $size): ?float
    {
        $given = is_string($given) ? trim($given) : $given;

        return ($given === null || $given === '') ? self::for($size) : (float) $given;
    }

    /** @return array<string, float> size => price */
    public static function all(): array
    {
        $saved = Setting::where('key', 'like', 'egg_price_%')->pluck('value', 'key');

        return collect(self::sizes())->mapWithKeys(fn ($size) => [
            $size => (float) ($saved[self::settingKey($size)] ?? config("egg_prices.defaults.{$size}")),
        ])->all();
    }
}

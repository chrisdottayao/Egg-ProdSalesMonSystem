<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * A DB-persisted override for a config() default. Falls back to $default
     * (normally the matching config/expenses.php value) when nothing has
     * been set yet, so callers work identically before and after an admin
     * ever touches the Settings screen.
     */
    public static function get(string $key, $default = null)
    {
        $value = self::where('key', $key)->value('value');

        return $value ?? $default;
    }

    public static function set(string $key, $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\EggPrices;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    // Expense ESTIMATE defaults from config/expenses.php that the owner may
    // eventually confirm with a real figure. Editing here is a one-field
    // change; it never rewrites already-saved expense rows, only what new
    // entries default to.
    private const KEYS = ['feed_price_per_bag_estimate', 'pullet_cost_per_head_estimate'];

    public function index()
    {
        $values = collect(self::KEYS)->mapWithKeys(fn ($key) => [
            $key => Setting::get($key, config("expenses.{$key}")),
        ]);

        // S12: egg price per size (₱ per piece).
        $eggPrices = EggPrices::all();

        return view('settings.index', compact('values', 'eggPrices'));
    }

    /**
     * Each section of the Settings page is its own form, so every field is
     * 'sometimes': a save only touches the fields that section submitted.
     */
    public function update(Request $request)
    {
        $rules = [
            'feed_price_per_bag_estimate'   => 'sometimes|required|numeric|min:0',
            'pullet_cost_per_head_estimate' => 'sometimes|required|numeric|min:0',
            'egg_prices'                    => 'sometimes|array',
        ];
        foreach (EggPrices::sizes() as $size) {
            $rules['egg_prices.' . EggPrices::fieldKey($size)] = 'required_with:egg_prices|numeric|min:0';
        }
        $validated = $request->validate($rules, [], collect(EggPrices::sizes())->mapWithKeys(
            fn ($size) => ['egg_prices.' . EggPrices::fieldKey($size) => "{$size} price"]
        )->all());

        foreach (self::KEYS as $key) {
            if (array_key_exists($key, $validated)) {
                Setting::set($key, $validated[$key]);
            }
        }

        // Changing a price only affects what NEW sales default to — saved
        // sales keep the price they were recorded with.
        if (isset($validated['egg_prices'])) {
            foreach (EggPrices::sizes() as $size) {
                Setting::set(EggPrices::settingKey($size), $validated['egg_prices'][EggPrices::fieldKey($size)]);
            }
        }

        return redirect()->route('settings.index')->with('success', 'Settings updated.');
    }
}

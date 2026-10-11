<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\EggPrices;
use App\Support\Thresholds;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        // Phase 3 item 5: thresholds and their written basis.
        $thresholds = collect(Thresholds::DEFINITIONS)->map(fn ($def, $key) => $def + [
            'key'     => $key,
            'value'   => Thresholds::get($key),
            'default' => Thresholds::default($key),
            'basis'   => Thresholds::basis($key),
        ])->groupBy('group');

        return view('settings.index', compact('values', 'eggPrices', 'thresholds'));
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

    /**
     * Phase 3 item 5 (S1, S2): the thresholds the system decides with, and
     * the written basis for each. Only changed values are saved; every change
     * is audited old → new (here as one summary row, and per setting by the
     * Setting model itself).
     */
    public function updateThresholds(Request $request)
    {
        $rules = ['threshold_basis' => 'required|array'];
        foreach (Thresholds::DEFINITIONS as $key => $def) {
            $rules["thresholds.{$key}"] = match ($def['group']) {
                'Production rate bands' => 'required|numeric|min:0|max:100',
                'Mortality bands'       => 'required|numeric|min:0|max:100',
                default                 => $key === 'anomaly_mortality_spike_min'
                    ? 'required|integer|min:1|max:100000'
                    : 'required|numeric|min:1|max:1000',
            };
            $rules["threshold_basis.{$key}"] = 'required|string|max:500';
        }
        $names = collect(Thresholds::DEFINITIONS)->mapWithKeys(fn ($d, $k) => ["thresholds.{$k}" => $d['label']])->all();
        $validated = $request->validate($rules, [], $names);
        $new = $validated['thresholds'];

        // Ordering: Healthy above Cull (Watch is the range between them), and
        // mortality bands strictly increasing.
        $errors = [];
        if ($new['prod_rate_healthy_min'] <= $new['prod_rate_cull_max']) {
            $errors['thresholds.prod_rate_healthy_min'] = 'Healthy must be higher than Cull-consideration (Watch is the range between them).';
        }
        $bands = ['mortality_band_elevated', 'mortality_band_abnormal', 'mortality_band_high', 'mortality_band_severe', 'mortality_band_extreme'];
        for ($i = 1; $i < count($bands); $i++) {
            if ($new[$bands[$i]] <= $new[$bands[$i - 1]]) {
                $errors["thresholds.{$bands[$i]}"] = Thresholds::DEFINITIONS[$bands[$i]]['label'] . ' must be higher than ' . strtolower(Thresholds::DEFINITIONS[$bands[$i - 1]]['label']) . '.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $changes = [];
        foreach (Thresholds::DEFINITIONS as $key => $def) {
            $old = Thresholds::get($key);
            if ((float) $new[$key] !== $old) {
                Setting::set('threshold_' . $key, $new[$key]);
                $changes[$key] = ['old' => $old, 'new' => (float) $new[$key]];
            }
            $oldBasis = Thresholds::basis($key);
            if (trim($validated['threshold_basis'][$key]) !== $oldBasis) {
                Setting::set('threshold_basis_' . $key, trim($validated['threshold_basis'][$key]));
                $changes["{$key} (basis)"] = ['old' => $oldBasis, 'new' => trim($validated['threshold_basis'][$key])];
            }
        }

        if ($changes) {
            AuditLog::create([
                'user_id'    => $request->user()->id,
                'action'     => 'thresholds_update',
                'model_type' => 'Thresholds',
                'details'    => ['changes' => $changes],
            ]);
        }

        return redirect()->route('settings.index')->with('success', $changes ? 'Thresholds updated.' : 'No threshold changes.');
    }
}

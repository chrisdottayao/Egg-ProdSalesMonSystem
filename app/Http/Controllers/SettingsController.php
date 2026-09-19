<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    // The only settings this prototype exposes — both are ESTIMATE defaults
    // from config/expenses.php that the owner may eventually confirm with a
    // real figure. Editing here is a one-field change; it never rewrites
    // already-saved expense rows, only what new entries default to.
    private const KEYS = ['feed_price_per_bag_estimate', 'pullet_cost_per_head_estimate'];

    public function index()
    {
        $values = collect(self::KEYS)->mapWithKeys(fn ($key) => [
            $key => Setting::get($key, config("expenses.{$key}")),
        ]);

        return view('settings.index', compact('values'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'feed_price_per_bag_estimate'   => 'required|numeric|min:0',
            'pullet_cost_per_head_estimate' => 'required|numeric|min:0',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        return redirect()->route('settings.index')->with('success', 'Settings updated.');
    }
}

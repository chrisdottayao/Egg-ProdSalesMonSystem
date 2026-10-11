{{-- S24: shown after a submit where sold would exceed produced. Ticking the
     box and saving again records the entry with a "sold > produced" flag. --}}
@if(session('sold_gt_produced'))
    <div class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded-lg space-y-2" role="alert">
        <p class="text-sm font-semibold text-orange-900">Sold is higher than produced</p>
        <p class="text-sm text-orange-800">{{ session('sold_gt_produced')['message'] }}</p>
        <p class="text-xs text-orange-700">This can be legitimate (for example, stock carried over from earlier days). If you continue, the entry is saved and flagged for a manager or admin to review.</p>
        <label class="flex items-center gap-2 text-sm font-medium text-orange-900">
            <input type="checkbox" name="confirm_sold_gt_produced" value="1" required class="rounded border-orange-400 text-orange-600" />
            Sold is higher than produced. Continue and save anyway.
        </label>
    </div>
@endif

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HenBatch extends Model
{
    protected $fillable = [
        'batch_id', 'batch_size', 'status', 'entry_date', 'notes', 'pen_number', 'building',
        'placement_date', 'breed', 'building_no',
    ];

    protected $casts = [
        'entry_date'      => 'date',
        'placement_date'  => 'date',
    ];

    public function cullRecords(): HasMany
    {
        return $this->hasMany(CullRecord::class);
    }

    public function buildingDailies(): HasMany
    {
        return $this->hasMany(BuildingDaily::class);
    }

    public static function activeHenCount(): int
    {
        return self::where('status', 'Active')->sum('batch_size');
    }
}

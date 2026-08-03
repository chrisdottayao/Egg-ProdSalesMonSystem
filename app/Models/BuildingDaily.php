<?php

namespace App\Models;

use App\Services\ProductionRollupService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuildingDaily extends Model
{
    protected $table = 'building_daily';

    protected $fillable = [
        'date',
        'hen_batch_id',
        'population',
        'mortality',
        'net_birds',
        'eggs_house',
        'eggs_eggroom',
        'soft_shell',
        'age_weeks',
        'feed_bags',
        'prod_rate',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function henBatch(): BelongsTo
    {
        return $this->belongsTo(HenBatch::class);
    }

    protected static function booted()
    {
        static::saving(function (BuildingDaily $model) {
            $model->prod_rate = $model->population > 0
                ? round(($model->eggs_house / $model->population) * 100, 2)
                : 0;
        });

        static::saved(fn (BuildingDaily $model) => (new ProductionRollupService)->rollupDate($model->date));
        static::deleted(fn (BuildingDaily $model) => (new ProductionRollupService)->rollupDate($model->date));
    }
}

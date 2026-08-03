<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EggGradingDaily extends Model
{
    protected $table = 'egg_grading_daily';

    // 1 case = 12 trays = 360 pieces
    private const TRAYS_PER_CASE = 12;
    private const PIECES_PER_CASE = 360;
    private const PIECES_PER_TRAY = self::PIECES_PER_CASE / self::TRAYS_PER_CASE;

    public const CATEGORIES = [
        'No Value', 'No Weight', 'Peewee', 'Small', 'Medium',
        'Large', 'XLarge', 'Jumbo', 'Broken', 'Dirty', 'Waste', 'TAPON',
    ];

    // Categories that are a partition of the day's production, not a deduction from it
    public const NON_DEDUCTING_CATEGORIES = ['Broken', 'Dirty', 'Waste'];

    protected $fillable = [
        'date',
        'category',
        'cases',
        'trays',
        'pieces',
        'total_pcs',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected static function booted()
    {
        static::saving(function (EggGradingDaily $model) {
            $model->total_pcs = ($model->cases * self::PIECES_PER_CASE)
                + ($model->trays * self::PIECES_PER_TRAY)
                + $model->pieces;
        });
    }
}

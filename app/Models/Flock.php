<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flock extends Model
{
    protected $fillable = [
        'name', 'breed', 'quantity', 'acquisition_date', 'status', 'notes',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
    ];

    public function productions(): HasMany
    {
        return $this->hasMany(EggProduction::class);
    }
}

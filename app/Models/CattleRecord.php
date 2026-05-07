<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CattleRecord extends Model
{
    protected $fillable = [
        'ear_tag', 'status', 'entry_date', 'notes',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];
}

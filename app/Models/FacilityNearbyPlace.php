<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityNearbyPlace extends Model
{
    public const CATEGORIES = ['hospital', 'pharmacy', 'doctor', 'bus_stop', 'railway_station', 'supermarket'];

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'distance_meters' => 'integer',
            'discovered_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}

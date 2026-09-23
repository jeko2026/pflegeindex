<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityGeocode extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'geocoded_at' => 'datetime'];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}

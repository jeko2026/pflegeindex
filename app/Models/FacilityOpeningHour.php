<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityOpeningHour extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['hours_json' => 'array', 'verified_at' => 'datetime'];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}

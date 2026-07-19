<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoState extends Model
{
    use HasFactory;

    protected $fillable = ['country_id', 'ags', 'name', 'slug'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(GeoCountry::class, 'country_id');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(GeoDistrict::class, 'state_id');
    }
}

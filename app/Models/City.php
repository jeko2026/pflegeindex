<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'state',
        'state_slug',
        'geo_municipality_id',
        'geo_match_status',
        'geo_match_method',
        'geo_match_confidence',
        'geo_requires_manual_review',
    ];

    protected function casts(): array
    {
        return ['geo_requires_manual_review' => 'boolean'];
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    public function geoMunicipality(): BelongsTo
    {
        return $this->belongsTo(GeoMunicipality::class, 'geo_municipality_id');
    }
}

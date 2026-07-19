<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoMunicipality extends Model
{
    use HasFactory;

    protected $fillable = [
        'district_id',
        'ags',
        'name',
        'normalized_name',
        'slug',
        'municipality_type',
        'postal_code_official',
        'source_name',
        'source_date',
        'source_url',
    ];

    protected function casts(): array
    {
        return ['source_date' => 'date'];
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(GeoDistrict::class, 'district_id');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class, 'geo_municipality_id');
    }
}

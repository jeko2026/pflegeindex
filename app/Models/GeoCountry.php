<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoCountry extends Model
{
    use HasFactory;

    protected $fillable = ['iso2', 'iso3', 'name', 'slug'];

    public function states(): HasMany
    {
        return $this->hasMany(GeoState::class, 'country_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoDistrict extends Model
{
    use HasFactory;

    protected $fillable = ['state_id', 'ags', 'name', 'slug', 'type'];

    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->type !== 'kreisfreie_stadt') {
                return $this->name;
            }

            return preg_replace('/,\s*Stadt$/u', '', $this->name) ?: $this->name;
        });
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(GeoState::class, 'state_id');
    }

    public function municipalities(): HasMany
    {
        return $this->hasMany(GeoMunicipality::class, 'district_id');
    }
}

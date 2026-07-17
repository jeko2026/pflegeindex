<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'state',
        'state_slug',
    ];

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }
}

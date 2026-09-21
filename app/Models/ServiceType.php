<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ServiceType extends Model
{
    protected $guarded = [];

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class);
    }
}

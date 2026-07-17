<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'city_id',
        'name',
        'slug',
        'postal_code',
        'street',
        'house_number',
        'address',
        'type',
        'source_sector',
        'description',
        'description_draft',
        'description_draft_sources',
        'description_draft_checked_at',
        'description_sources',
        'description_checked_at',
        'description_ai_assisted',
        'phone',
        'email',
        'website',
        'contact_source',
        'contact_status',
        'contact_checked_at',
        'contact_locked',
        'care_types',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'care_types' => 'array',
            'features' => 'array',
            'description_draft_sources' => 'array',
            'description_draft_checked_at' => 'datetime',
            'description_sources' => 'array',
            'description_checked_at' => 'datetime',
            'description_ai_assisted' => 'boolean',
            'contact_checked_at' => 'datetime',
            'contact_locked' => 'boolean',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function contactSuggestions(): HasMany
    {
        return $this->hasMany(ContactSuggestion::class);
    }

    public function formattedPhone(): ?string
    {
        if ($this->phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $this->phone);

        if (! is_string($digits) || ! str_starts_with($digits, '49')) {
            return $this->phone;
        }

        $groups = str_split(substr($digits, 2), 3);
        $lastGroup = end($groups);

        if (count($groups) > 1 && is_string($lastGroup) && strlen($lastGroup) < 3) {
            $tail = array_pop($groups);
            $groups[array_key_last($groups)] .= $tail;
        }

        return '+49 '.implode(' ', $groups);
    }

    public function contactStatusLabel(): string
    {
        return match ($this->contact_status) {
            'verified' => 'Geprüft',
            'pending' => 'In Prüfung',
            'not_found' => 'Nicht gefunden',
            default => 'Noch offen',
        };
    }
}

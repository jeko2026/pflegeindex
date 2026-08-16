<?php

namespace App\Models;

use App\Services\QualityScoreService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Facility extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        $forgetQualityCache = static function (self $facility): void {
            if ($facility->city_id !== null) {
                Cache::forget(QualityScoreService::cityCacheKey((int) $facility->city_id));
            }
        };

        static::saved($forgetQualityCache);
        static::deleted($forgetQualityCache);
    }

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
        'official_email_absent',
        'website',
        'official_website_absent',
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
            'official_website_absent' => 'boolean',
            'official_email_absent' => 'boolean',
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

    public function contactReviewIsOpen(): bool
    {
        return $this->contact_status === null
            || in_array($this->contact_status, ['unverified', 'pending'], true);
    }

    public function emailReviewIsOpen(): bool
    {
        return blank($this->email) && ! $this->official_email_absent;
    }

    public function websiteReviewIsOpen(): bool
    {
        return blank($this->website) && ! $this->official_website_absent;
    }

    public function scopeContactReviewOpen(Builder $query): Builder
    {
        return $query->where(fn (Builder $open): Builder => $open
            ->whereNull('contact_status')
            ->orWhereIn('contact_status', ['unverified', 'pending']));
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'fingerprint',
        'parser_status',
        'phone',
        'email',
        'website',
        'phone_source',
        'email_source',
        'confidence',
        'checked_at',
        'decision',
        'reviewed_at',
        'reviewed_by',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function parserStatusLabel(): string
    {
        return match ($this->parser_status) {
            'verified' => 'Kontakt gefunden',
            'not_found' => 'Nicht gefunden',
            default => $this->parser_status,
        };
    }

    public function decisionLabel(): string
    {
        return match ($this->decision) {
            'accepted' => 'Angenommen',
            'rejected' => 'Abgelehnt',
            default => 'Zu prüfen',
        };
    }
}

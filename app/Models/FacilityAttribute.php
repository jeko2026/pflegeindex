<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityAttribute extends Model
{
    public const REVIEW_CANDIDATE = 'candidate';

    public const REVIEW_AUTO_APPROVED = 'auto_approved';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    public const REVIEW_NEEDS_REVIEW = 'needs_review';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'discovered_at' => 'datetime',
            'verified_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(FacilitySource::class, 'source_id');
    }
}

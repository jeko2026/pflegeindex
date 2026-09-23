<?php

namespace App\Services\FacilityEnrichment;

use App\Models\Facility;
use App\Models\FacilityAttribute;
use InvalidArgumentException;

final class FacilityAttributeUpserter
{
    public function __construct(private AttributeReviewPolicy $policy) {}

    public function store(Facility $facility, array $data): FacilityAttribute
    {
        if (! AttributeTaxonomy::known($data['attribute_key'])) {
            throw new InvalidArgumentException('Unknown facility attribute key.');
        }
        if (! in_array($data['confidence'], ['HIGH', 'MEDIUM', 'LOW'], true)) {
            throw new InvalidArgumentException('Invalid confidence.');
        }
        if (! filter_var($data['source_url'], FILTER_VALIDATE_URL) || trim($data['source_excerpt']) === '') {
            throw new InvalidArgumentException('Valid source evidence is required.');
        }
        $identity = collect($data)->only(['attribute_key', 'normalized_value', 'source_url'])->all();
        $existing = $facility->enrichmentAttributes()->where($identity)->first();

        return FacilityAttribute::updateOrCreate($identity + ['facility_id' => $facility->id], [...$data, 'review_status' => $existing?->review_status ?? $this->policy->status($data['attribute_key'], $data['confidence']), 'discovered_at' => $existing?->discovered_at ?? now()]);
    }
}

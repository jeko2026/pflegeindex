<?php

namespace App\Services\FacilityEnrichment;

use App\Models\FacilityAttribute;

final class AttributeReviewPolicy
{
    public function status(string $key, string $confidence): string
    {
        if ($confidence === 'LOW' || AttributeTaxonomy::medical($key)) {
            return FacilityAttribute::REVIEW_NEEDS_REVIEW;
        }

        return $confidence === 'HIGH' ? FacilityAttribute::REVIEW_AUTO_APPROVED : FacilityAttribute::REVIEW_NEEDS_REVIEW;
    }
}

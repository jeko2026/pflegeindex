<?php

namespace App\Services\FacilityEnrichment;

use App\Models\Facility;
use Illuminate\Support\Str;

final class FacilityRelationEvaluator
{
    public function evaluate(Facility $facility, array $evidence): array
    {
        $text = $this->normalise(($evidence['source_text'] ?? '').' '.($evidence['source_url'] ?? ''));
        $signals = [];
        if ($this->contains($text, $facility->name)) {
            $signals[] = 'facility_name';
        }
        if ($facility->city && $this->contains($text, $facility->city->name)) {
            $signals[] = 'city';
        }
        if ($facility->postal_code && str_contains($text, $facility->postal_code)) {
            $signals[] = 'postcode';
        }
        if ($facility->address && $this->contains($text, $facility->address)) {
            $signals[] = 'address';
        }
        if ($facility->phone && str_contains(preg_replace('/\D+/', '', $text), preg_replace('/\D+/', '', $facility->phone))) {
            $signals[] = 'phone';
        }
        $path = (string) parse_url($evidence['source_url'], PHP_URL_PATH);
        $isStandortUrl = $facility->slug !== '' && str_contains($this->normalise($path), $this->normalise($facility->slug));
        if ($isStandortUrl) {
            $signals[] = 'standort_url';
        }
        $count = count($signals);
        $confidence = $isStandortUrl || (in_array('facility_name', $signals, true) && $count >= 3) ? 'HIGH' : ($count >= 2 ? 'MEDIUM' : 'LOW');

        return ['confidence' => $confidence, 'relation_reason' => $count ? implode(', ', $signals) : 'no_confirmed_relation', 'signals' => $signals];
    }

    private function contains(string $haystack, string $needle): bool
    {
        $needle = trim($this->normalise($needle));

        return $needle !== '' && str_contains($haystack, $needle);
    }

    private function normalise(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim()->value();
    }
}

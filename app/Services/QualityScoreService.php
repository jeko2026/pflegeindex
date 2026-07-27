<?php

namespace App\Services;

use App\Models\Facility;
use App\Support\HttpUrl;
use Illuminate\Support\Collection;

final class QualityScoreService
{
    /** @return array{score:int, quality_label:string, quality_color:string, progress_percentage:int, verified:bool, review_documented:bool, verified_at:?string, source:?string, criteria:array<string,bool>} */
    public function evaluate(Facility $facility): array
    {
        $criteria = [
            'phone' => filled($facility->phone),
            'email' => filled($facility->email),
            'website' => filled($facility->website),
            'address' => $this->addressIsConfirmed($facility),
            'source' => filled($facility->contact_source),
            'verified' => $facility->contact_status === 'verified',
        ];
        $weights = ['phone' => 20, 'email' => 15, 'website' => 20, 'address' => 20, 'source' => 10, 'verified' => 15];
        $score = array_sum(array_map(
            fn (string $key): int => $criteria[$key] ? $weights[$key] : 0,
            array_keys($weights),
        ));
        $verified = $criteria['verified'];
        $hasContact = filled($facility->phone) || filled($facility->email) || filled($facility->website);
        $reviewDocumented = $verified
            && $facility->contact_checked_at !== null
            && $hasContact
            && HttpUrl::isValid($facility->contact_source);

        return [
            'score' => $score,
            'quality_label' => $this->label($score),
            'quality_color' => $this->color($score),
            'progress_percentage' => $score,
            'verified' => $verified,
            'review_documented' => $reviewDocumented,
            'verified_at' => $reviewDocumented ? $facility->contact_checked_at?->format('d.m.Y') : null,
            'source' => $reviewDocumented ? (string) $facility->contact_source : null,
            'criteria' => $criteria,
        ];
    }

    /** @param iterable<int, Facility> $facilities @return array{average_score:float, verified_percentage:float, verified_count:int, unverified_count:int, total_facilities:int} */
    public function aggregate(iterable $facilities): array
    {
        $items = $facilities instanceof Collection ? $facilities->values() : collect($facilities)->values();
        $total = $items->count();
        $scores = $items->map(fn (Facility $facility): array => $this->evaluate($facility));
        $verified = $scores->where('verified', true)->count();

        return [
            'average_score' => $total > 0 ? round($scores->avg('score'), 2) : 0.0,
            'verified_percentage' => $total > 0 ? round(($verified / $total) * 100, 2) : 0.0,
            'verified_count' => $verified,
            'unverified_count' => $total - $verified,
            'total_facilities' => $total,
        ];
    }

    private function addressIsConfirmed(Facility $facility): bool
    {
        return $facility->contact_status === 'verified'
            && filled($facility->address)
            && filled($facility->postal_code)
            && $facility->city_id !== null;
    }

    private function label(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Sehr hoch',
            $score >= 75 => 'Hoch',
            $score >= 50 => 'Gut',
            $score >= 25 => 'Teilweise',
            default => 'Unvollständig',
        };
    }

    private function color(int $score): string
    {
        return match (true) {
            $score >= 90 => 'green',
            $score >= 75 => 'teal',
            $score >= 50 => 'amber',
            $score >= 25 => 'orange',
            default => 'red',
        };
    }
}

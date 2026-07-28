<?php

namespace App\Services;

use App\Models\Facility;
use App\Support\HttpUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class QualityScoreService
{
    /** @return array{score:int, quality_label:string, quality_color:string, progress_percentage:int, verified:bool, review_documented:bool, verified_at:?string, source:?string, trust:array{status:string, label:string, verified_at:?string, source:?string}, criteria:array<string,bool>, field_statuses:array<string, array{key:string, label:string, status:string, display_text:string, accessible_label:string}>} */
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
            ...$this->qualityForScore($score),
            'progress_percentage' => $score,
            'verified' => $verified,
            'review_documented' => $reviewDocumented,
            'verified_at' => $reviewDocumented ? $facility->contact_checked_at?->format('d.m.Y') : null,
            'source' => $reviewDocumented ? (string) $facility->contact_source : null,
            'trust' => $this->trustSummary($facility, $reviewDocumented),
            'criteria' => $criteria,
            'field_statuses' => $this->fieldStatuses($facility, $criteria),
        ];
    }

    /** @return array{status:string, label:string, verified_at:?string, source:?string} */
    public function trustSummary(Facility $facility, ?bool $reviewDocumented = null): array
    {
        $reviewDocumented ??= $this->evaluate($facility)['review_documented'];
        $hasContact = filled($facility->phone) || filled($facility->email) || filled($facility->website);
        $status = $reviewDocumented
            ? 'verified'
            : ($facility->contact_status === 'verified' && $hasContact ? 'partial' : 'unverified');

        return [
            'status' => $status,
            'label' => match ($status) {
                'verified' => 'Kontaktdaten geprüft',
                'partial' => 'Kontaktdaten teilweise geprüft',
                default => 'Kontaktdaten noch nicht vollständig geprüft',
            },
            'verified_at' => $facility->contact_checked_at?->format('d.m.Y'),
            'source' => $status !== 'unverified' && HttpUrl::isValid($facility->contact_source)
                ? (string) $facility->contact_source
                : null,
        ];
    }

    /** @param array<string, bool> $criteria @return array<string, array{key:string, label:string, status:string, display_text:string, accessible_label:string}> */
    public function fieldStatuses(Facility $facility, ?array $criteria = null): array
    {
        $criteria ??= [
            'phone' => filled($facility->phone),
            'email' => filled($facility->email),
            'website' => filled($facility->website),
            'address' => $this->addressIsConfirmed($facility),
            'source' => filled($facility->contact_source),
            'verified' => $facility->contact_status === 'verified',
        ];

        $fields = [
            'phone' => ['label' => 'Telefon', 'official_absent' => false],
            'email' => ['label' => 'E-Mail', 'official_absent' => (bool) $facility->official_email_absent],
            'website' => ['label' => 'Website', 'official_absent' => (bool) $facility->official_website_absent],
            'address' => ['label' => 'Adresse', 'official_absent' => false],
            'source' => ['label' => 'Quelle', 'official_absent' => false],
            'verified' => ['label' => 'Verifizierung', 'official_absent' => false],
        ];

        $statuses = [];
        foreach ($fields as $key => $field) {
            $status = $criteria[$key] ? 'present' : ($field['official_absent'] ? 'officially_absent' : 'missing');
            $displayText = match ($status) {
                'present' => $field['label'],
                'officially_absent' => $key === 'email'
                    ? 'Keine öffentliche E-Mail vorhanden'
                    : 'Keine offizielle Website vorhanden',
                default => $field['label'],
            };
            $accessibleLabel = match ($status) {
                'present' => $field['label'].' vorhanden',
                'officially_absent' => $displayText,
                default => $field['label'].' fehlt oder ist noch nicht bestätigt',
            };

            $statuses[$key] = [
                'key' => $key,
                'label' => $field['label'],
                'status' => $status,
                'display_text' => $displayText,
                'accessible_label' => $accessibleLabel,
            ];
        }

        return $statuses;
    }

    /** @param iterable<int, Facility> $facilities @return array{average_score:float, verified_percentage:float, verified_count:int, unverified_count:int, total_facilities:int} */
    public function aggregate(iterable $facilities): array
    {
        $items = $facilities instanceof Collection ? $facilities->values() : collect($facilities)->values();
        $total = $items->count();
        $scores = $items->map(fn (Facility $facility): array => $this->evaluate($facility));
        $verified = $scores->where('verified', true)->count();

        return $this->aggregateResult($scores, $total, $verified, $items->pluck('type')->filter()->unique()->count());
    }

    /** Calculate city statistics in chunks without loading every model at once. */
    public function aggregateQuery(Builder $query): array
    {
        $scores = collect();
        $total = 0;
        $verified = 0;
        $types = collect();
        $query->select(['id', 'city_id', 'type', 'address', 'postal_code', 'phone', 'email', 'website', 'contact_source', 'contact_status', 'contact_checked_at'])
            ->chunkById(500, function (Collection $facilities) use (&$scores, &$total, &$verified, &$types): void {
                foreach ($facilities as $facility) {
                    if (filled($facility->type)) {
                        $types->push($facility->type);
                    }
                    $score = $this->evaluate($facility);
                    $scores->push($score);
                    $total++;
                    $verified += $score['verified'] ? 1 : 0;
                }
            });

        return $this->aggregateResult($scores, $total, $verified, $types->unique()->count());
    }

    public static function cityCacheKey(int $cityId): string
    {
        return 'city-data-quality:'.$cityId;
    }

    /** @param Collection<int, array{score:int, verified:bool}> $scores */
    private function aggregateResult(Collection $scores, int $total, int $verified, int $typeCount = 0): array
    {
        $average = $total > 0 ? round($scores->avg('score'), 2) : 0.0;
        $quality = $this->qualityForScore((int) round($average));

        return [
            'average_score' => $average,
            'verified_percentage' => $total > 0 ? round(($verified / $total) * 100, 2) : 0.0,
            'verified_count' => $verified,
            'unverified_count' => $total - $verified,
            'total_facilities' => $total,
            'type_count' => $typeCount,
            ...$quality,
        ];
    }

    private function addressIsConfirmed(Facility $facility): bool
    {
        return $facility->contact_status === 'verified'
            && filled($facility->address)
            && filled($facility->postal_code)
            && $facility->city_id !== null;
    }

    public function qualityForScore(int $score): array
    {
        if ($score >= 90) {
            return ['quality_label' => 'Sehr hoch', 'quality_color' => 'green', 'progress_percentage' => max(0, min(100, $score))];
        }
        if ($score >= 75) {
            return ['quality_label' => 'Hoch', 'quality_color' => 'teal', 'progress_percentage' => $score];
        }
        if ($score >= 50) {
            return ['quality_label' => 'Gut', 'quality_color' => 'amber', 'progress_percentage' => $score];
        }
        if ($score >= 25) {
            return ['quality_label' => 'Teilweise', 'quality_color' => 'orange', 'progress_percentage' => $score];
        }

        return [
            'quality_label' => 'Unvollständig',
            'quality_color' => 'red',
            'progress_percentage' => max(0, min(100, $score)),
        ];
    }
}

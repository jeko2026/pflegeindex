<?php

namespace App\Services\DataQuality;

use App\Models\City;
use App\Models\Facility;
use App\Models\GeoMunicipality;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ReviewPackPreparer
{
    private const MAJOR_CITIES = ['potsdam', 'cottbus', 'brandenburg-an-der-havel', 'frankfurt-oder', 'eberswalde', 'oranienburg', 'neuruppin'];

    public function __construct(
        private readonly FullDataAudit $auditor,
        private readonly DuplicateCandidateTriage $triage,
    ) {}

    /**
     * @return array{packs: array<int, array<string, mixed>>, index: array<int, array<string, mixed>>, summary: array<string, mixed>, duplicate_triage: array<int, array<string, mixed>>, priority_high: array<int, array<string, mixed>>, geography: array<int, array<string, mixed>>}
     */
    public function prepare(int $packSize = 30, ?string $citySlug = null): array
    {
        $audit = $this->auditor->run($citySlug);
        $facilities = Facility::query()->with(['city.geoMunicipality.district'])
            ->when($citySlug, fn ($query) => $query->whereHas('city', fn ($city) => $city->where('slug', $citySlug)))
            ->orderBy('id')->get();
        $groups = $this->triage->classifyGroups($facilities, $audit['duplicates']);
        $groupMap = $this->groupMap($groups);
        $issuesByFacility = collect($audit['issues'])->whereNotNull('facility_id')->groupBy('facility_id');
        $queueIds = collect($audit['verification_queue'])->pluck('facility_id')->map(fn ($id): int => (int) $id)->flip();

        $queue = $facilities->filter(fn (Facility $facility): bool => $queueIds->has($facility->id))
            ->map(fn (Facility $facility): array => $this->reviewRow($facility, $issuesByFacility->get($facility->id, collect()), $groupMap[$facility->id] ?? []))
            ->sortBy(['_sort_rank', '_city_sort', 'name', 'facility_id'])
            ->values()
            ->map(function (array $row, int $index): array {
                $row['queue_position'] = $index + 1;

                return $row;
            });

        $packs = $queue->chunk($packSize)->values()->map(function (Collection $rows, int $index): array {
            $cities = $rows->pluck('city')->filter()->unique()->values();
            $firstCitySlug = Str::slug((string) $cities->first()) ?: 'ohne-ort';
            $slug = $cities->count() === 1 ? $firstCitySlug : $firstCitySlug.'-und-weitere';
            $number = $index + 1;

            return [
                'pack_number' => $number,
                'filename' => sprintf('review-pack-%03d-%s.csv', $number, $slug ?: 'ohne-ort'),
                'city' => $cities->implode(' | '),
                'rows' => $rows->map(fn (array $row): array => $this->publicReviewRow($row))->all(),
                'stats' => $this->packStats($rows),
            ];
        })->all();

        $index = collect($packs)->map(fn (array $pack): array => [
            'pack_number' => $pack['pack_number'], 'filename' => $pack['filename'], 'city' => $pack['city'],
            'facilities_total' => $pack['stats']['facilities_total'], 'high_count' => $pack['stats']['high_count'],
            'medium_count' => $pack['stats']['medium_count'], 'low_count' => $pack['stats']['low_count'],
            'not_reviewed_count' => $pack['stats']['not_reviewed_count'], 'duplicate_candidates' => $pack['stats']['duplicate_candidates'],
            'reviewed_count' => 0, 'remaining_count' => $pack['stats']['facilities_total'], 'status' => 'open',
        ])->all();

        $priorityHigh = $queue->filter(fn (array $row): bool => $row['_effective_priority'] === 'high' || in_array($row['facility_id'], [676, 677, 892], true))
            ->map(fn (array $row): array => $this->publicReviewRow($row))->values()->all();
        $classificationCounts = collect(DuplicateCandidateTriage::CLASSIFICATIONS)
            ->mapWithKeys(fn (string $classification): array => [$classification => collect($groups)->where('classification', $classification)->count()])->all();

        return [
            'packs' => $packs,
            'index' => $index,
            'duplicate_triage' => $groups,
            'priority_high' => $priorityHigh,
            'geography' => $this->geographyRows($facilities),
            'summary' => [
                'generated_at' => now()->toIso8601String(),
                'duplicate_groups_before_triage' => count($audit['duplicates']),
                'duplicate_classifications' => $classificationCounts,
                'queue_records' => $queue->count(),
                'packs_total' => count($packs),
                'average_pack_size' => count($packs) > 0 ? round($queue->count() / count($packs), 1) : 0,
                'high_records_after_false_positive_removal' => count($priorityHigh),
                'not_reviewed_total' => $queue->where('current_verification_status', null)->count(),
                'unresolved_geography_total' => count($this->geographyRows($facilities)),
            ],
        ];
    }

    /** @param  array<int, array<string, mixed>>  $groups */
    private function groupMap(array $groups): array
    {
        $map = [];
        foreach ($groups as $group) {
            foreach (explode('|', (string) $group['facility_ids']) as $id) {
                if (ctype_digit($id)) {
                    $map[(int) $id][] = $group;
                }
            }
        }

        return $map;
    }

    /** @param  Collection<int, array<string, mixed>>  $issues @param  array<int, array<string, mixed>>  $groups */
    private function reviewRow(Facility $facility, Collection $issues, array $groups): array
    {
        $classifications = collect($groups)->pluck('classification')->unique()->values();
        $relatedIds = collect($groups)->flatMap(fn (array $group) => explode('|', (string) $group['facility_ids']))
            ->filter(fn (string $id): bool => ctype_digit($id) && (int) $id !== $facility->id)->unique()->sort()->values();
        $nonDuplicate = $issues->where('category', '!=', 'duplicate');
        $priority = $this->effectivePriority($facility, $nonDuplicate, $classifications);
        $main = $nonDuplicate->sortBy(fn (array $issue): int => $this->priorityRank((string) $issue['priority']))->first();
        $triageCodes = $classifications->map(fn (string $classification): string => 'DUPLICATE_TRIAGE_'.strtoupper($classification));
        $issueCodes = $nonDuplicate->pluck('issue_code')->merge($triageCodes)->unique()->values();
        $mainIssue = $main['issue'] ?? ($classifications->isNotEmpty() ? 'Duplicate-Triage: '.$classifications->implode(', ').'.' : 'Manuelle Datenprüfung erforderlich.');

        $notReviewed = blank($facility->contact_status);
        $noContacts = blank($facility->phone) && blank($facility->email) && blank($facility->website);
        $majorRank = array_search($facility->city?->slug, self::MAJOR_CITIES, true);
        $sortRank = match (true) {
            $priority === 'high' => 0,
            $notReviewed => 100,
            $majorRank !== false => 200 + $majorRank,
            $noContacts => 300,
            $priority === 'medium' => 400,
            default => 500,
        };

        return [
            '_sort_rank' => $sortRank, '_city_sort' => $facility->city?->name ?? '', '_effective_priority' => $priority,
            'queue_position' => 0, 'facility_id' => $facility->id, 'name' => $facility->name, 'type' => $facility->type,
            'city' => $facility->city?->name, 'address' => $facility->address, 'postal_code' => $facility->postal_code,
            'phone' => $facility->phone, 'email' => $facility->email, 'website' => $facility->website,
            'current_verification_status' => $facility->contact_status, 'current_provenance' => $facility->contact_source,
            'current_verified_at' => $facility->contact_checked_at?->toIso8601String(),
            'issue_codes' => $issueCodes->implode('|'), 'main_issue' => $mainIssue,
            'duplicate_classification' => $classifications->implode('|'), 'duplicate_related_ids' => $relatedIds->implode('|'),
            'recommended_search_query' => $this->searchQuery($facility),
            'official_source_url' => '', 'reviewed_name' => '', 'reviewed_type' => '', 'reviewed_address' => '',
            'reviewed_postal_code' => '', 'reviewed_phone' => '', 'reviewed_email' => '', 'reviewed_website' => '',
            'reviewed_source_url' => '', 'review_result' => '', 'review_notes' => '',
        ];
    }

    private function effectivePriority(Facility $facility, Collection $nonDuplicateIssues, Collection $classifications): string
    {
        if (in_array($facility->id, [676, 677, 892], true)
            || $nonDuplicateIssues->contains(fn (array $issue): bool => in_array($issue['priority'], ['critical', 'high'], true))
            || $classifications->contains(fn (string $classification): bool => in_array($classification, ['exact_duplicate', 'strong_duplicate_candidate'], true))) {
            return 'high';
        }
        if ($nonDuplicateIssues->contains(fn (array $issue): bool => $issue['priority'] === 'medium')
            || $classifications->contains(fn (string $classification): bool => in_array($classification, ['possible_duplicate', 'manual_review'], true))) {
            return 'medium';
        }

        return 'low';
    }

    private function searchQuery(Facility $facility): string
    {
        $parts = ['"'.trim($facility->name).'"', $facility->city?->name, 'offizielle Webseite'];
        if (blank($facility->phone) && blank($facility->email) && blank($facility->website)) {
            $parts[] = $facility->address;
        }

        return collect($parts)->filter()->implode(' ');
    }

    private function publicReviewRow(array $row): array
    {
        unset($row['_sort_rank'], $row['_city_sort'], $row['_effective_priority']);

        return $row;
    }

    /** @param  Collection<int, array<string, mixed>>  $rows */
    private function packStats(Collection $rows): array
    {
        return [
            'facilities_total' => $rows->count(),
            'high_count' => $rows->where('_effective_priority', 'high')->count(),
            'medium_count' => $rows->where('_effective_priority', 'medium')->count(),
            'low_count' => $rows->where('_effective_priority', 'low')->count(),
            'not_reviewed_count' => $rows->where('current_verification_status', null)->count(),
            'duplicate_candidates' => $rows->filter(fn (array $row): bool => filled($row['duplicate_classification']))->count(),
        ];
    }

    /** @param  Collection<int, Facility>  $facilities */
    private function geographyRows(Collection $facilities): array
    {
        $cities = City::query()->with(['geoMunicipality.district'])
            ->where(function ($query): void {
                $query->where('slug', 'hennickendorf')->orWhere('name', 'like', 'Reichenberg%');
            })->get();

        return $cities->flatMap(function (City $city) use ($facilities): Collection {
            $cityFacilities = $facilities->where('city_id', $city->id);
            $postcodes = $cityFacilities->pluck('postal_code')->filter()->unique();
            $candidates = GeoMunicipality::query()->with('district')->whereIn('postal_code_official', $postcodes)->get();
            $possible = $candidates->pluck('name')->unique()->implode(' | ');
            if ($possible === '' && str_contains($city->name, ',')) {
                $possible = trim(Str::after($city->name, ','));
            }
            $districts = $candidates->map(fn (GeoMunicipality $municipality) => $municipality->district?->display_name)->filter()->unique()->implode(' | ');
            $reason = $city->geoMunicipality
                ? 'Benannte Sonderprüfung; bestehende Zuordnung manuell bestätigen.'
                : 'Keine eindeutige Gemeinde-Zuordnung im aktuellen GeoCore.';

            return $cityFacilities->map(fn (Facility $facility): array => [
                'facility_id' => $facility->id, 'name' => $facility->name, 'current_city' => $city->name,
                'city_slug' => $city->slug, 'postal_code' => $facility->postal_code,
                'possible_gemeinde' => $possible, 'landkreis' => $districts,
                'unresolved_reason' => $reason, 'manual_decision' => '',
            ]);
        })->values()->all();
    }

    private function priorityRank(string $priority): int
    {
        return array_search($priority, ['critical', 'high', 'medium', 'low'], true) ?: 0;
    }
}

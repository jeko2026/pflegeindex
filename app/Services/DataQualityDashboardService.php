<?php

namespace App\Services;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Support\Collection;

final class DataQualityDashboardService
{
    public function __construct(private readonly QualityScoreService $qualityScores) {}

    /** @return array{overview:array<string,int|float>, open_tasks:list<array<string,mixed>>, next_task:?array<string,mixed>, cities:list<array<string,mixed>>, facilities:list<array<string,mixed>>, cities_filter:Collection<int,City>, filters:array<string,mixed>} */
    public function dashboard(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $cityNames = City::query()->pluck('name', 'id');
        $cities = [];
        $rows = [];
        $overview = ['total_facilities' => 0, 'verified_count' => 0, 'unverified_count' => 0, 'verified_percentage' => 0.0, 'average_score' => 0.0, 'without_phone' => 0, 'without_email' => 0, 'without_website' => 0];

        Facility::query()
            ->select(['id', 'city_id', 'name', 'phone', 'email', 'website', 'address', 'postal_code', 'contact_source', 'contact_status', 'contact_checked_at'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $facilities) use (&$cities, &$rows, &$overview, $cityNames, $filters): void {
                foreach ($facilities as $facility) {
                    $score = $this->qualityScores->evaluate($facility);
                    $criteria = $score['criteria'];
                    $overview['total_facilities']++;
                    $overview['verified_count'] += $score['verified'] ? 1 : 0;
                    $overview['without_phone'] += $criteria['phone'] ? 0 : 1;
                    $overview['without_email'] += $criteria['email'] ? 0 : 1;
                    $overview['without_website'] += $criteria['website'] ? 0 : 1;
                    $cityId = (int) $facility->city_id;
                    $cities[$cityId] ??= ['city_id' => $cityId, 'name' => (string) ($cityNames[$cityId] ?? ''), 'total_facilities' => 0, 'verified_count' => 0, 'score_total' => 0];
                    $cities[$cityId]['total_facilities']++;
                    $cities[$cityId]['verified_count'] += $score['verified'] ? 1 : 0;
                    $cities[$cityId]['score_total'] += $score['score'];

                    if ($this->matches($facility, $score, $filters)) {
                        $rows[] = [
                            'id' => $facility->id,
                            'name' => $facility->name,
                            'city_name' => $cityNames[$cityId] ?? '',
                            'score' => $score['score'],
                            'status' => $facility->contact_status,
                            'status_label' => match ($facility->contact_status) {
                                'verified' => 'Geprüft',
                                'pending' => 'In Prüfung',
                                'not_found' => 'Nicht gefunden',
                                default => 'Noch offen',
                            },
                            'missing' => $this->missingLabels($criteria),
                        ];
                    }
                }
            });

        $overview['unverified_count'] = $overview['total_facilities'] - $overview['verified_count'];
        $overview['verified_percentage'] = $overview['total_facilities'] > 0 ? round(($overview['verified_count'] / $overview['total_facilities']) * 100, 2) : 0.0;
        $overview['average_score'] = $overview['total_facilities'] > 0 ? round(collect($cities)->sum('score_total') / $overview['total_facilities'], 2) : 0.0;
        $overview += $this->qualityScores->qualityForScore((int) round($overview['average_score']));

        $cityRows = collect($cities)->map(function (array $city): array {
            $city['average_score'] = round($city['score_total'] / $city['total_facilities'], 2);
            $city['verified_count'] = (int) $city['verified_count'];
            $city['unverified_count'] = $city['total_facilities'] - $city['verified_count'];
            $city['verified_percentage'] = round(($city['verified_count'] / $city['total_facilities']) * 100, 2);
            $city['priority'] = $city['verified_percentage'] < 25
                ? 'Hoch'
                : ($city['verified_percentage'] < 60 ? 'Mittel' : 'Niedrig');
            return $city;
        })->sortBy([['verified_percentage', 'asc'], ['unverified_count', 'desc'], ['name', 'asc']])->take(20)->values()->all();
        usort($rows, fn (array $a, array $b): int => [$a['score'], mb_strtolower($a['name']), $a['id']] <=> [$b['score'], mb_strtolower($b['name']), $b['id']]);

        $openTasks = [
            ['key' => 'phone', 'label' => 'Telefon prüfen', 'action' => 'Telefon-Prüfung starten', 'count' => $overview['without_phone'], 'description' => 'Einrichtungen ohne Telefonnummer'],
            ['key' => 'email', 'label' => 'E-Mail prüfen', 'action' => 'E-Mail-Prüfung starten', 'count' => $overview['without_email'], 'description' => 'Einrichtungen ohne E-Mail-Adresse'],
            ['key' => 'website', 'label' => 'Website prüfen', 'action' => 'Website-Prüfung starten', 'count' => $overview['without_website'], 'description' => 'Einrichtungen ohne Website'],
        ];
        $nextTask = collect($openTasks)->sortByDesc('count')->first();
        if (($nextTask['count'] ?? 0) === 0) {
            $nextTask = null;
        }

        return [
            'overview' => $overview,
            'open_tasks' => $openTasks,
            'next_task' => $nextTask,
            'cities' => $cityRows,
            'facilities' => array_slice($rows, 0, 50),
            'cities_filter' => $cityNames->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->sortBy('name')->values(),
            'filters' => $filters,
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        $status = in_array($filters['status'] ?? '', ['verified', 'unverified', 'pending', 'not_found', ''], true) ? ($filters['status'] ?? '') : '';
        $city = is_numeric($filters['city'] ?? null) ? (int) $filters['city'] : null;
        $max = is_numeric($filters['max_score'] ?? null) ? max(0, min(100, (int) $filters['max_score'])) : null;
        return ['city' => $city, 'status' => $status, 'missing_phone' => ($filters['missing_phone'] ?? '') === '1', 'missing_email' => ($filters['missing_email'] ?? '') === '1', 'missing_website' => ($filters['missing_website'] ?? '') === '1', 'max_score' => $max];
    }

    private function matches(Facility $facility, array $score, array $filters): bool
    {
        return ($filters['city'] === null || (int) $facility->city_id === $filters['city'])
            && ($filters['status'] === '' || $facility->contact_status === $filters['status'])
            && (! $filters['missing_phone'] || ! $score['criteria']['phone'])
            && (! $filters['missing_email'] || ! $score['criteria']['email'])
            && (! $filters['missing_website'] || ! $score['criteria']['website'])
            && ($filters['max_score'] === null || $score['score'] <= $filters['max_score']);
    }

    private function missingLabels(array $criteria): array
    {
        return array_values(array_filter(['phone' => 'Telefon', 'email' => 'E-Mail', 'website' => 'Website', 'source' => 'Quelle', 'address' => 'Adressprüfung', 'verified' => 'Verifizierung'], fn (string $key): bool => ! $criteria[$key], ARRAY_FILTER_USE_KEY));
    }
}

<?php

namespace App\Services;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Support\Collection;

final class DataQualityDashboardService
{
    public function __construct(private readonly QualityScoreService $qualityScores) {}

    /** @return array{overview:array<string,int|float>, open_tasks:list<array<string,mixed>>, next_task:?array<string,mixed>, cities:list<array<string,mixed>>, facilities:list<array<string,mixed>>, filtered_count:int, displayed_count:int, active_city_name:?string, cities_filter:Collection<int,City>, filters:array<string,mixed>} */
    public function dashboard(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $cityNames = City::query()->pluck('name', 'id');
        $cities = [];
        $rows = [];
        $overview = ['total_facilities' => 0, 'verified_count' => 0, 'unverified_count' => 0, 'verified_percentage' => 0.0, 'average_score' => 0.0, 'without_phone' => 0, 'without_email' => 0, 'without_website' => 0];

        Facility::query()
            ->select(['id', 'city_id', 'name', 'phone', 'email', 'official_email_absent', 'website', 'official_website_absent', 'address', 'postal_code', 'contact_source', 'contact_status', 'contact_checked_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $facilities) use (&$cities, &$rows, &$overview, $cityNames, $filters): void {
                foreach ($facilities as $facility) {
                    $score = $this->qualityScores->evaluate($facility);
                    $criteria = $score['criteria'];
                    $isOpen = $facility->contactReviewIsOpen();
                    $overview['total_facilities']++;
                    $overview['verified_count'] += $score['review_documented'] ? 1 : 0;
                    $overview['unverified_count'] += $isOpen ? 1 : 0;
                    $overview['without_phone'] += $criteria['phone'] ? 0 : 1;
                    $overview['without_email'] += $facility->emailReviewIsOpen() ? 1 : 0;
                    $overview['without_website'] += $facility->websiteReviewIsOpen() ? 1 : 0;
                    $cityId = (int) $facility->city_id;
                    $cities[$cityId] ??= ['city_id' => $cityId, 'name' => (string) ($cityNames[$cityId] ?? ''), 'total_facilities' => 0, 'verified_count' => 0, 'unverified_count' => 0, 'score_total' => 0];
                    $cities[$cityId]['total_facilities']++;
                    $cities[$cityId]['verified_count'] += $score['review_documented'] ? 1 : 0;
                    $cities[$cityId]['unverified_count'] += $isOpen ? 1 : 0;
                    $cities[$cityId]['score_total'] += $score['score'];

                    if ($this->matches($facility, $score, $filters)) {
                        $rows[] = [
                            'id' => $facility->id,
                            'name' => $facility->name,
                            'city_name' => $cityNames[$cityId] ?? '',
                            'score' => $score['score'],
                            'updated_at' => $facility->updated_at?->getTimestamp() ?? 0,
                            'status' => $facility->contact_status,
                            'status_label' => match ($facility->contact_status) {
                                'verified' => 'Geprüft',
                                'pending' => 'In Prüfung',
                                'not_found' => 'Nicht gefunden',
                                default => 'Noch offen',
                            },
                            'missing' => $this->missingLabels($score['field_statuses']),
                        ];
                    }
                }
            });

        $overview['verified_percentage'] = $overview['total_facilities'] > 0 ? round(($overview['verified_count'] / $overview['total_facilities']) * 100, 2) : 0.0;
        $overview['average_score'] = $overview['total_facilities'] > 0 ? round(collect($cities)->sum('score_total') / $overview['total_facilities'], 2) : 0.0;
        $overview += $this->qualityScores->qualityForScore((int) round($overview['average_score']));

        $cityRows = collect($cities)->map(function (array $city): array {
            $city['average_score'] = round($city['score_total'] / $city['total_facilities'], 2);
            $city['verified_count'] = (int) $city['verified_count'];
            $city['unverified_count'] = (int) $city['unverified_count'];
            $city['verified_percentage'] = round(($city['verified_count'] / $city['total_facilities']) * 100, 2);
            $city['priority'] = $city['verified_percentage'] < 25
                ? 'Hoch'
                : ($city['verified_percentage'] < 60 ? 'Mittel' : 'Niedrig');

            return $city;
        })->sortBy([['verified_percentage', 'asc'], ['unverified_count', 'desc'], ['name', 'asc']])->take(20)->values()->all();
        $this->sortRows($rows, $filters);
        $filteredCount = count($rows);
        $displayedFacilities = array_slice($rows, 0, 50);

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
            'facilities' => $displayedFacilities,
            'filtered_count' => $filteredCount,
            'displayed_count' => count($displayedFacilities),
            'active_city_name' => $filters['city'] !== null ? ($cityNames[$filters['city']] ?? null) : null,
            'cities_filter' => $cityNames->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->sortBy('name')->values(),
            'filters' => $filters,
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        $status = in_array($filters['status'] ?? '', ['verified', 'unverified', 'pending', 'not_found', ''], true) ? ($filters['status'] ?? '') : '';
        $city = is_numeric($filters['city'] ?? null) ? (int) $filters['city'] : null;
        $max = is_numeric($filters['max_score'] ?? null) ? max(0, min(100, (int) $filters['max_score'])) : null;
        [$sort, $direction] = match ($filters['sort'] ?? '') {
            'quality_score_desc' => ['quality_score', 'desc'],
            'city_desc' => ['city', 'desc'],
            'updated_at' => ['updated_at', 'desc'],
            default => [
                in_array($filters['sort'] ?? '', ['quality_score', 'city'], true) ? $filters['sort'] : 'quality_score',
                in_array($filters['direction'] ?? '', ['asc', 'desc'], true) ? $filters['direction'] : 'asc',
            ],
        };
        $task = in_array($filters['task'] ?? '', ['open', 'missing_phone', 'missing_email', 'missing_website'], true)
            ? $filters['task']
            : $this->legacyTask($filters);

        return ['task' => $task, 'city' => $city, 'status' => $status, 'max_score' => $max, 'sort' => $sort, 'direction' => $direction];
    }

    private function legacyTask(array $filters): string
    {
        foreach (['missing_phone', 'missing_email', 'missing_website'] as $legacyTask) {
            if (($filters[$legacyTask] ?? '') === '1') {
                return $legacyTask;
            }
        }

        return 'open';
    }

    /** @param list<array<string,mixed>> $rows */
    private function sortRows(array &$rows, array $filters): void
    {
        $direction = $filters['direction'] === 'desc' ? -1 : 1;

        usort($rows, function (array $left, array $right) use ($filters, $direction): int {
            $primary = match ($filters['sort']) {
                'city' => mb_strtolower((string) $left['city_name']) <=> mb_strtolower((string) $right['city_name']),
                'updated_at' => $left['updated_at'] <=> $right['updated_at'],
                default => $left['score'] <=> $right['score'],
            };

            if ($primary !== 0) {
                return $primary * $direction;
            }

            return [mb_strtolower($left['name']), $left['id']] <=> [mb_strtolower($right['name']), $right['id']];
        });
    }

    private function matches(Facility $facility, array $score, array $filters): bool
    {
        $isOpen = $facility->contactReviewIsOpen();
        $matchesTask = match ($filters['task']) {
            'missing_phone' => ! $score['criteria']['phone'],
            'missing_email' => $facility->emailReviewIsOpen(),
            'missing_website' => $facility->websiteReviewIsOpen(),
            default => $isOpen,
        };
        $matchesStatus = match ($filters['status']) {
            'unverified' => $isOpen,
            '' => true,
            default => $facility->contact_status === $filters['status'],
        };

        return $matchesTask
            && $matchesStatus
            && ($filters['city'] === null || (int) $facility->city_id === $filters['city'])
            && ($filters['max_score'] === null || $score['score'] <= $filters['max_score']);
    }

    /** @param array<string, array{status:string}> $fieldStatuses */
    private function missingLabels(array $fieldStatuses): array
    {
        $labels = ['phone' => 'Telefon', 'email' => 'E-Mail', 'website' => 'Website', 'source' => 'Quelle', 'address' => 'Adressprüfung', 'verified' => 'Verifizierung'];

        return collect($labels)
            ->filter(fn (string $label, string $key): bool => $fieldStatuses[$key]['status'] === 'missing')
            ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }
}

<?php

namespace App\Services\DataQuality;

use App\Models\Facility;
use App\Services\QualityScoreService;
use App\Support\HttpUrl;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ManualContactReviewImportService
{
    private const CONTACT_FIELDS = ['phone', 'email', 'website'];

    private const SHEET_ONE_HEADERS = [
        'Назва закладу', 'Місто', 'Веб сайт Офіційний', 'Номер телефону', 'Email', 'URL-адреса(и) джерела',
    ];

    private const SHEET_TWO_HEADERS = [
        'facility_id', 'current_name', 'current_city', 'current_phone(+49..)', 'current_email',
        'current_website(https://)', 'data source(url)',
    ];

    /** @return array{rows:list<array<string,mixed>>, files:list<array<string,mixed>>} */
    public function read(string $sheetOne, string $sheetTwo): array
    {
        $one = $this->readCsv($sheetOne, ',');
        $two = $this->readCsv($sheetTwo, ';');
        $this->requireHeaders($one['headers'], self::SHEET_ONE_HEADERS, 'sheet 1');
        $this->requireHeaders($two['headers'], self::SHEET_TWO_HEADERS, 'sheet 2');

        $rows = [];
        foreach ($one['rows'] as $index => $row) {
            $rows[] = [
                'sheet' => 'sheet1', 'line' => $index + 2, 'facility_id' => null,
                'name' => $row['Назва закладу'] ?? '', 'city' => $row['Місто'] ?? '',
                'address' => trim(($row['Вулиця'] ?? '').' '.($row['Номер буд'] ?? '')),
                'postal_code' => $row['Поштовий індекс'] ?? '', 'phone' => $row['Номер телефону'] ?? '',
                'email' => $row['Email'] ?? '', 'website' => $row['Веб сайт Офіційний'] ?? '',
                'source' => $row['URL-адреса(и) джерела'] ?? '', 'notes' => $row['Примітки'] ?? '',
            ];
        }
        foreach ($two['rows'] as $index => $row) {
            $rows[] = [
                'sheet' => 'sheet2', 'line' => $index + 2, 'facility_id' => $row['facility_id'] ?? '',
                'name' => $row['current_name'] ?? '', 'city' => $row['current_city'] ?? '',
                'address' => $row['current_address'] ?? '', 'postal_code' => $row['current_postal_code'] ?? '',
                'phone' => $row['current_phone(+49..)'] ?? '', 'email' => $row['current_email'] ?? '',
                'website' => $row['current_website(https://)'] ?? '', 'source' => $row['data source(url)'] ?? '',
                'notes' => trim(($row['Notes'] ?? '').' '.($row['Column1'] ?? '')),
            ];
        }

        return [
            'rows' => $rows,
            'files' => [
                $this->fileSummary($sheetOne, ',', $one['headers'], count($one['rows']), 'normalized name + city', ['phone', 'email', 'website', 'contact source']),
                $this->fileSummary($sheetTwo, ';', $two['headers'], count($two['rows']), 'facility_id', ['phone', 'email', 'website', 'contact source']),
            ],
        ];
    }

    /** @param array{rows:list<array<string,mixed>>,files:list<array<string,mixed>>} $payload @return array<string,mixed> */
    public function plan(array $payload): array
    {
        $facilities = Facility::query()->with('city')->orderBy('id')->get();
        $byId = $facilities->keyBy('id');
        $byNameCity = $facilities->groupBy(fn (Facility $facility): string => $this->identity($facility->name, (string) $facility->city?->name));
        $duplicateKeys = collect($payload['rows'])->groupBy(fn (array $row): string => $row['sheet'] === 'sheet2'
            ? 'id:'.trim((string) $row['facility_id'])
            : 'name-city:'.$this->identity((string) $row['name'], (string) $row['city']))
            ->filter(fn ($rows): bool => $rows->count() > 1)->keys()->flip();

        $resolved = [];
        foreach ($payload['rows'] as $index => $row) {
            $key = $row['sheet'] === 'sheet2'
                ? 'id:'.trim((string) $row['facility_id'])
                : 'name-city:'.$this->identity((string) $row['name'], (string) $row['city']);
            $row['_duplicate_key'] = $duplicateKeys->has($key);
            if ($row['sheet'] === 'sheet2') {
                $id = filter_var($row['facility_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $row['_facility'] = $id === false ? null : $byId->get((int) $id);
                $row['_invalid_id'] = $id === false;
                $row['_ambiguous'] = false;
            } else {
                $matches = $byNameCity->get($this->identity((string) $row['name'], (string) $row['city']), collect());
                $row['_facility'] = $matches->count() === 1 ? $matches->first() : null;
                $row['_invalid_id'] = false;
                $row['_ambiguous'] = $matches->count() > 1;
            }
            $resolved[$index] = $row;
        }

        $duplicateFacilityIds = collect($resolved)->filter(fn (array $row): bool => $row['_facility'] instanceof Facility)
            ->groupBy(fn (array $row): int => $row['_facility']->id)
            ->filter(fn ($rows): bool => $rows->count() > 1)->keys()->mapWithKeys(fn ($id): array => [(int) $id => true]);

        $changes = [];
        $issues = [];
        $rowResults = [];
        $fieldUpdates = array_fill_keys([...self::CONTACT_FIELDS, 'contact_source'], 0);
        $summary = ['total_rows' => count($resolved), 'matched' => 0, 'unchanged' => 0, 'to_update' => 0, 'skipped' => 0, 'conflicts' => 0, 'not_found' => 0, 'invalid' => 0];

        foreach ($resolved as $row) {
            $facility = $row['_facility'];
            if ($row['_invalid_id']) {
                $this->reject($rowResults, $issues, $summary, $row, 'invalid', 'invalid_facility_id', 'facility_id must be a positive integer.');

                continue;
            }
            if ($row['_ambiguous']) {
                $this->reject($rowResults, $issues, $summary, $row, 'conflict', 'ambiguous_name_city', 'Name and city match more than one facility.');

                continue;
            }
            if (! $facility instanceof Facility) {
                $this->reject($rowResults, $issues, $summary, $row, 'not_found', 'facility_not_found', 'No facility matched this review row.');

                continue;
            }
            $summary['matched']++;
            if ($row['_duplicate_key'] || $duplicateFacilityIds->has($facility->id)) {
                $this->reject($rowResults, $issues, $summary, $row, 'conflict', 'duplicate_input_facility', 'The same facility occurs more than once in the review files.');

                continue;
            }
            if ($this->containsNeedsReview((string) $row['notes'])) {
                $this->reject($rowResults, $issues, $summary, $row, 'conflict', 'needs_review', 'The reviewer explicitly marked this row as needing review.');

                continue;
            }

            $warnings = [];
            if ($this->identity((string) $row['name'], (string) $row['city']) !== $this->identity($facility->name, (string) $facility->city?->name)) {
                $warnings[] = 'The facility ID matches, but the name/city snapshot differs; identity fields were not changed.';
            }
            $source = $this->value((string) $row['source']);
            if ($source !== null && (! HttpUrl::isValid($source) || $this->isDiscoverySource($source))) {
                $this->reject($rowResults, $issues, $summary, $row, 'invalid', 'invalid_source_url', 'The source is not one acceptable primary HTTP(S) URL.', $facility);

                continue;
            }

            $proposed = [];
            $invalid = null;
            foreach (self::CONTACT_FIELDS as $field) {
                $value = $this->value((string) $row[$field]);
                if ($value === null) {
                    continue;
                }
                if ($field === 'phone') {
                    $value = $this->normalizePhone($value);
                    if (FacilityDataAuditor::auditPhone($value) !== null) {
                        $invalid = ['invalid_phone', 'The reviewed phone is suspicious or too short.'];
                        break;
                    }
                }
                if ($field === 'email' && FacilityDataAuditor::auditEmail($value) !== null) {
                    $invalid = ['invalid_email', 'The reviewed email is invalid or a placeholder.'];
                    break;
                }
                if ($field === 'website' && ! HttpUrl::isValid($value)) {
                    $invalid = ['invalid_website', 'The reviewed website is not an absolute public HTTP(S) URL.'];
                    break;
                }
                $proposed[$field] = $value;
            }
            if ($invalid !== null) {
                $this->reject($rowResults, $issues, $summary, $row, 'invalid', $invalid[0], $invalid[1], $facility);

                continue;
            }

            $rowChanges = [];
            foreach ($proposed as $field => $newValue) {
                if ($this->equivalent($field, $facility->{$field}, $newValue)) {
                    continue;
                }
                $rowChanges[] = $this->change($facility, $row, $field, $facility->{$field}, $newValue);
            }
            if ($source !== null && (string) $facility->contact_source !== $source) {
                $rowChanges[] = $this->change($facility, $row, 'contact_source', $facility->contact_source, $source);
            }
            if ($rowChanges !== [] && $source === null && ! HttpUrl::isValid((string) $facility->contact_source)) {
                $this->reject($rowResults, $issues, $summary, $row, 'conflict', 'changed_contact_without_source', 'Contact changes require an exact primary source URL.', $facility);

                continue;
            }
            if ($rowChanges !== [] && $facility->contact_locked) {
                $this->reject($rowResults, $issues, $summary, $row, 'conflict', 'contact_locked', 'Protected manual contact data cannot be overwritten.', $facility);

                continue;
            }
            if ($rowChanges === []) {
                $summary['unchanged']++;
                $rowResults[] = $this->rowResult($row, $facility, 'unchanged', 'No meaningful changes.', $warnings);

                continue;
            }

            $summary['to_update']++;
            foreach ($rowChanges as $change) {
                $changes[] = $change;
                $fieldUpdates[$change['field']]++;
            }
            $rowResults[] = $this->rowResult($row, $facility, 'update', count($rowChanges).' field change(s).', $warnings);
        }
        $summary['skipped'] = $summary['conflicts'] + $summary['not_found'] + $summary['invalid'];

        return [
            'files' => $payload['files'], 'summary' => $summary, 'field_updates' => $fieldUpdates,
            'changes' => $changes, 'issues' => $issues, 'rows' => $rowResults,
        ];
    }

    /** @param array<string,mixed> $plan @return array{facilities:int,fields:int} */
    public function apply(array $plan): array
    {
        $facilityCount = 0;
        $fieldCount = 0;
        DB::transaction(function () use ($plan, &$facilityCount, &$fieldCount): void {
            foreach (collect($plan['changes'])->groupBy('facility_id') as $facilityId => $changes) {
                $facility = Facility::query()->lockForUpdate()->find((int) $facilityId);
                if (! $facility instanceof Facility) {
                    throw new RuntimeException('Facility '.$facilityId.' disappeared before apply.');
                }
                foreach ($changes as $change) {
                    $field = $change['field'];
                    if ((string) ($facility->{$field} ?? '') !== (string) ($change['old_value'] ?? '')) {
                        throw new RuntimeException('Concurrent change for facility '.$facilityId.' field '.$field.'.');
                    }
                    $facility->{$field} = $change['new_value'];
                }
                if ($facility->isDirty()) {
                    $facility->save();
                    $facilityCount++;
                    $fieldCount += $changes->count();
                }
            }
        });

        return ['facilities' => $facilityCount, 'fields' => $fieldCount];
    }

    /** @return array<string,int|float> */
    public function qualitySnapshot(): array
    {
        $facilities = Facility::query()->get();
        $scores = $facilities->map(fn (Facility $facility): array => app(QualityScoreService::class)->evaluate($facility));
        $total = $facilities->count();

        return [
            'facilities_total' => $total,
            'phone_coverage' => $this->percentage($facilities->whereNotNull('phone')->filter(fn (Facility $f): bool => trim((string) $f->phone) !== '')->count(), $total),
            'email_coverage' => $this->percentage($facilities->whereNotNull('email')->filter(fn (Facility $f): bool => trim((string) $f->email) !== '')->count(), $total),
            'website_coverage' => $this->percentage($facilities->whereNotNull('website')->filter(fn (Facility $f): bool => trim((string) $f->website) !== '')->count(), $total),
            'verified_contacts' => $facilities->where('contact_status', 'verified')->count(),
            'average_quality_score' => round((float) $scores->avg('score'), 2),
            'missing_phone_queue' => $facilities->filter(fn (Facility $f): bool => blank($f->phone))->count(),
            'missing_email_queue' => $facilities->filter(fn (Facility $f): bool => blank($f->email))->count(),
            'missing_website_queue' => $facilities->filter(fn (Facility $f): bool => blank($f->website))->count(),
        ];
    }

    /** @return array<string,int|string> */
    public function integritySnapshot(): array
    {
        return [
            'facilities_total' => Facility::query()->count(),
            'cities_total' => DB::table('cities')->count(),
            'geocore_mapping_count' => DB::table('cities')->whereNotNull('geo_municipality_id')->count(),
            'contact_suggestions' => DB::table('contact_suggestions')->count(),
            'duplicate_signals' => DB::table('facilities')->select('name', 'city_id')->groupBy('name', 'city_id')->havingRaw('COUNT(*) > 1')->get()->count(),
            'orphan_records' => DB::table('facilities')->leftJoin('cities', 'cities.id', '=', 'facilities.city_id')->whereNull('cities.id')->count(),
            'invalid_foreign_keys' => count(DB::select('PRAGMA foreign_key_check')),
            'source_id_duplicates' => DB::table('facilities')->select('source_id')->groupBy('source_id')->havingRaw('COUNT(*) > 1')->get()->count(),
            'sqlite_integrity' => (string) (DB::selectOne('PRAGMA integrity_check')->integrity_check ?? 'unknown'),
        ];
    }

    /** @return array{headers:list<string>,rows:list<array<string,string>>} */
    private function readCsv(string $path, string $delimiter): array
    {
        $resolved = realpath($path);
        if ($resolved === false || ! is_readable($resolved)) {
            throw new RuntimeException('CSV is not readable: '.$path);
        }
        $handle = fopen($resolved, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV: '.$path);
        }
        try {
            $headers = fgetcsv($handle, 0, $delimiter);
            if (! is_array($headers)) {
                throw new RuntimeException('CSV header is missing: '.$path);
            }
            $headers = array_map(fn ($value): string => $this->clean((string) $value), $headers);
            $rows = [];
            while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($values === [null] || $values === []) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new RuntimeException('CSV row has a different column count in '.$path.'.');
                }
                $rows[] = array_combine($headers, array_map(fn ($value): string => $this->clean((string) $value), $values));
            }
        } finally {
            fclose($handle);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @param list<string> $headers @param list<string> $required */
    private function requireHeaders(array $headers, array $required, string $label): void
    {
        $missing = array_values(array_diff($required, $headers));
        if ($missing !== []) {
            throw new RuntimeException($label.' is missing headers: '.implode(', ', $missing));
        }
    }

    /** @param list<string> $headers @param list<string> $reviewedFields @return array<string,mixed> */
    private function fileSummary(string $path, string $delimiter, array $headers, int $rows, string $key, array $reviewedFields): array
    {
        return [
            'filename' => basename($path), 'path' => realpath($path), 'format' => 'UTF-8 CSV (delimiter '.($delimiter === ',' ? 'comma' : 'semicolon').')',
            'rows' => $rows, 'columns' => $headers, 'key_field' => $key, 'reviewed_fields' => $reviewedFields,
            'size' => filesize($path), 'sha256' => hash_file('sha256', $path),
        ];
    }

    private function clean(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{FEFF}]/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function value(string $value): ?string
    {
        $value = $this->clean($value);
        if ($value === '' || preg_match('/^(?:NO_[A-Z_]+|NEEDS_REVIEW|NULL|N\/?A|-)$/i', $value)) {
            return null;
        }

        return $value;
    }

    private function identity(string $name, string $city): string
    {
        return $this->identityPart($name).'|'.$this->identityPart($city);
    }

    private function identityPart(string $value): string
    {
        $value = mb_strtolower($this->clean($value));
        $value = str_replace(['„', '“', '”', '’', '´'], ['"', '"', '"', "'", "'"], $value);

        return trim(preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value);
    }

    private function containsNeedsReview(string $notes): bool
    {
        return preg_match('/needs_review/i', $notes) === 1;
    }

    private function isDiscoverySource(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return collect(['google.', 'bing.', 'gelbeseiten.', '11880.', 'bkk-pflegefinder.', 'facebook.', 'instagram.'])
            ->contains(fn (string $fragment): bool => str_contains($host, $fragment));
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/^tel:\s*/i', '', $this->clean($phone)) ?? $phone;
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0049')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '49'.substr($digits, 1);
        }
        if (str_starts_with($digits, '49')) {
            return '+49 '.trim(chunk_split(substr($digits, 2), 3, ' '));
        }

        return $phone;
    }

    private function equivalent(string $field, mixed $old, string $new): bool
    {
        $old = $this->clean((string) ($old ?? ''));
        if ($field === 'phone') {
            return preg_replace('/\D+/', '', $old) === preg_replace('/\D+/', '', $new);
        }
        if ($field === 'email') {
            return mb_strtolower($old) === mb_strtolower($new);
        }
        if ($field === 'website') {
            return rtrim($old, '/') === rtrim($new, '/');
        }

        return $old === $new;
    }

    /** @return array<string,mixed> */
    private function change(Facility $facility, array $row, string $field, mixed $old, mixed $new): array
    {
        return ['facility_id' => $facility->id, 'sheet' => $row['sheet'], 'line' => $row['line'], 'field' => $field, 'old_value' => $old, 'new_value' => $new];
    }

    private function reject(array &$rowResults, array &$issues, array &$summary, array $row, string $status, string $code, string $message, ?Facility $facility = null): void
    {
        $summary[$status === 'conflict' ? 'conflicts' : $status]++;
        $current = $facility === null ? null : json_encode([
            'phone' => $facility->phone, 'email' => $facility->email, 'website' => $facility->website, 'contact_source' => $facility->contact_source,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $proposed = json_encode([
            'phone' => $row['phone'], 'email' => $row['email'], 'website' => $row['website'], 'contact_source' => $row['source'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $issues[] = ['sheet' => $row['sheet'], 'line' => $row['line'], 'facility_id' => $facility?->id ?? $row['facility_id'], 'current_value' => $current, 'new_value' => $proposed, 'code' => $code, 'message' => $message];
        $rowResults[] = $this->rowResult($row, $facility, $status, $message);
    }

    /** @return array<string,mixed> */
    private function rowResult(array $row, ?Facility $facility, string $status, string $message, array $warnings = []): array
    {
        return ['sheet' => $row['sheet'], 'line' => $row['line'], 'facility_id' => $facility?->id ?? $row['facility_id'], 'name' => $row['name'], 'city' => $row['city'], 'status' => $status, 'message' => $message, 'warnings' => implode(' | ', $warnings)];
    }

    private function percentage(int $count, int $total): float
    {
        return $total === 0 ? 0.0 : round($count * 100 / $total, 2);
    }
}

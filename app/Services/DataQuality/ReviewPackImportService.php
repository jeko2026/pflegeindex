<?php

namespace App\Services\DataQuality;

use App\Models\Facility;
use App\Support\HttpUrl;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReviewPackImportService
{
    private const REVIEW_RESULTS = ['verified', 'partially_verified', 'needs_review', 'conflict', 'closed', 'duplicate', 'not_found'];

    private const SUPPORTED_CONTACT_STATUSES = ['verified', 'pending', 'not_found'];

    private const ALLOWED_TYPES = ['Ambulante Pflege', 'Stationäre/teilstationäre Pflege', 'Krankenhaus'];

    private const FIELD_MAP = [
        'reviewed_name' => 'name',
        'reviewed_type' => 'type',
        'reviewed_address' => 'address',
        'reviewed_postal_code' => 'postal_code',
        'reviewed_phone' => 'phone',
        'reviewed_email' => 'email',
        'reviewed_website' => 'website',
    ];

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>, source_path: string}
     */
    public function read(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open review pack: '.$path);
        }

        try {
            $first = fread($handle, 3);
            if ($first !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            $headers = fgetcsv($handle);
            if (! is_array($headers) || $headers === []) {
                throw new RuntimeException('Review pack has no CSV header.');
            }
            $headers = array_map(static fn ($header): string => trim((string) $header), $headers);
            if (! in_array('facility_id', $headers, true)) {
                throw new RuntimeException('Review pack must contain a facility_id column.');
            }

            $rows = [];
            while (($values = fgetcsv($handle)) !== false) {
                if ($values === [null] || $values === []) {
                    continue;
                }
                if (count($values) !== count($headers)) {
                    throw new RuntimeException('A CSV row has a different number of columns than the header.');
                }
                $rows[] = array_map(static fn ($value): string => trim((string) $value), array_combine($headers, $values));
            }
        } finally {
            fclose($handle);
        }

        return ['headers' => $headers, 'rows' => $rows, 'source_path' => $path];
    }

    /**
     * Validate all rows and build a non-mutating change plan.
     *
     * @param  array{headers: array<int, string>, rows: array<int, array<string, string>>, source_path: string}  $payload
     * @return array<string, mixed>
     */
    public function plan(array $payload, bool $allowStale = false, bool $skipUnchanged = false, ?int $facilityId = null): array
    {
        $rows = $payload['rows'];
        $ids = [];
        $duplicateIds = [];
        foreach ($rows as $index => $row) {
            if (trim((string) ($row['review_result'] ?? '')) === '') {
                continue;
            }
            $id = filter_var($row['facility_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id !== false) {
                if (isset($ids[$id])) {
                    $duplicateIds[$id] = true;
                }
                $ids[$id] = $index;
            }
        }

        $changes = [];
        $errors = [];
        $warnings = [];
        $skipped = 0;
        $reviewed = 0;
        $facilitiesFound = 0;
        $facilitiesMissing = 0;
        $unchangedFields = 0;
        $conflicts = 0;

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $result = strtolower(trim((string) ($row['review_result'] ?? '')));
            if ($result === '') {
                $skipped++;

                continue;
            }
            $reviewed++;

            $id = filter_var($row['facility_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                $errors[] = $this->error($line, null, 'invalid_facility_id', 'facility_id must be a positive integer.');

                continue;
            }
            if (isset($duplicateIds[$id])) {
                $errors[] = $this->error($line, $id, 'duplicate_facility_id', 'The facility ID occurs more than once in this CSV.');

                continue;
            }
            if ($facilityId !== null && $id !== $facilityId) {
                $skipped++;

                continue;
            }
            if (! in_array($result, self::REVIEW_RESULTS, true)) {
                $errors[] = $this->error($line, $id, 'invalid_review_result', 'Unsupported review_result: '.$result.'.');

                continue;
            }
            $facility = Facility::query()->with('city')->find($id);
            if ($facility === null) {
                $facilitiesMissing++;
                $errors[] = $this->error($line, $id, 'facility_not_found', 'Facility ID does not exist.');

                continue;
            }
            $facilitiesFound++;

            $rowWarnings = [];
            $stale = $this->staleFields($facility, $row);
            if ($stale !== []) {
                $rowWarnings[] = 'stale_review_row: '.implode(', ', $stale);
                if (! $allowStale) {
                    $errors[] = $this->error($line, $id, 'stale_review_row', 'The database no longer matches the review pack snapshot: '.implode(', ', $stale).'.');

                    continue;
                }
                $warnings[] = $this->warning($line, $id, 'stale_review_row', 'Stale row accepted only because --allow-stale was supplied.');
            }

            $rowPlan = $this->buildPlan($facility, $row, $result, $line, $rowWarnings);
            $errors = [...$errors, ...$rowPlan['errors']];
            $warnings = [...$warnings, ...$rowPlan['warnings']];
            if ($rowPlan['conflict']) {
                $conflicts++;
            }
            if ($rowPlan['errors'] !== []) {
                continue;
            }
            if ($rowPlan['changes'] === []) {
                $unchangedFields += count(self::FIELD_MAP);
                if ($skipUnchanged) {
                    continue;
                }
            }
            $changes = [...$changes, ...$rowPlan['changes']];
        }

        return [
            'source_path' => $payload['source_path'],
            'headers' => $payload['headers'],
            'rows' => $rows,
            'rows_total' => count($rows),
            'rows_empty_skipped' => $skipped,
            'rows_reviewed' => $reviewed,
            'facilities_found' => $facilitiesFound,
            'facilities_missing' => $facilitiesMissing,
            'changes' => $changes,
            'errors' => $errors,
            'warnings' => $warnings,
            'conflicts' => $conflicts,
            'unchanged_fields' => $unchangedFields,
            'ready_to_apply' => $errors === [],
            'fatal' => $errors !== [],
        ];
    }

    /**
     * Apply a validated plan atomically. Empty reviewed fields are never part of the plan.
     *
     * @param  array<string, mixed>  $plan
     * @return array{facilities: int, fields: int, integrity: string, foreign_keys: int}
     */
    public function apply(array $plan): array
    {
        $appliedFacilities = [];
        $appliedFields = 0;

        DB::transaction(function () use ($plan, &$appliedFacilities, &$appliedFields): void {
            foreach (collect($plan['changes'])->groupBy('facility_id') as $facilityId => $changes) {
                $facility = Facility::query()->lockForUpdate()->find((int) $facilityId);
                if ($facility === null) {
                    throw new RuntimeException('Facility '.$facilityId.' disappeared before apply.');
                }
                foreach ($changes as $change) {
                    $field = $change['field'];
                    if ($field === 'contact_checked_at') {
                        $facility->{$field} = now();

                        continue;
                    }
                    $current = $facility->{$field};
                    if ($this->scalar($current) !== $this->scalar($change['old_value'])) {
                        throw new RuntimeException('Concurrent change detected for facility '.$facility->id.' field '.$field.'.');
                    }
                    $facility->{$field} = $change['new_value'];
                }
                if ($facility->isDirty()) {
                    $facility->save();
                    $appliedFacilities[] = $facility->id;
                    $appliedFields += count($changes);
                }
            }
        });

        $integrity = (string) (DB::selectOne('PRAGMA integrity_check')->integrity_check ?? 'unknown');
        $foreignKeys = count(DB::select('PRAGMA foreign_key_check'));
        if ($integrity !== 'ok' || $foreignKeys !== 0) {
            throw new RuntimeException('Post-apply SQLite validation failed.');
        }

        return ['facilities' => count(array_unique($appliedFacilities)), 'fields' => $appliedFields, 'integrity' => $integrity, 'foreign_keys' => $foreignKeys];
    }

    /** @param array<string, string> $row @return array{changes: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>, conflict: bool} */
    private function buildPlan(Facility $facility, array $row, string $result, int $line, array $rowWarnings): array
    {
        $changes = [];
        $errors = [];
        $warnings = [];
        $conflict = in_array($result, ['conflict', 'needs_review'], true);
        $source = $this->clean($row['reviewed_source_url'] ?? '') ?: ($this->clean($row['official_source_url'] ?? '') ?? '');
        $unsupportedNotes = $this->clean($row['review_notes'] ?? '') ?? '';

        if ($unsupportedNotes !== '') {
            $errors[] = $this->error($line, $facility->id, 'unsupported_review_notes', 'review_notes is not a persisted field in the current facilities schema; no note was imported.');
        }
        if (in_array($result, ['closed', 'duplicate'], true)) {
            $errors[] = $this->error($line, $facility->id, 'unsupported_review_result', "review_result '{$result}' cannot be mapped to the current contact_status enum without hiding, deleting, or merging a facility.");
        }
        if (filled($row['reviewed_city'] ?? '') || filled($row['reviewed_gemeinde'] ?? '') || filled($row['reviewed_landkreis'] ?? '')) {
            $errors[] = $this->error($line, $facility->id, 'unsupported_geography_change', 'Geography changes require a separate approved mapping workflow.');
        }
        if ($result === 'duplicate' && blank($row['primary_facility_id'] ?? '')) {
            $errors[] = $this->error($line, $facility->id, 'duplicate_primary_id_missing', 'A duplicate result requires a primary_facility_id; automatic merging is never performed.');
        }

        foreach (self::FIELD_MAP as $csvField => $modelField) {
            $value = $this->clean($row[$csvField] ?? '');
            if ($value === null) {
                continue;
            }
            if ($modelField === 'type' && ! in_array($value, self::ALLOWED_TYPES, true)) {
                $errors[] = $this->error($line, $facility->id, 'invalid_type', "Unsupported facility type '{$value}'.");

                continue;
            }
            if ($modelField === 'postal_code' && ! preg_match('/^\d{5}$/', $value)) {
                $errors[] = $this->error($line, $facility->id, 'invalid_postal_code', 'postal_code must contain five digits.');

                continue;
            }
            if ($modelField === 'phone') {
                $audit = FacilityDataAuditor::auditPhone($value);
                if ($audit !== null) {
                    $errors[] = $this->error($line, $facility->id, 'invalid_phone', 'Reviewed phone is missing or suspicious.');

                    continue;
                }
            }
            if ($modelField === 'email') {
                $audit = FacilityDataAuditor::auditEmail($value);
                if ($audit !== null) {
                    $errors[] = $this->error($line, $facility->id, 'invalid_email', 'Reviewed e-mail is invalid, corrupt, or a placeholder.');

                    continue;
                }
            }
            if ($modelField === 'website' && ! HttpUrl::isValid($value)) {
                $errors[] = $this->error($line, $facility->id, 'invalid_website', 'Reviewed website must be an absolute public http(s) URL.');

                continue;
            }
            if ($facility->contact_locked && in_array($modelField, ['phone', 'email', 'website'], true) && $value !== (string) $facility->{$modelField}) {
                $errors[] = $this->error($line, $facility->id, 'contact_locked', 'Protected contact fields cannot be overwritten by this importer.');

                continue;
            }
            $old = $facility->{$modelField};
            if ((string) $old === $value) {
                continue;
            }
            if (in_array($modelField, ['name', 'address', 'type'], true)) {
                $warnings[] = $this->warning($line, $facility->id, 'sensitive_field_change', "Reviewed {$modelField} differs from the current value.");
            }
            $changes[] = $this->change($facility, $result, $modelField, $old, $value, $rowWarnings);
        }

        $mappedStatus = match ($result) {
            'verified' => 'verified',
            'not_found' => 'not_found',
            default => 'pending',
        };
        if ($result === 'verified') {
            if ($source === '' && blank($facility->contact_source)) {
                $errors[] = $this->error($line, $facility->id, 'verified_without_source', 'verified requires reviewed_source_url or an existing contact_source.');
            } elseif ($source !== '' && ! HttpUrl::isValid($source)) {
                $errors[] = $this->error($line, $facility->id, 'invalid_source_url', 'reviewed_source_url must be an absolute public http(s) URL.');
            }
            $hasContact = collect(['phone', 'email', 'website'])->contains(fn (string $field): bool => filled($row['reviewed_'.$field] ?? '') || filled($facility->{$field}));
            if (! $hasContact) {
                $errors[] = $this->error($line, $facility->id, 'verified_without_contact', 'verified requires at least one contact field.');
            }
        } elseif ($source !== '' && ! HttpUrl::isValid($source)) {
            $errors[] = $this->error($line, $facility->id, 'invalid_source_url', 'reviewed_source_url must be an absolute public http(s) URL.');
        }
        if ($source !== '' && $facility->contact_locked && $source !== (string) $facility->contact_source) {
            $errors[] = $this->error($line, $facility->id, 'contact_locked', 'Protected contact source cannot be overwritten by this importer.');
        } elseif ($source !== '' && $source !== (string) $facility->contact_source) {
            $changes[] = $this->change($facility, $result, 'contact_source', $facility->contact_source, $source, $rowWarnings);
        }
        if ($facility->contact_locked && $mappedStatus !== $facility->contact_status) {
            $errors[] = $this->error($line, $facility->id, 'contact_locked', 'Protected contact status cannot be overwritten by this importer.');
        }
        if ($mappedStatus !== $facility->contact_status) {
            $changes[] = $this->change($facility, $result, 'contact_status', $facility->contact_status, $mappedStatus, $rowWarnings);
        }
        if ($result === 'verified' && ($changes !== []) && ! $facility->contact_locked) {
            $changes[] = $this->change($facility, $result, 'contact_checked_at', $facility->contact_checked_at?->toIso8601String(), '__NOW__', $rowWarnings);
        }

        return compact('changes', 'errors', 'warnings', 'conflict');
    }

    /** @param array<string, string> $row @return array<int, string> */
    private function staleFields(Facility $facility, array $row): array
    {
        $comparisons = [
            'name' => 'name', 'type' => 'type', 'address' => 'address', 'postal_code' => 'postal_code',
            'phone' => 'phone', 'email' => 'email', 'website' => 'website',
            'current_verification_status' => 'contact_status', 'current_provenance' => 'contact_source',
        ];
        $stale = [];
        foreach ($comparisons as $csvField => $modelField) {
            if (! array_key_exists($csvField, $row)) {
                continue;
            }
            $expected = $this->clean($row[$csvField]);
            $actual = $this->clean((string) ($facility->{$modelField} ?? ''));
            if ($expected !== $actual) {
                $stale[] = $csvField;
            }
        }

        return $stale;
    }

    private function clean(?string $value): ?string
    {
        return FacilityContactNormalizer::clean($value);
    }

    /** @return array<string, mixed> */
    private function change(Facility $facility, string $result, string $field, mixed $old, mixed $new, array $warnings): array
    {
        return [
            'facility_id' => $facility->id, 'name' => $facility->name, 'review_result' => $result, 'field' => $field,
            'old_value' => $old, 'new_value' => $new, 'validation_status' => 'valid',
            'warning' => implode(' | ', $warnings), 'action' => 'apply',
        ];
    }

    /** @return array<string, mixed> */
    private function error(int $line, ?int $facilityId, string $code, string $message): array
    {
        return ['line' => $line, 'facility_id' => $facilityId, 'code' => $code, 'message' => $message];
    }

    /** @return array<string, mixed> */
    private function warning(int $line, ?int $facilityId, string $code, string $message): array
    {
        return ['line' => $line, 'facility_id' => $facilityId, 'code' => $code, 'message' => $message];
    }

    private function scalar(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('c');
        }

        return $value === null ? '' : (string) $value;
    }
}

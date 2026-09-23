<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\FacilityAttribute;
use App\Models\FacilityGeocode;
use App\Models\FacilityNearbyPlace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ImportFacilityContextData extends Command
{
    protected $signature = 'pflegeindex:facility-context-import {path : Package directory created by facility-context-export} {--allow-production : Required to import into production}';

    protected $description = 'Idempotently import an audited facility context data package after database backup.';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('allow-production')) {
            $this->error('Production import requires --allow-production after a database backup.');

            return self::FAILURE;
        }

        $directory = base_path((string) $this->argument('path'));
        $manifestPath = $directory.'/manifest.json';
        if (! is_dir($directory) || ! is_file($manifestPath)) {
            $this->error("Package not found: {$directory}");

            return self::FAILURE;
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== 'pflegeindex-facility-context' || ($manifest['version'] ?? null) !== 2) {
            $this->error('Unsupported facility context package.');

            return self::FAILURE;
        }

        $stats = ['attributes_created' => 0, 'attributes_protected' => 0, 'geocodes_created' => 0, 'geocodes_updated' => 0, 'geocodes_protected' => 0, 'nearby_created' => 0, 'nearby_updated' => 0];
        DB::transaction(function () use ($directory, &$stats): void {
            $this->each($directory.'/facility_attributes.ndjson', function (array $row) use (&$stats): void {
                $facility = $this->facility($row['facility'] ?? []);
                $data = $row['data'] ?? [];
                $identity = collect($data)->only(['attribute_key', 'normalized_value', 'source_url'])->all();
                $existing = FacilityAttribute::query()->where('facility_id', $facility->id)->where($identity)->first();
                if ($existing) {
                    if (in_array($existing->review_status, [FacilityAttribute::REVIEW_APPROVED, FacilityAttribute::REVIEW_REJECTED], true)) {
                        $stats['attributes_protected']++;
                    }

                    return;
                }
                FacilityAttribute::query()->create(['facility_id' => $facility->id, 'source_id' => null] + $data);
                $stats['attributes_created']++;
            });

            $score = ['LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3];
            $this->each($directory.'/facility_geocodes.ndjson', function (array $row) use (&$stats, $score): void {
                $facility = $this->facility($row['facility'] ?? []);
                $data = $row['data'] ?? [];
                $existing = FacilityGeocode::query()->where('facility_id', $facility->id)->first();
                if ($existing && ($score[$existing->confidence] ?? 0) > ($score[$data['confidence'] ?? ''] ?? 0)) {
                    $stats['geocodes_protected']++;

                    return;
                }
                if ($existing) {
                    $existing->update($data);
                    $stats['geocodes_updated']++;
                } else {
                    FacilityGeocode::query()->create(['facility_id' => $facility->id] + $data);
                    $stats['geocodes_created']++;
                }
            });

            $this->each($directory.'/facility_nearby_places.ndjson', function (array $row) use (&$stats): void {
                $facility = $this->facility($row['facility'] ?? []);
                $data = $row['data'] ?? [];
                $record = FacilityNearbyPlace::query()->updateOrCreate(
                    ['facility_id' => $facility->id, 'category' => $data['category'], 'external_id' => $data['external_id']],
                    $data,
                );
                $stats[$record->wasRecentlyCreated ? 'nearby_created' : 'nearby_updated']++;
            });
        });

        $this->table(array_keys($stats), [array_values($stats)]);

        return self::SUCCESS;
    }

    private function each(string $path, callable $callback): void
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Dataset not found: {$path}");
        }
        $file = new \SplFileObject($path);
        while (! $file->eof()) {
            $line = trim((string) $file->fgets());
            if ($line !== '') {
                $callback(json_decode($line, true, 512, JSON_THROW_ON_ERROR));
            }
        }
    }

    private function facility(array $reference): Facility
    {
        $facility = Facility::query()->with('city')->find($reference['id'] ?? null);
        if (! $facility || $facility->slug !== ($reference['slug'] ?? null) || $facility->city->slug !== ($reference['city_slug'] ?? null) || $facility->city->state_slug !== ($reference['state_slug'] ?? null)) {
            throw new \RuntimeException(sprintf('Facility identity mismatch for ID %s.', $reference['id'] ?? '?'));
        }

        return $facility;
    }
}

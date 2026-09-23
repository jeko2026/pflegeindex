<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\FacilityGeocode;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ImportBrandenburgGeocodes extends Command
{
    protected $signature = 'facility-nearby:import-brandenburg-geocodes {report=reports/brandenburg-geocoding-full.json}';

    protected $description = 'Importiert nur HIGH/MEDIUM-Nominatim-Geocodes aus dem lokalen Brandenburg-Bericht.';

    public function handle(): int
    {
        $path = base_path($this->argument('report'));
        if (! is_file($path)) {
            $this->error("Report nicht gefunden: {$path}");

            return self::FAILURE;
        }

        $report = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $rows = collect($report['results'] ?? [])->filter(fn (array $row): bool => in_array($row['match_quality'] ?? null, ['HIGH', 'MEDIUM'], true));
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'missing' => 0];
        $score = ['LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3];
        $timestamp = CarbonImmutable::parse($report['generated_at'] ?? now());

        foreach ($rows as $row) {
            $facility = Facility::query()->find($row['facility_id']);
            if (! $facility || $facility->city?->state_slug !== 'brandenburg') {
                $stats['missing']++;

                continue;
            }

            $incomingConfidence = $row['match_quality'];
            $existing = FacilityGeocode::query()->where('facility_id', $facility->id)->first();
            if ($existing && $score[$existing->confidence] > $score[$incomingConfidence]) {
                $stats['skipped']++;

                continue;
            }

            $attributes = [
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'confidence' => $incomingConfidence,
                'source' => 'OpenStreetMap Nominatim',
                'external_id' => filled($row['osm_type'] ?? null) && filled($row['osm_id'] ?? null) ? $row['osm_type'].'/'.$row['osm_id'] : null,
                'geocoded_at' => $timestamp,
            ];
            if ($existing) {
                $existing->fill($attributes)->save();
                $stats['updated']++;
            } else {
                FacilityGeocode::query()->create(['facility_id' => $facility->id] + $attributes);
                $stats['created']++;
            }
        }

        $this->table(['created', 'updated', 'skipped', 'missing', 'eligible'], [[...$stats, 'eligible' => $rows->count()]]);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\FacilityAttribute;
use App\Models\FacilityGeocode;
use App\Models\FacilityNearbyPlace;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

final class ExportFacilityContextData extends Command
{
    protected $signature = 'pflegeindex:facility-context-export {path=storage/app/exports/facility-context}';

    protected $description = 'Export local enrichment, Brandenburg geocodes, and nearby places into a portable data package.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Facility context export is blocked in production.');

            return self::FAILURE;
        }

        $directory = base_path((string) $this->argument('path'));
        File::ensureDirectoryExists($directory);
        $datasets = [
            'facility_attributes' => [FacilityAttribute::class, ['attribute_key', 'attribute_value', 'normalized_value', 'confidence', 'review_status', 'source_url', 'source_type', 'source_title', 'source_excerpt', 'source_context', 'relation_reason', 'discovered_at', 'verified_at', 'published_at']],
            'facility_geocodes' => [FacilityGeocode::class, ['latitude', 'longitude', 'confidence', 'source', 'external_id', 'geocoded_at']],
            'facility_nearby_places' => [FacilityNearbyPlace::class, ['category', 'name', 'locality', 'distance_meters', 'latitude', 'longitude', 'source', 'external_id', 'confidence', 'discovered_at', 'updated_at']],
        ];

        $counts = [];
        foreach ($datasets as $name => [$model, $fields]) {
            $counts[$name] = $this->writeDataset($directory, $name, $model, $fields);
        }

        File::put($directory.'/manifest.json', json_encode([
            'format' => 'pflegeindex-facility-context',
            'version' => 2,
            'exported_at' => now()->toIso8601String(),
            'counts' => $counts,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->table(['dataset', 'rows'], collect($counts)->map(fn (int $count, string $key): array => [$key, $count])->all());
        $this->info("Export: {$directory}");

        return self::SUCCESS;
    }

    private function writeDataset(string $directory, string $name, string $model, array $fields): int
    {
        $handle = fopen($directory.'/'.$name.'.ndjson', 'wb');
        $count = 0;
        foreach ($model::query()->with('facility.city')->lazyById(200) as $row) {
            fwrite($handle, json_encode($this->row($row, $fields), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR).PHP_EOL);
            $count++;
        }
        fclose($handle);

        return $count;
    }

    private function row(Model $model, array $fields): array
    {
        $facility = $model->facility;

        return [
            'facility' => [
                'id' => $facility->id,
                'slug' => $facility->slug,
                'city_slug' => $facility->city->slug,
                'state_slug' => $facility->city->state_slug,
            ],
            'data' => collect($fields)->mapWithKeys(fn (string $field): array => [$field => $model->getRawOriginal($field)])->all(),
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Models\FacilityNearbyPlace;
use App\Services\FacilityNearby\NearbyPlaceCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EnrichFacilityNearbyPlaces extends Command
{
    protected $signature = 'facility-nearby:enrich {--refresh : Overpass-Tile erneut laden} {--tile-size=1 : Grad pro geografischem Tile} {--max-tiles=0 : Nur die ersten N Tiles, 0 bedeutet alle}';

    protected $description = 'Ermittelt aus OSM/Overpass den nächstgelegenen relevanten POI je Kategorie pro Einrichtung.';

    private int $httpRequests = 0;

    public function handle(): int
    {
        $started = microtime(true);
        $size = (float) $this->option('tile-size');
        if ($size <= 0 || $size > 2) {
            $this->error('tile-size muss größer als 0 und maximal 2 sein.');

            return self::FAILURE;
        }

        $facilities = $this->facilities();
        $tiles = $facilities->groupBy(fn (object $facility): string => $this->tileKey((float) $facility->latitude, (float) $facility->longitude, $size));
        $tiles = $tiles->sortKeys();
        if ((int) $this->option('max-tiles') > 0) {
            $tiles = $tiles->take((int) $this->option('max-tiles'));
        }
        $stats = ['processed' => 0, 'success' => 0, 'no_poi' => 0, 'errors' => 0, 'created' => 0, 'updated' => 0];
        $reportRows = [];

        foreach ($tiles as $tileKey => $items) {
            try {
                $places = $this->loadTile($tileKey, $size, (bool) $this->option('refresh'));
                foreach ($items as $facility) {
                    $matches = $this->nearestFor($facility, $places);
                    $saved = 0;
                    foreach ($matches as $category => $place) {
                        $record = FacilityNearbyPlace::query()->updateOrCreate(
                            ['facility_id' => $facility->id, 'category' => $category, 'external_id' => $place['external_id']],
                            ['name' => $place['name'], 'distance_meters' => $place['distance_meters'], 'latitude' => $place['latitude'], 'longitude' => $place['longitude'], 'source' => 'OpenStreetMap Overpass', 'confidence' => 'HIGH', 'discovered_at' => now(), 'updated_at' => now()]
                        );
                        $stats[$record->wasRecentlyCreated ? 'created' : 'updated']++;
                        $saved++;
                    }
                    $stats['processed']++;
                    $stats[$saved ? 'success' : 'no_poi']++;
                    $reportRows[] = ['facility_id' => $facility->id, 'region' => $facility->state_slug, 'places' => array_values($matches)];
                }
                $this->line("{$tileKey}: {$items->count()} Einrichtungen, ".count($places).' OSM-Objekte');
                if ($items->count() > $stats['processed'] % 500) {
                    $this->line('PROGRESS '.$stats['processed'].' Einrichtungen');
                }
                file_put_contents(storage_path('app/nearby-enrichment-progress.json'), json_encode([
                    'processed' => $stats['processed'], 'success' => $stats['success'], 'no_poi' => $stats['no_poi'],
                    'errors' => $stats['errors'], 'created' => $stats['created'], 'updated' => $stats['updated'],
                    'http_requests' => $this->httpRequests, 'updated_at' => now()->toIso8601String(),
                ], JSON_PRETTY_PRINT));
            } catch (\Throwable $exception) {
                $stats['errors'] += $items->count();
                $this->error("{$tileKey}: ".$exception->getMessage());
            }
        }

        $this->writeReport($stats, $reportRows, $started, $tiles->count());
        $this->table(array_keys($stats), [array_values($stats)]);
        $this->line("HTTP requests: {$this->httpRequests}; runtime: ".round(microtime(true) - $started, 1).' s');

        return $stats['errors'] ? self::FAILURE : self::SUCCESS;
    }

    private function facilities()
    {
        $sachsen = Facility::query()->select('facilities.id', 'facilities.latitude', 'facilities.longitude', 'cities.state_slug')->join('cities', 'cities.id', '=', 'facilities.city_id')->where('facilities.is_active', true)->where('cities.state_slug', 'sachsen')->whereNotNull('facilities.latitude')->whereNotNull('facilities.longitude')->whereBetween('facilities.latitude', [50.0, 52.0])->whereBetween('facilities.longitude', [11.5, 15.5]);
        $brandenburg = Facility::query()->select('facilities.id', 'facility_geocodes.latitude', 'facility_geocodes.longitude', 'cities.state_slug')->join('cities', 'cities.id', '=', 'facilities.city_id')->join('facility_geocodes', 'facility_geocodes.facility_id', '=', 'facilities.id')->where('facilities.is_active', true)->where('cities.state_slug', 'brandenburg')->whereIn('facility_geocodes.confidence', ['HIGH', 'MEDIUM']);

        return $sachsen->unionAll($brandenburg)->get();
    }

    private function tileKey(float $latitude, float $longitude, float $size): string
    {
        return floor($latitude / $size) * $size.':'.floor($longitude / $size) * $size;
    }

    private function loadTile(string $key, float $size, bool $refresh): array
    {
        [$south, $west] = array_map('floatval', explode(':', $key));
        $north = $south + $size;
        $east = $west + $size;
        $cacheDir = storage_path('app/nearby-osm-tiles');
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }
        $cache = $cacheDir.'/'.str_replace([':', '-'], ['_', 'm'], $key).'.json';
        if (! $refresh && is_file($cache)) {
            return json_decode((string) file_get_contents($cache), true, flags: JSON_THROW_ON_ERROR);
        }

        $margin = 0.20;
        $bbox = ($south - $margin).','.($west - $margin).','.($north + $margin).','.($east + $margin);
        $query = "[out:json][timeout:180];(nwr[amenity=hospital]({$bbox});nwr[healthcare=hospital]({$bbox});nwr[amenity=pharmacy]({$bbox});nwr[healthcare=pharmacy]({$bbox});nwr[amenity=doctors]({$bbox});nwr[healthcare=doctor]({$bbox});nwr[highway=bus_stop]({$bbox});nwr[public_transport=platform][bus]({$bbox});nwr[railway~\"^(station|halt)$\"]({$bbox});nwr[shop=supermarket]({$bbox}););out center tags;";
        $response = Http::asForm()->withUserAgent('PflegeIndex-LageUmgebung/1.0 (info@pflegeindex.com)')->timeout(210)->retry(2, 4000)->post('https://overpass-api.de/api/interpreter', ['data' => $query]);
        $this->httpRequests++;
        $response->throw();
        $elements = $response->json('elements') ?? [];
        file_put_contents($cache, json_encode($elements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        sleep(1);

        return $elements;
    }

    private function nearestFor(object $facility, array $elements): array
    {
        $nearest = [];
        foreach ($elements as $element) {
            $tags = $element['tags'] ?? [];
            $latitude = $element['lat'] ?? data_get($element, 'center.lat');
            $longitude = $element['lon'] ?? data_get($element, 'center.lon');
            if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                continue;
            }
            foreach (NearbyPlaceCatalog::categories($tags) as $category) {
                $distance = $this->distance((float) $facility->latitude, (float) $facility->longitude, (float) $latitude, (float) $longitude);
                if ($distance > NearbyPlaceCatalog::RADII[$category]) {
                    continue;
                }
                $candidate = ['category' => $category, 'name' => trim($tags['name']), 'distance_meters' => (int) round($distance), 'latitude' => (float) $latitude, 'longitude' => (float) $longitude, 'external_id' => $element['type'].'/'.$element['id'], 'confidence' => 'HIGH'];
                if (! isset($nearest[$category]) || $candidate['distance_meters'] < $nearest[$category]['distance_meters']) {
                    $nearest[$category] = $candidate;
                }
            }
        }
        ksort($nearest);

        return $nearest;
    }

    private function distance(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earth = 6371000;
        $lat = deg2rad($latitudeB - $latitudeA);
        $lon = deg2rad($longitudeB - $longitudeA);
        $a = sin($lat / 2) ** 2 + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($lon / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }

    private function writeReport(array $stats, array $rows, float $started, int $tiles): void
    {
        $byRegion = collect($rows)->groupBy('region')->map(fn ($items) => ['facilities' => $items->count(), 'poi_rows' => $items->sum(fn ($row) => count($row['places']))]);
        $categories = collect($rows)->flatMap(fn ($row) => $row['places'])->countBy('category')->sort();
        $payload = ['generated_at' => now()->toIso8601String(), 'stats' => $stats, 'http_requests' => $this->httpRequests, 'tiles' => $tiles, 'runtime_seconds' => round(microtime(true) - $started, 2), 'by_region' => $byRegion, 'by_category' => $categories, 'facilities' => $rows];
        $name = 'nearby-enrichment-mass-'.now()->format('Ymd-His');
        file_put_contents(base_path("reports/{$name}.json"), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

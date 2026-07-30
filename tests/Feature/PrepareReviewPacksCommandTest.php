<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Services\DataQuality\DuplicateCandidateTriage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PrepareReviewPacksCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $outputDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDirectory = storage_path('framework/testing/review-packs-'.str_replace('.', '', uniqid('', true)));
    }

    public function test_shared_awo_network_website_is_not_an_exact_duplicate(): void
    {
        $city = $this->city();
        $first = $this->facility($city, ['name' => 'AWO Sozialstation Potsdam', 'slug' => 'awo-sozialstation', 'website' => 'https://awo.example/', 'phone' => '+49 331 100001', 'email' => 'potsdam@awo.example', 'address' => 'Parkstraße 1']);
        $second = $this->facility($city, ['name' => 'AWO Tagespflege Am Park', 'slug' => 'awo-tagespflege', 'website' => 'https://awo.example/', 'phone' => '+49 331 100002', 'email' => 'tagespflege@awo.example', 'address' => 'Waldstraße 2']);

        $classification = app(DuplicateCandidateTriage::class)->classify(collect([$first, $second]));

        $this->assertSame('shared_network_website', $classification);
    }

    public function test_different_service_types_at_one_address_are_not_exact_duplicates(): void
    {
        $city = $this->city();
        $first = $this->facility($city, ['name' => 'Pflegedienst am Markt', 'slug' => 'pflegedienst-am-markt', 'type' => 'Ambulante Pflege']);
        $second = $this->facility($city, ['name' => 'Tagespflege am Markt', 'slug' => 'tagespflege-am-markt', 'type' => 'Stationäre/teilstationäre Pflege']);

        $this->assertSame('same_address_different_service', app(DuplicateCandidateTriage::class)->classify(collect([$first, $second])));
    }

    public function test_matching_name_address_city_and_type_are_exact_duplicates(): void
    {
        $city = $this->city();
        $first = $this->facility($city, ['name' => 'Pflege am Park', 'slug' => 'pflege-am-park-a']);
        $second = $this->facility($city, ['name' => 'Pflege am Park', 'slug' => 'pflege-am-park-b']);

        $this->assertSame('exact_duplicate', app(DuplicateCandidateTriage::class)->classify(collect([$first, $second])));
    }

    public function test_matching_name_and_phone_are_a_strong_candidate(): void
    {
        $city = $this->city();
        $first = $this->facility($city, ['name' => 'Pflege Sonnenschein', 'slug' => 'pflege-sonnenschein-a', 'address' => 'Parkstraße 1', 'email' => 'a@example.org', 'website' => 'https://a.example.org/standort']);
        $second = $this->facility($city, ['name' => 'Pflege Sonnenschein', 'slug' => 'pflege-sonnenschein-b', 'address' => 'Waldstraße 2', 'email' => 'b@example.org', 'website' => 'https://b.example.org/standort']);

        $this->assertSame('strong_duplicate_candidate', app(DuplicateCandidateTriage::class)->classify(collect([$first, $second])));
    }

    public function test_known_high_ids_are_in_priority_file_and_database_is_unchanged(): void
    {
        $city = $this->city();
        $first = $this->facility($city, ['id' => 676, 'name' => 'AWO Seniorenzentrum Jüterbog', 'slug' => 'awo-jb-a']);
        $this->facility($city, ['id' => 677, 'name' => 'AWO Seniorenzentrum Jüterbog', 'slug' => 'awo-jb-b']);
        $this->facility($city, ['id' => 892, 'name' => 'Ambulantes Pflegeteam Denny Schulz GmbH', 'slug' => 'denny-schulz', 'email' => 'geschlossenï¿½contactsï¿½info@email.com']);
        $before = $first->fresh()->getRawOriginal();

        $this->artisan('data-quality:prepare-review-packs', ['--pack-size' => 2, '--output' => $this->outputDirectory])
            ->assertExitCode(0);

        $high = File::get($this->outputDirectory.'/priority-high-manual-review.csv');
        foreach ([676, 677, 892] as $id) {
            $this->assertStringContainsString(','.$id.',', $high);
        }
        $this->assertSame($before, $first->fresh()->getRawOriginal());
        $this->assertFileExists($this->outputDirectory.'/duplicate-triage.csv');
        $this->assertFileExists($this->outputDirectory.'/geography-manual-review.csv');
        $this->assertFileExists($this->outputDirectory.'/README.md');
    }

    public function test_all_723_not_reviewed_facilities_are_in_exactly_one_main_pack_and_index_totals_match(): void
    {
        $city = $this->city();
        $now = now()->format('Y-m-d H:i:s');
        $rows = [];
        for ($id = 1; $id <= 723; $id++) {
            $rows[] = [
                'source_id' => 'not-reviewed-'.$id, 'city_id' => $city->id, 'name' => 'Prüfung Einrichtung '.$id,
                'slug' => 'pruefung-einrichtung-'.$id, 'postal_code' => '14467', 'street' => 'Teststraße',
                'house_number' => (string) $id, 'address' => 'Teststraße '.$id, 'type' => 'Ambulante Pflege',
                'care_types' => json_encode(['Ambulante Pflege']), 'features' => json_encode([]),
                'phone' => null, 'email' => null, 'website' => null, 'contact_source' => null,
                'contact_status' => null, 'contact_checked_at' => null, 'contact_locked' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('facilities')->insert($chunk);
        }

        $this->artisan('data-quality:prepare-review-packs', ['--pack-size' => 30, '--output' => $this->outputDirectory])
            ->assertExitCode(0);

        $packFiles = File::glob($this->outputDirectory.'/review-pack-*.csv') ?: [];
        $facilityIds = collect($packFiles)->flatMap(fn (string $path) => collect($this->csvRows($path))->pluck('facility_id'));
        $index = collect($this->csvRows($this->outputDirectory.'/index.csv'));
        $summary = json_decode(File::get($this->outputDirectory.'/triage-summary.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(25, $packFiles);
        $this->assertCount(723, $facilityIds);
        $this->assertCount(723, $facilityIds->unique());
        $this->assertSame(723, $index->sum(fn (array $row): int => (int) $row['facilities_total']));
        $this->assertSame(723, $index->sum(fn (array $row): int => (int) $row['not_reviewed_count']));
        $this->assertSame(723, $summary['not_reviewed_total']);
        $this->assertSame(723, $summary['queue_records']);
        $this->assertSame(0, $index->sum(fn (array $row): int => (int) $row['reviewed_count']));
    }

    private function city(): City
    {
        return City::firstOrCreate(['slug' => 'potsdam'], [
            'name' => 'Potsdam', 'state' => 'Brandenburg', 'state_slug' => 'brandenburg',
            'geo_match_status' => 'exact', 'geo_requires_manual_review' => false,
        ]);
    }

    private function facility(City $city, array $overrides = []): Facility
    {
        $data = array_merge([
            'source_id' => 'facility-'.uniqid(), 'city_id' => $city->id, 'name' => 'Pflege Beispiel',
            'slug' => 'pflege-beispiel-'.uniqid(), 'postal_code' => '14467', 'street' => 'Marktstraße',
            'house_number' => '1', 'address' => 'Marktstraße 1', 'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'], 'features' => [], 'phone' => '+49 331 123456',
            'email' => 'kontakt@example.org', 'website' => 'https://pflege.example.org/standort',
            'contact_status' => 'verified', 'contact_source' => 'https://pflege.example.org/standort',
            'contact_checked_at' => now()->subMonth(),
        ], $overrides);

        return Facility::unguarded(fn (): Facility => Facility::create($data));
    }

    /** @return array<int, array<string, string>> */
    private function csvRows(string $path): array
    {
        $stream = fopen($path, 'r');
        $headers = fgetcsv($stream);
        $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            $rows[] = array_combine($headers, $values);
        }
        fclose($stream);

        return $rows;
    }
}

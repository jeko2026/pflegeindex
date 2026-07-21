<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Models\GeoCountry;
use App\Models\GeoDistrict;
use App\Models\GeoMunicipality;
use App\Models\GeoState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicPaginationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_public_listings_accept_existing_pages_and_reject_pages_beyond_the_last(): void
    {
        [$city, $district] = $this->createScope();
        $this->createFacilities($city, 49);

        foreach ($this->listingUrls($city, $district) as $url) {
            $this->get($url)->assertOk();
            $this->get($url.'?page=1')->assertOk();
            $this->get($url.'?page=2')->assertOk();
            $this->get($url.'?page=3')->assertOk();
            $this->get($url.'?page=4')->assertNotFound();
            $this->get($url.'?page=999999')->assertNotFound();
            $this->get($url.'?page=999999999')->assertNotFound();
        }
    }

    public function test_public_listings_reject_malformed_page_values_consistently(): void
    {
        [$city, $district] = $this->createScope();
        $this->createFacilities($city, 1);
        $invalidValues = [
            '0',
            '-1',
            'invalid',
            '1.5',
            str_repeat('9', 100),
            ' 2 ',
            '',
            ['2'],
        ];

        foreach ($this->listingUrls($city, $district) as $url) {
            foreach ($invalidValues as $value) {
                $this->get($url.'?'.http_build_query(['page' => $value]))
                    ->assertNotFound();
            }
        }
    }

    public function test_first_page_remains_valid_when_a_listing_has_no_results(): void
    {
        [$city, $district] = $this->createScope();

        foreach ($this->listingUrls($city, $district) as $url) {
            $this->get($url)->assertOk();
            $this->get($url.'?page=1')->assertOk();
            $this->get($url.'?page=2')->assertNotFound();
        }
    }

    public function test_filtered_directory_keeps_noindex_on_valid_pages_and_rejects_out_of_range_page(): void
    {
        [$city] = $this->createScope();
        $this->createFacilities($city, 25, 'Filter Treffer');
        $this->createFacilities($city, 24, 'Anderes Ergebnis');
        $parameters = [
            'q' => 'Filter',
            'city' => $city->slug,
            'type' => 'Ambulante Pflege',
        ];
        $url = route('directory.index', $parameters);
        $robotsMeta = '<meta name="robots" content="noindex,follow">';

        $this->get($url)
            ->assertOk()
            ->assertSee($robotsMeta, false);
        $this->get($url.'&page=2')
            ->assertOk()
            ->assertSee($robotsMeta, false)
            ->assertSee('Seite 2 von 2');
        $this->get($url.'&page=3')->assertNotFound();
    }

    public function test_extreme_out_of_range_page_skips_the_offset_query(): void
    {
        [$city] = $this->createScope();
        $this->createFacilities($city, 1);
        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('directory.index', ['page' => 999999999]))
            ->assertNotFound();

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('count(', strtolower($queries[0]));
        $this->assertStringNotContainsString('offset', strtolower($queries[0]));
    }

    /** @return array{City, GeoDistrict} */
    private function createScope(): array
    {
        $country = GeoCountry::create([
            'iso2' => 'DE',
            'iso3' => 'DEU',
            'name' => 'Deutschland',
            'slug' => 'deutschland',
        ]);
        $state = GeoState::create([
            'country_id' => $country->id,
            'ags' => '12',
            'name' => 'Brandenburg',
            'slug' => 'brandenburg',
        ]);
        $district = GeoDistrict::create([
            'state_id' => $state->id,
            'ags' => '12060',
            'name' => 'Testkreis',
            'slug' => 'testkreis',
            'type' => 'landkreis',
        ]);
        $municipality = GeoMunicipality::create([
            'district_id' => $district->id,
            'ags' => '12060001',
            'name' => 'Teststadt',
            'normalized_name' => 'teststadt',
            'slug' => 'teststadt',
            'municipality_type' => 'Stadt',
            'source_name' => 'Pagination Hardening Test',
        ]);
        $city = City::create([
            'name' => 'Teststadt',
            'slug' => 'teststadt',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
            'geo_municipality_id' => $municipality->id,
        ]);

        return [$city, $district];
    }

    /** @return list<string> */
    private function listingUrls(City $city, GeoDistrict $district): array
    {
        return [
            route('cities.show', $city),
            route('districts.show', $district->slug),
            route('region.show'),
            route('directory.index'),
        ];
    }

    private function createFacilities(City $city, int $count, string $prefix = 'Einrichtung'): void
    {
        foreach (range(1, $count) as $number) {
            $this->sequence++;
            Facility::create([
                'source_id' => "pagination-hardening-{$this->sequence}",
                'city_id' => $city->id,
                'name' => sprintf('%s %03d', $prefix, $number),
                'slug' => "pagination-hardening-{$this->sequence}",
                'postal_code' => '14467',
                'address' => "Musterstraße {$this->sequence}",
                'type' => 'Ambulante Pflege',
                'care_types' => ['Ambulante Pflege'],
                'features' => [],
            ]);
        }
    }
}

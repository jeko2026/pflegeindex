<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contains_public_directory_pages_only(): void
    {
        $cityUpdatedAt = Carbon::parse('2026-07-17T10:15:00+00:00');
        $firstFacilityUpdatedAt = Carbon::parse('2026-07-18T08:30:00+00:00');
        $secondFacilityUpdatedAt = Carbon::parse('2026-07-18T09:45:00+00:00');
        $city = City::create([
            'name' => 'Potsdam',
            'slug' => 'potsdam',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);
        $facility = Facility::create([
            'source_id' => 'sitemap-example-14467',
            'city_id' => $city->id,
            'name' => 'Sitemap Pflegezentrum',
            'slug' => 'sitemap-pflegezentrum-14467',
            'postal_code' => '14467',
            'address' => 'Musterstraße 1',
            'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'],
            'features' => [],
        ]);
        $secondFacility = Facility::create([
            'source_id' => 'sitemap-new-14467',
            'city_id' => $city->id,
            'name' => 'Neues Pflegezentrum',
            'slug' => 'neues-pflegezentrum-14467',
            'postal_code' => '14467',
            'address' => 'Parkstraße 2',
            'type' => 'Stationäre Pflege',
            'care_types' => ['Stationäre Pflege'],
            'features' => [],
        ]);

        City::query()->whereKey($city->id)->update(['updated_at' => $cityUpdatedAt]);
        Facility::query()->whereKey($facility->id)->update(['updated_at' => $firstFacilityUpdatedAt]);
        Facility::query()->whereKey($secondFacility->id)->update(['updated_at' => $secondFacilityUpdatedAt]);
        $expectedCityLastmod = $city->fresh()->updated_at->toAtomString();
        $expectedFirstFacilityLastmod = $facility->fresh()->updated_at->toAtomString();
        $expectedSecondFacilityLastmod = $secondFacility->fresh()->updated_at->toAtomString();

        $response = $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee(route('home'), false)
            ->assertSee(route('cities.show', $city), false)
            ->assertSee(route('facilities.show', [$city, $facility]), false)
            ->assertSee(route('facilities.show', [$city, $secondFacility]), false)
            ->assertSee($expectedCityLastmod, false)
            ->assertSee($expectedFirstFacilityLastmod, false)
            ->assertSee($expectedSecondFacilityLastmod, false)
            ->assertDontSee(route('pages.imprint'), false)
            ->assertDontSee(route('pages.privacy'), false)
            ->assertDontSee('/admin', false);

        $this->assertNotFalse(simplexml_load_string($response->getContent()));
    }

    public function test_robots_file_points_to_the_sitemap(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *')
            ->assertSee('Sitemap: '.route('sitemap'));
    }
}

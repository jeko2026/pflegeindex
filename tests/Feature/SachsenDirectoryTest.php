<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Models\FacilitySocialLink;
use App\Models\FacilitySource;
use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SachsenDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sachsen_pages_keep_social_links_out_of_facility_and_support_service_taxonomy(): void
    {
        $city = City::create(['name' => 'Görlitz', 'slug' => 'gorlitz', 'state' => 'Sachsen', 'state_slug' => 'sachsen']);
        $facility = Facility::create(['source_id' => 'gmaps-place-test', 'city_id' => $city->id, 'name' => 'Pflege Görlitz', 'slug' => 'pflege-gorlitz-02826', 'postal_code' => '02826', 'address' => 'Teststraße 1', 'type' => 'Pflegeeinrichtung', 'google_place_id' => 'place-test', 'is_active' => true]);
        $service = ServiceType::create(['slug' => 'ambulante_pflege', 'name' => 'Ambulante Pflege']);
        $facility->serviceTypes()->attach($service);
        FacilitySource::create(['facility_id' => $facility->id, 'source_type' => 'google_maps', 'source_name' => 'Google Maps Extractor', 'source_record_id' => 'test.csv#2', 'first_seen_at' => now(), 'last_seen_at' => now(), 'imported_at' => now()]);
        FacilitySocialLink::create(['facility_id' => $facility->id, 'platform' => 'facebook', 'url' => 'https://facebook.com/pflege-goerlitz', 'source_type' => 'google_maps']);

        $this->get('/sachsen/gorlitz.html')->assertOk()->assertSee('Pflege Görlitz');
        $this->get('/sachsen/gorlitz/ambulante_pflege.html')->assertOk();
        $this->get('/pflegeeinrichtungen/sachsen/gorlitz/pflege-gorlitz-02826')->assertOk()->assertSee('Online &amp; Social', false)->assertSee('facebook.com/pflege-goerlitz');
        $this->assertDatabaseHas('facility_sources', ['facility_id' => $facility->id, 'source_type' => 'google_maps']);
    }
}

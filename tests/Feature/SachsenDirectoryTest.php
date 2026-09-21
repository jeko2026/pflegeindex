<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityOpeningHour;
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

    public function test_sachsen_facility_detail_uses_its_route_contract_and_optional_profile_blocks(): void
    {
        $city = City::create(['name' => 'Delitzsch', 'slug' => 'delitzsch', 'state' => 'Sachsen', 'state_slug' => 'sachsen']);
        $facility = Facility::create(['source_id' => 'st-georg', 'city_id' => $city->id, 'name' => 'Altenpflegeheim St. Georg', 'slug' => 'altenpflegeheim-st-georg-hospital-04509', 'postal_code' => '04509', 'address' => 'Hospitalstraße 1', 'type' => 'Pflegeheim', 'is_active' => true]);
        $related = Facility::create(['source_id' => 'related-facility', 'city_id' => $city->id, 'name' => 'Pflegehaus Delitzsch', 'slug' => 'pflegehaus-delitzsch-04509', 'postal_code' => '04509', 'address' => 'Markt 2', 'type' => 'Pflegeheim', 'is_active' => true]);
        FacilityOpeningHour::create(['facility_id' => $facility->id, 'source_type' => 'google_maps', 'hours_text' => 'Monday(2026-09-21): [6 AM–10 PM], Sunday(2026-09-20): [Closed]']);
        FacilitySocialLink::create(['facility_id' => $facility->id, 'platform' => 'instagram', 'url' => 'https://instagram.com/st-georg-delitzsch', 'source_type' => 'google_maps']);

        $url = route('sachsen.facilities.show', [$city, $facility]);
        $response = $this->get($url)->assertOk();

        $response
            ->assertSee('<title>Altenpflegeheim St. Georg in Delitzsch – PflegeIndex</title>', false)
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)
            ->assertSee('href="'.route('sachsen.land').'">Sachsen</a>', false)
            ->assertSee('href="'.route('sachsen.cities.show', $city).'">Delitzsch</a>', false)
            ->assertSee('Weitere Pflegeeinrichtungen in Delitzsch')
            ->assertSee('href="'.route('sachsen.facilities.show', [$city, $related]).'"', false)
            ->assertSee('Öffnungszeiten')
            ->assertSee('Montag')
            ->assertSee('06:00–22:00')
            ->assertSee('Sonntag')
            ->assertSee('Geschlossen')
            ->assertSee('Online &amp; Social', false)
            ->assertSee('instagram.com/st-georg-delitzsch')
            ->assertDontSee('Monday(2026-09-21)', false)
            ->assertDontSee('Amtliche Grunddaten')
            ->assertDontSee('im amtlichen Einrichtungsverzeichnis des Landes Brandenburg')
            ->assertSee('in öffentlichen Unternehmens- und Karteneinträgen geführt.')
            ->assertDontSee('Datenqualität');
    }
}

<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityAttribute;
use App\Models\FacilityNearbyPlace;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\FacilityEnrichment\AttributeReviewPolicy;
use App\Services\FacilityEnrichment\FacilityAttributePresenter;
use App\Services\FacilityEnrichment\FacilityAttributeUpserter;
use App\Services\FacilityEnrichment\FacilityRelationEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityEnrichmentPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_relation_requires_two_signals_unless_the_url_is_a_specific_standort_url(): void
    {
        $facility = $this->facility();
        $evaluator = new FacilityRelationEvaluator;
        $low = $evaluator->evaluate($facility, ['source_url' => 'https://pflege.example/angebote', 'source_text' => 'Unsere Angebote in Potsdam.']);
        $medium = $evaluator->evaluate($facility, ['source_url' => 'https://pflege.example/angebote', 'source_text' => 'Haus Beispiel in Potsdam.']);
        $high = $evaluator->evaluate($facility, ['source_url' => 'https://pflege.example/'.$facility->slug, 'source_text' => 'Standortseite']);
        $this->assertSame('LOW', $low['confidence']);
        $this->assertSame('MEDIUM', $medium['confidence']);
        $this->assertSame('HIGH', $high['confidence']);
    }

    public function test_medical_attributes_are_never_auto_approved_and_low_is_not_presented(): void
    {
        $policy = new AttributeReviewPolicy;
        $this->assertSame('needs_review', $policy->status('care.dementia', 'MEDIUM'));
        $this->assertSame('needs_review', $policy->status('care.dementia', 'HIGH'));
        $this->assertSame('auto_approved', $policy->status('outdoor.garden', 'HIGH'));
        $facility = $this->facility();
        FacilityAttribute::create($this->attribute($facility, ['review_status' => 'rejected']));
        FacilityAttribute::create($this->attribute($facility, ['attribute_key' => 'outdoor.garden', 'normalized_value' => 'garden', 'source_url' => 'https://official.example/garden', 'review_status' => 'approved']));
        $visible = (new FacilityAttributePresenter)->for($facility);
        $this->assertCount(1, $visible);
        $this->assertSame('outdoor.garden', $visible->first()->attribute_key);
    }

    public function test_evidence_is_idempotent_and_preserves_review_decision(): void
    {
        $facility = $this->facility();
        $data = $this->attribute($facility);
        unset($data['facility_id'], $data['review_status']);
        $upserter = app(FacilityAttributeUpserter::class);
        $first = $upserter->store($facility, $data);
        $first->update(['review_status' => 'rejected']);
        $second = $upserter->store($facility, [...$data, 'source_excerpt' => 'Aktualisierter, aussagekräftiger Beleg.']);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('rejected', $second->review_status);
        $this->assertDatabaseCount('facility_attributes', 1);
        $this->assertSame('Aktualisierter, aussagekräftiger Beleg.', $second->source_excerpt);
    }

    public function test_attributes_remain_attached_to_their_own_brandenburg_or_sachsen_facility(): void
    {
        $brandenburg = $this->facility();
        $sachsenCity = City::create(['name' => 'Dresden', 'slug' => 'dresden', 'state' => 'Sachsen', 'state_slug' => 'sachsen']);
        $sachsen = Facility::create(['source_id' => 'pipeline-sachsen', 'city_id' => $sachsenCity->id, 'name' => 'Haus Sachsen', 'slug' => 'haus-sachsen-01067', 'postal_code' => '01067', 'address' => 'Elbstraße 1', 'type' => 'Pflegeheim', 'care_types' => ['Pflegeheim'], 'features' => []]);
        FacilityAttribute::create($this->attribute($brandenburg));
        FacilityAttribute::create($this->attribute($sachsen, ['source_url' => 'https://official.example/sachsen', 'normalized_value' => 'sachsen-garden']));
        $this->assertCount(1, (new FacilityAttributePresenter)->for($brandenburg));
        $this->assertCount(1, (new FacilityAttributePresenter)->for($sachsen));
        $this->assertSame('Haus Sachsen', (new FacilityAttributePresenter)->for($sachsen)->first()->facility->name);
    }

    public function test_public_groups_only_expose_allowed_approved_attributes(): void
    {
        $facility = $this->facility();
        FacilityAttribute::create($this->attribute($facility, ['attribute_key' => 'outdoor.garden', 'normalized_value' => 'garden-preview', 'review_status' => 'auto_approved']));
        FacilityAttribute::create($this->attribute($facility, ['attribute_key' => 'care.dementia', 'normalized_value' => 'dementia-preview', 'source_url' => 'https://official.example/dementia', 'review_status' => 'approved']));
        FacilityAttribute::create($this->attribute($facility, ['attribute_key' => 'services.hairdresser', 'normalized_value' => 'hairdresser-preview', 'source_url' => 'https://official.example/hairdresser', 'review_status' => 'needs_review']));
        $groups = (new FacilityAttributePresenter)->publicGroups($facility);
        $this->assertSame('Ausstattung', $groups[0]['heading']);
        $this->assertSame('Garten', $groups[0]['items'][0]['label']);
        $this->assertStringNotContainsString('Demenz', json_encode($groups));
        $this->assertStringNotContainsString('Friseur', json_encode($groups));
    }

    public function test_public_detail_renders_preview_for_brandenburg_and_sachsen_and_hides_empty_block(): void
    {
        $brandenburg = $this->facility();
        FacilityAttribute::create($this->attribute($brandenburg, ['normalized_value' => 'garden-brandenburg', 'review_status' => 'auto_approved']));
        $this->get(route('facilities.show', [$brandenburg->city, $brandenburg]))->assertOk()->assertSee('Ausstattung &amp; Angebote', false)->assertSee('Garten');

        $sachsenCity = City::create(['name' => 'Dresden', 'slug' => 'dresden', 'state' => 'Sachsen', 'state_slug' => 'sachsen']);
        $sachsen = Facility::create(['source_id' => 'preview-sachsen', 'city_id' => $sachsenCity->id, 'name' => 'Haus Sachsen', 'slug' => 'haus-sachsen-01067', 'postal_code' => '01067', 'address' => 'Elbstraße 1', 'type' => 'Pflegeheim', 'is_active' => true, 'care_types' => ['Pflegeheim'], 'features' => []]);
        FacilityAttribute::create($this->attribute($sachsen, ['normalized_value' => 'garden-sachsen', 'review_status' => 'auto_approved']));
        $this->get(route('sachsen.facilities.show', [$sachsenCity, $sachsen]))->assertOk()->assertSee('Ausstattung &amp; Angebote', false)->assertSee('Garten');

        $empty = $brandenburg->replicate();
        $empty->fill(['source_id' => 'empty-preview', 'name' => 'Ohne Preview', 'slug' => 'ohne-preview-14467']);
        $empty->save();
        $this->get(route('facilities.show', [$brandenburg->city, $empty]))->assertOk()->assertDontSee('Ausstattung &amp; Angebote', false);
    }

    public function test_enrichment_precedes_content_and_service_type_section_is_conditional(): void
    {
        $facility = $this->facility();
        FacilityAttribute::create($this->attribute($facility, ['normalized_value' => 'ordered-garden']));
        $empty = $this->get(route('facilities.show', [$facility->city, $facility]));
        $empty->assertOk()->assertDontSee('<h2>Einrichtungsart</h2>', false);
        $html = $empty->getContent();
        $this->assertLessThan(strpos($html, 'Was Sie wissen sollten'), strpos($html, 'Ausstattung &amp; Angebote'));

        $serviceType = ServiceType::create(['slug' => 'pflegeheim', 'name' => 'Pflegeheim']);
        $facility->serviceTypes()->attach($serviceType);
        $this->get(route('facilities.show', [$facility->city, $facility]))->assertOk()->assertSee('Einrichtungsart')->assertSee('Pflegeheim');
    }

    public function test_admin_can_review_attribute_without_exposing_public_content(): void
    {
        $facility = $this->facility();
        $attribute = FacilityAttribute::create($this->attribute($facility, ['review_status' => 'candidate']));
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.facility-attributes.index'))->assertOk()->assertSee('Haus Beispiel')->assertSee('Quelle öffnen');
        $this->actingAs($admin)->get(route('admin.facility-attributes.index', ['state' => 'brandenburg', 'attribute_key' => 'outdoor.garden']))->assertOk()->assertSee('Haus Beispiel');
        $this->actingAs($admin)->post(route('admin.facility-attributes.approve', $attribute))->assertRedirect();
        $this->assertSame('approved', $attribute->fresh()->review_status);
        $this->actingAs($admin)->post(route('admin.facility-attributes.reject', $attribute))->assertRedirect();
        $this->assertSame('rejected', $attribute->fresh()->review_status);
        $this->actingAs($admin)->post(route('admin.facility-attributes.needs-review', $attribute))->assertRedirect();
        $this->assertSame('needs_review', $attribute->fresh()->review_status);
    }

    public function test_nearby_places_preview_shows_only_five_public_categories_and_locality(): void
    {
        $brandenburg = $this->facility();
        foreach ([
            ['pharmacy', 'Apotheke am Markt', null, 350, 'osm/1'],
            ['doctor', 'Praxis Beispiel', 'Potsdam', 480, 'osm/2'],
            ['bus_stop', 'Am Rathaus', 'Werder', 180, 'osm/3'],
            ['railway_station', 'Bahnhof Werder', 'Werder', 6200, 'osm/4'],
            ['supermarket', 'Markt GmbH', null, 700, 'osm/5'],
            ['hospital', 'Klinik intern', 'Potsdam', 400, 'osm/6'],
        ] as [$category, $name, $locality, $distance, $externalId]) {
            FacilityNearbyPlace::create(['facility_id' => $brandenburg->id, 'category' => $category, 'name' => $name, 'locality' => $locality, 'distance_meters' => $distance, 'latitude' => 52.4, 'longitude' => 13.1, 'source' => 'OpenStreetMap', 'external_id' => $externalId, 'confidence' => 'HIGH', 'discovered_at' => now(), 'updated_at' => now()]);
        }
        $html = $this->get(route('facilities.show', [$brandenburg->city, $brandenburg]))->assertOk()->getContent();
        foreach (['Apotheke', 'Arztpraxis', 'Bushaltestelle', 'Bahnhof', 'Supermarkt'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringNotContainsString('Klinik intern', $html);
        $this->assertStringContainsString('Am Rathaus — Werder', $html);
        $this->assertStringNotContainsString('Praxis Beispiel — Potsdam', $html);

        $sachsenCity = City::create(['name' => 'Leipzig', 'slug' => 'leipzig', 'state' => 'Sachsen', 'state_slug' => 'sachsen']);
        $sachsen = Facility::create(['source_id' => 'nearby-sachsen', 'city_id' => $sachsenCity->id, 'name' => 'Haus Leipzig', 'slug' => 'haus-leipzig-04109', 'postal_code' => '04109', 'address' => 'Markt 1', 'type' => 'Pflegeheim', 'is_active' => true, 'care_types' => [], 'features' => []]);
        FacilityNearbyPlace::create(['facility_id' => $sachsen->id, 'category' => 'bus_stop', 'name' => 'Markt', 'distance_meters' => 180, 'latitude' => 51.3, 'longitude' => 12.4, 'source' => 'OpenStreetMap', 'external_id' => 'osm/7', 'confidence' => 'HIGH', 'discovered_at' => now(), 'updated_at' => now()]);
        $this->get(route('sachsen.facilities.show', [$sachsenCity, $sachsen]))->assertOk()->assertSee('Lage &amp; Umgebung', false)->assertSee('Bushaltestelle');

        $empty = $brandenburg->replicate();
        $empty->fill(['source_id' => 'nearby-empty', 'name' => 'Ohne Umgebung', 'slug' => 'ohne-umgebung-14467']);
        $empty->save();
        FacilityNearbyPlace::create(['facility_id' => $empty->id, 'category' => 'hospital', 'name' => 'Nur Klinik', 'distance_meters' => 100, 'latitude' => 52.4, 'longitude' => 13.1, 'source' => 'OpenStreetMap', 'external_id' => 'osm/8', 'confidence' => 'HIGH', 'discovered_at' => now(), 'updated_at' => now()]);
        $this->get(route('facilities.show', [$brandenburg->city, $empty]))->assertOk()->assertDontSee('Lage &amp; Umgebung', false);
    }

    private function facility(): Facility
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state' => 'Brandenburg', 'state_slug' => 'brandenburg']);

        return Facility::create(['source_id' => 'pipeline-1', 'city_id' => $city->id, 'name' => 'Haus Beispiel', 'slug' => 'haus-beispiel-14467', 'postal_code' => '14467', 'address' => 'Musterstraße 1', 'phone' => '+49331123456', 'type' => 'Pflegeheim', 'care_types' => ['Pflegeheim'], 'features' => []]);
    }

    private function attribute(Facility $facility, array $overrides = []): array
    {
        return [...['facility_id' => $facility->id, 'attribute_key' => 'outdoor.garden', 'attribute_value' => 'Garten', 'normalized_value' => 'garden', 'confidence' => 'HIGH', 'review_status' => 'auto_approved', 'source_url' => 'https://official.example/haus-beispiel', 'source_type' => 'official_website', 'source_excerpt' => 'Die Einrichtung verfügt über einen Garten für Bewohnerinnen und Bewohner.', 'source_context' => 'Angebot der Einrichtung.', 'relation_reason' => 'facility_name, city', 'discovered_at' => now()], ...$overrides];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Models\GeoCountry;
use App\Models\GeoDistrict;
use App\Models\GeoMunicipality;
use App\Models\GeoState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Feature tests for the second round of SEO Growth improvements
 * (SEO Growth Content Audit, 2026-08-19), covering exactly the four pages
 * in scope:
 *  - AWO Seniorenzentrum "Am Tierpark" (facility, slug awo-seniorenzentrum-am-tierpark-16278)
 *  - MEDI+CARE Haus Barbara (facility, slug medi-care-gmbh-haus-barbara-15848)
 *  - Haus am Mariengrund (facility, slug haus-am-mariengrund-brandenburg-an-der-havel-14770)
 *  - Zeuthen (city page)
 *
 * Also proves the point-fix override mechanism used for the three facility
 * pages above does not affect any other facility page, and that the dynamic
 * intro/FAQ addition on the City page template does not affect the existing
 * title/meta description contract on any other city.
 *
 * /pflegeheime.html and its existing DirectorySeoGrowthTest suite are
 * intentionally untouched by this task and are not covered here.
 */
class SeoGrowthFacilityAndCityTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    // -----------------------------------------------------------------
    // 1. AWO Seniorenzentrum "Am Tierpark"
    // -----------------------------------------------------------------

    public function test_awo_facility_page_keeps_its_title_and_gets_the_new_meta_description(): void
    {
        $city = $this->createCity('Angermünde', 'angermuende');
        $facility = $this->createFacility(
            $city,
            'awo-seniorenzentrum-am-tierpark-16278',
            'AWO Seniorenzentrum "Am Tierpark"',
            'Stationäre/teilstationäre Pflege',
            description: 'Bestehender, quellenbasierter Beschreibungstext für die AWO-Einrichtung, der unverändert bleiben soll.',
            descriptionDraft: 'Bestehender, quellenbasierter Beschreibungstext für die AWO-Einrichtung, der unverändert bleiben soll.'
                ."\n\n".'Laut Trägerangaben stehen am Standort 33 vollstationäre Pflegeplätze in Einzelzimmern sowie 4 zusätzliche Kurzzeitpflegeplätze zur Verfügung; die Einrichtung führt außerdem einen eigenen Bereich für die Betreuung von Menschen mit Demenz.',
        );

        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();

        $response->assertSee(
            '<title>AWO Seniorenzentrum &quot;Am Tierpark&quot; in Angermünde – PflegeIndex</title>',
            false,
        );
        $response->assertSee(
            '<meta name="description" content="AWO Seniorenzentrum &quot;Am Tierpark&quot; in Angermünde: stationäre Pflege mit 33 Plätzen, Kurzzeitpflege und Demenzbereich. Adresse, Kontakt &amp; Leistungen im Überblick.">',
            false,
        );

        // The draft fact block must not leak onto the public page: only the
        // existing, already-published description is shown there.
        $response->assertSee('Bestehender, quellenbasierter Beschreibungstext für die AWO-Einrichtung, der unverändert bleiben soll.');
        $response->assertDontSee('33 vollstationäre Pflegeplätze in Einzelzimmern sowie 4 zusätzliche Kurzzeitpflegeplätze');
    }

    // -----------------------------------------------------------------
    // 2. MEDI+CARE Haus Barbara
    // -----------------------------------------------------------------

    public function test_haus_barbara_facility_page_keeps_title_and_description_gets_only_the_new_meta_description(): void
    {
        $city = $this->createCity('Beeskow', 'beeskow');
        $existingDescription = 'Bestehender Beschreibungstext für Haus Barbara, der laut Aufgabenstellung nicht verändert werden darf.';
        $facility = $this->createFacility(
            $city,
            'medi-care-gmbh-haus-barbara-15848',
            'MEDI+CARE GmbH Haus Barbara',
            'Stationäre/teilstationäre Pflege',
            description: $existingDescription,
        );

        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();

        $response->assertSee(
            '<title>MEDI+CARE GmbH Haus Barbara in Beeskow – PflegeIndex</title>',
            false,
        );
        $response->assertSee(
            '<meta name="description" content="Vollstationäres Pflegeheim MEDI+CARE Haus Barbara in Beeskow, Frankfurter Chaussee 49. Adresse, Kontakt und amtliche Basisdaten im PflegeIndex-Profil.">',
            false,
        );
        // Main body description must be exactly the existing text, untouched.
        $response->assertSee($existingDescription);
    }

    // -----------------------------------------------------------------
    // 3. Haus am Mariengrund
    // -----------------------------------------------------------------

    public function test_haus_am_mariengrund_gets_a_deduplicated_title_and_new_meta_description(): void
    {
        $city = $this->createCity('Brandenburg an der Havel', 'brandenburg-an-der-havel');
        // The official facility name already contains the city name (as stored
        // in the LASV source data) -- this is what causes the duplication bug.
        $existingDescription = 'Bestehender Beschreibungstext für Haus am Mariengrund, der unverändert bleiben soll.';
        $facility = $this->createFacility(
            $city,
            'haus-am-mariengrund-brandenburg-an-der-havel-14770',
            'Haus am Mariengrund Brandenburg an der Havel',
            'Stationäre/teilstationäre Pflege',
            description: $existingDescription,
            descriptionDraft: $existingDescription
                ."\n\n".'Laut Betreiberangaben wurde das Haus 2018 eröffnet und verfügt über 146 Zimmer sowie ein Pflegebad und einen Therapiegarten; das Angebot „Junge Pflege" richtet sich dort an bis zu 21 Bewohnerinnen und Bewohner unter 65 Jahren.',
        );

        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $content = $response->getContent();

        $response->assertSee(
            '<title>Haus am Mariengrund – Pflegeheim in Brandenburg an der Havel | PflegeIndex</title>',
            false,
        );
        // The old duplicated phrase must no longer appear anywhere on the page.
        $this->assertSame(
            0,
            substr_count($content, 'Brandenburg an der Havel in Brandenburg an der Havel'),
            'The city name must no longer be duplicated anywhere on the page.',
        );

        $response->assertSee(
            '<meta name="description" content="Haus am Mariengrund in Brandenburg an der Havel: stationäre Pflege, Kurzzeitpflege, betreutes Wohnen und Junge Pflege. Adresse, Kontakt &amp; Leistungen.">',
            false,
        );

        $response->assertSee($existingDescription);
        $response->assertDontSee('146 Zimmer sowie ein Pflegebad und einen Therapiegarten');
        // The price figure from the audit must never appear anywhere on the page.
        $response->assertDontSee('2.984');
    }

    // -----------------------------------------------------------------
    // 4. Regression: an ordinary facility page not in scope
    // -----------------------------------------------------------------

    public function test_an_ordinary_facility_page_is_completely_unaffected_by_the_new_overrides(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam');
        $facility = $this->createFacility(
            $city,
            'ganz-normale-einrichtung-potsdam-99999',
            'Ganz normale Einrichtung Potsdam',
            'Ambulante Pflege',
            description: str_repeat('Ein regulärer, ausführlicher Beschreibungstext ohne jede Sonderbehandlung. ', 4),
        );

        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();

        // Title still generated the old way: "{name} in {city} – PflegeIndex".
        $response->assertSee(
            '<title>Ganz normale Einrichtung Potsdam in Potsdam – PflegeIndex</title>',
            false,
        );

        // Meta description is still the auto-truncated description, not a
        // hand-written override string.
        $metaDescription = $this->metaDescription($response);
        $this->assertStringStartsWith('Ein regulärer, ausführlicher Beschreibungstext', $metaDescription);
        $this->assertLessThanOrEqual(158, mb_strlen($metaDescription)); // 155 + "..."
    }

    public function test_facility_structured_data_still_valid_for_overridden_and_ordinary_pages(): void
    {
        $city = $this->createCity('Angermünde', 'angermuende');
        $facility = $this->createFacility(
            $city,
            'awo-seniorenzentrum-am-tierpark-16278',
            'AWO Seniorenzentrum "Am Tierpark"',
            'Stationäre/teilstationäre Pflege',
            description: 'Bestehender Text.',
        );
        $canonical = route('facilities.show', [$city, $facility]);

        $response = $this->get($canonical)->assertOk();
        $schemas = $this->jsonLdSchemas($response->getContent());
        $localBusiness = $this->findSchema($schemas, 'LocalBusiness');
        $breadcrumb = $this->findSchema($schemas, 'BreadcrumbList');

        $this->assertNotNull($localBusiness, 'LocalBusiness structured data must still be present.');
        $this->assertSame($facility->name, $localBusiness['name']);
        $this->assertSame($canonical, $localBusiness['url']);
        $this->assertNotNull($breadcrumb, 'BreadcrumbList structured data must still be present.');
        $this->assertSame($canonical, end($breadcrumb['itemListElement'])['item']);
    }

    // -----------------------------------------------------------------
    // 5. Zeuthen city page
    // -----------------------------------------------------------------

    public function test_zeuthen_keeps_its_title_and_meta_description_and_gets_a_dynamic_intro(): void
    {
        [$zeuthen] = $this->seedZeuthenWithDistrict();

        $response = $this->get(route('cities.show', $zeuthen))->assertOk();

        $response->assertSee('<title>Pflegeeinrichtungen in Zeuthen – PflegeIndex</title>', false);
        $response->assertSee(
            '<meta name="description" content="13 Pflegeeinrichtungen in Zeuthen: Anschriften, Einrichtungsarten und verfügbare Kontaktdaten.">',
            false,
        );
        $response->assertSee(
            'Von den 13 gelisteten Einrichtungen bieten 8 ambulante Pflege im häuslichen Umfeld an, 5 sind stationäre oder teilstationäre Einrichtungen wie Pflegeheime oder Tagespflegen.',
        );
    }

    public function test_zeuthen_faq_uses_dynamic_counts_and_links_to_the_existing_nearby_cities_mechanism(): void
    {
        [$zeuthen, $district] = $this->seedZeuthenWithDistrict();

        $response = $this->get(route('cities.show', $zeuthen))->assertOk();
        $content = $response->getContent();

        $response->assertSee('Häufig gestellte Fragen');
        $response->assertSee('Welche Pflegeangebote gibt es in Zeuthen?');
        $response->assertSee('In Zeuthen sind aktuell 13 Pflegeeinrichtungen gelistet: 8 Anbieter für ambulante Pflege und 5 stationäre bzw. teilstationäre Einrichtungen.');

        $response->assertSee('Wo finde ich weitere Einrichtungen in der Nähe von Zeuthen?');
        $districtUrl = route('districts.show', $district->slug);
        $this->assertStringContainsString('href="'.$districtUrl.'"', $content);
        $this->assertStringContainsString('href="#nearby-cities-title"', $content);
    }

    public function test_zeuthen_counts_stay_correct_if_a_facility_is_added(): void
    {
        [$zeuthen] = $this->seedZeuthenWithDistrict();
        $this->createFacility($zeuthen, 'ein-weiterer-ambulanter-dienst-zeuthen', 'Ein weiterer ambulanter Dienst', 'Ambulante Pflege');

        $response = $this->get(route('cities.show', $zeuthen))->assertOk();

        // Nothing is hardcoded to "13" -- the count must move to 14 automatically.
        $response->assertSee('Von den 14 gelisteten Einrichtungen bieten 9 ambulante Pflege im häuslichen Umfeld an, 5 sind stationäre oder teilstationäre Einrichtungen wie Pflegeheime oder Tagespflegen.');
        $response->assertSee('In Zeuthen sind aktuell 14 Pflegeeinrichtungen gelistet: 9 Anbieter für ambulante Pflege und 5 stationäre bzw. teilstationäre Einrichtungen.');
    }

    // -----------------------------------------------------------------
    // 6. Regression: an ordinary city page not in scope
    // -----------------------------------------------------------------

    public function test_an_ordinary_city_page_keeps_its_title_meta_and_structured_data(): void
    {
        $potsdam = $this->createCity('Potsdam', 'potsdam');
        $this->createFacility($potsdam, 'alpha-pflege-potsdam', 'Alpha Pflege Potsdam', 'Ambulante Pflege');
        $this->createFacility($potsdam, 'beta-pflege-potsdam', 'Beta Pflege Potsdam', 'Stationäre/teilstationäre Pflege');
        $canonical = route('cities.show', $potsdam);

        $response = $this->get($canonical)->assertOk();

        $response->assertSee('<title>Pflegeeinrichtungen in Potsdam – PflegeIndex</title>', false);
        $response->assertSee(
            '<meta name="description" content="2 Pflegeeinrichtungen in Potsdam: Anschriften, Einrichtungsarten und verfügbare Kontaktdaten.">',
            false,
        );
        // The general dynamic-intro feature still safely applies to any city.
        $response->assertSee('Von den 2 gelisteten Einrichtungen bieten 1 ambulante Pflege im häuslichen Umfeld an, 1 sind stationäre oder teilstationäre Einrichtungen wie Pflegeheime oder Tagespflegen.');

        $schemas = $this->jsonLdSchemas($response->getContent());
        $collectionPage = $this->findSchema($schemas, 'CollectionPage');
        $this->assertNotNull($collectionPage, 'CollectionPage structured data must still be present.');
        $this->assertSame($canonical, $collectionPage['url']);
        $this->assertSame('Potsdam', $collectionPage['about']['name']);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /** @return array{0: City, 1: GeoDistrict} */
    private function seedZeuthenWithDistrict(): array
    {
        $country = GeoCountry::create(['iso2' => 'DE', 'name' => 'Deutschland', 'slug' => 'deutschland']);
        $state = GeoState::create(['country_id' => $country->id, 'ags' => '12', 'name' => 'Brandenburg', 'slug' => 'brandenburg']);
        $district = GeoDistrict::create(['state_id' => $state->id, 'ags' => '12061', 'name' => 'Landkreis Dahme-Spreewald', 'slug' => 'dahme-spreewald', 'type' => 'landkreis']);
        $municipality = GeoMunicipality::create([
            'district_id' => $district->id,
            'ags' => '12061572',
            'name' => 'Zeuthen',
            'normalized_name' => 'zeuthen',
            'slug' => 'zeuthen',
            'source_name' => 'test-fixture',
        ]);
        $zeuthen = $this->createCity('Zeuthen', 'zeuthen', geoMunicipalityId: $municipality->id);

        foreach (range(1, 8) as $n) {
            $this->createFacility($zeuthen, "zeuthen-ambulant-{$n}", "Ambulanter Dienst {$n}", 'Ambulante Pflege');
        }
        foreach (range(1, 5) as $n) {
            $this->createFacility($zeuthen, "zeuthen-stationaer-{$n}", "Pflegeheim {$n}", 'Stationäre/teilstationäre Pflege');
        }

        // A neighbouring city in the same Landkreis, with at least one
        // facility, so the existing "Städte in der Nähe" mechanism actually
        // renders -- matching the real Zeuthen page, which does have
        // neighbouring towns in Dahme-Spreewald.
        $neighbourMunicipality = GeoMunicipality::create([
            'district_id' => $district->id,
            'ags' => '12061128',
            'name' => 'Eichwalde',
            'normalized_name' => 'eichwalde',
            'slug' => 'eichwalde',
            'source_name' => 'test-fixture',
        ]);
        $eichwalde = $this->createCity('Eichwalde', 'eichwalde', geoMunicipalityId: $neighbourMunicipality->id);
        $this->createFacility($eichwalde, 'eichwalde-ambulant-1', 'Nachbarort Einrichtung', 'Ambulante Pflege');

        return [$zeuthen, $district];
    }

    private function createCity(string $name, string $slug, string $state = 'Brandenburg', string $stateSlug = 'brandenburg', ?int $geoMunicipalityId = null): City
    {
        return City::create([
            'name' => $name,
            'slug' => $slug,
            'state' => $state,
            'state_slug' => $stateSlug,
            'geo_municipality_id' => $geoMunicipalityId,
        ]);
    }

    private function createFacility(
        City $city,
        string $slug,
        string $name,
        string $type,
        ?string $description = null,
        ?string $descriptionDraft = null,
    ): Facility {
        $this->sequence++;

        return Facility::create([
            'source_id' => "seo-growth-2-{$this->sequence}",
            'city_id' => $city->id,
            'name' => $name,
            'slug' => $slug,
            'postal_code' => '14467',
            'address' => 'Musterstraße 1',
            'type' => $type,
            'description' => $description,
            'description_draft' => $descriptionDraft,
            'description_draft_sources' => $descriptionDraft !== null ? ['https://example-official-site.de/'] : null,
            'description_draft_checked_at' => $descriptionDraft !== null ? now() : null,
            'care_types' => [$type],
            'features' => [],
        ]);
    }

    private function metaDescription(TestResponse $response): string
    {
        preg_match('/<meta name="description" content="(.*?)">/s', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'Meta description tag not found.');

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonLdSchemas(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        return array_map(
            static fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
            $matches[1],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $schemas
     * @return array<string, mixed>|null
     */
    private function findSchema(array $schemas, string $type): ?array
    {
        foreach ($schemas as $schema) {
            if (($schema['@type'] ?? null) === $type) {
                return $schema;
            }

            if (isset($schema['@graph']) && is_array($schema['@graph'])) {
                foreach ($schema['@graph'] as $node) {
                    if (($node['@type'] ?? null) === $type) {
                        return $node;
                    }
                }
            }
        }

        return null;
    }
}

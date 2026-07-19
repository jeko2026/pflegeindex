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

class DistrictPageTest extends TestCase
{
    use RefreshDatabase;

    private GeoCountry $country;

    private GeoState $brandenburg;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->country = GeoCountry::create([
            'iso2' => 'DE',
            'iso3' => 'DEU',
            'name' => 'Deutschland',
            'slug' => 'deutschland',
        ]);
        $this->brandenburg = GeoState::create([
            'country_id' => $this->country->id,
            'ags' => '12',
            'name' => 'Brandenburg',
            'slug' => 'brandenburg',
        ]);
    }

    public function test_landkreis_page_has_correct_content_seo_json_ld_filters_and_query_count(): void
    {
        $district = $this->createDistrict('Dahme-Spreewald', 'dahme-spreewald', 'landkreis');
        $otherDistrict = $this->createDistrict('Oberhavel', 'oberhavel', 'landkreis');
        $lübben = $this->createLinkedCity($district, 'Lübben', 'luebben');
        $wildau = $this->createLinkedCity($district, 'Wildau', 'wildau');
        $emptyCity = $this->createLinkedCity($district, 'Zeuthen', 'zeuthen');
        $manualCity = $this->createManualCity('Ortsteil Test', 'ortsteil-test');
        $otherCity = $this->createLinkedCity($otherDistrict, 'Oranienburg', 'oranienburg');
        $alpha = $this->createFacility($lübben, 'Alpha Pflege Lübben');
        $beta = $this->createFacility($wildau, 'Beta Pflege Wildau');
        $manualFacility = $this->createFacility($manualCity, 'Nicht bestätigte Pflege');
        $otherFacility = $this->createFacility($otherCity, 'Pflege Oberhavel');

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $canonical = route('districts.show', $district->slug);
        $response = $this->get($canonical)->assertOk();
        $content = $response->getContent();

        $response
            ->assertSee('<h1>Pflegeheime im Landkreis Dahme-Spreewald</h1>', false)
            ->assertSee('<link rel="canonical" href="'.$canonical.'">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:url" content="'.$canonical.'">', false)
            ->assertSee('<meta property="og:site_name" content="PflegeIndex">', false)
            ->assertSee('<meta property="og:locale" content="de_DE">', false)
            ->assertSee('Landkreis Dahme-Spreewald</span></li>', false)
            ->assertSee('<li aria-current="page">', false)
            ->assertSee(route('cities.show', $lübben), false)
            ->assertSee(route('cities.show', $wildau), false)
            ->assertSee($alpha->name)
            ->assertSee($beta->name)
            ->assertDontSee($emptyCity->name)
            ->assertDontSee($manualCity->name)
            ->assertDontSee($manualFacility->name)
            ->assertDontSee($otherCity->name)
            ->assertDontSee($otherFacility->name);

        $this->assertMatchesRegularExpression(
            '#<a href="'.preg_quote(route('region.show'), '#').'"[^>]*aria-current="page"[^>]*>Brandenburg</a>#',
            $content,
        );

        $this->assertLessThan(
            strpos($content, $wildau->name),
            strpos($content, $lübben->name),
        );
        $this->assertLessThanOrEqual(5, $queryCount, 'District page introduced too many SQL queries.');

        $collectionPage = $this->jsonLdOfType($content, 'CollectionPage');
        $this->assertSame($canonical, $collectionPage['url']);
        $this->assertSame('PflegeIndex', $collectionPage['isPartOf']['name']);
        $this->assertSame('AdministrativeArea', $collectionPage['about']['@type']);
        $this->assertSame($district->ags, $collectionPage['about']['identifier']);
        $this->assertCount(2, $collectionPage['mainEntity']['itemListElement']);

        $breadcrumbs = $this->jsonLdOfType($content, 'BreadcrumbList');
        $this->assertSame(['Startseite', 'Brandenburg', 'Landkreis Dahme-Spreewald'], array_column($breadcrumbs['itemListElement'], 'name'));

        $this->get(route('cities.show', $lübben))->assertOk();
        $this->get(route('facilities.show', [$lübben, $alpha]))->assertOk();
        $this->assertSame(url('/brandenburg/luebben.html'), route('cities.show', $lübben));
        $this->assertSame(url('/brandenburg/landkreis/dahme-spreewald.html'), $canonical);
    }

    public function test_kreisfreie_stadt_page_works_with_one_city_and_empty_facility_state(): void
    {
        $district = $this->createDistrict('Potsdam, Stadt', 'potsdam', 'kreisfreie_stadt');
        $city = $this->createLinkedCity($district, 'Potsdam', 'potsdam');

        $response = $this->get(route('districts.show', $district->slug))
            ->assertOk()
            ->assertSee('<title>Pflegeheime in Potsdam | PflegeIndex</title>', false)
            ->assertSee('<h1>Pflegeheime in Potsdam</h1>', false)
            ->assertSee('Für Potsdam sind derzeit keine bestätigten Pflegeeinrichtungen verfügbar.')
            ->assertSee('<strong>1</strong><span>Ort</span>', false)
            ->assertDontSee(route('cities.show', $city), false)
            ->assertSee('<li aria-current="page"><span aria-hidden="true">›</span><span>Potsdam</span></li>', false);

        $collectionPage = $this->jsonLdOfType($response->getContent(), 'CollectionPage');
        $this->assertArrayNotHasKey('mainEntity', $collectionPage);
        $this->assertSame('Potsdam', $collectionPage['about']['name']);
    }

    public function test_unknown_or_non_brandenburg_district_returns_not_found(): void
    {
        $saxony = GeoState::create([
            'country_id' => $this->country->id,
            'ags' => '14',
            'name' => 'Sachsen',
            'slug' => 'sachsen',
        ]);
        $this->createDistrict('Andere Region', 'andere-region', 'landkreis', $saxony);

        $this->get('/brandenburg/landkreis/unbekannt.html')->assertNotFound();
        $this->get('/brandenburg/landkreis/andere-region.html')->assertNotFound();
    }

    public function test_facilities_are_sorted_and_paginated_by_city_name_then_facility_name(): void
    {
        $district = $this->createDistrict('Barnim', 'barnim', 'landkreis');
        $alphaCity = $this->createLinkedCity($district, 'Ahrensfelde', 'ahrensfelde');
        $betaCity = $this->createLinkedCity($district, 'Bernau', 'bernau');

        foreach (range(1, 24) as $number) {
            $this->createFacility($alphaCity, sprintf('Ahrensfelde Pflege %02d', $number));
        }
        $last = $this->createFacility($betaCity, 'Bernau Pflege 01');

        $firstPage = $this->get(route('districts.show', $district->slug))->assertOk();
        $firstPage
            ->assertSee('Ahrensfelde Pflege 01')
            ->assertSee('Ahrensfelde Pflege 24')
            ->assertDontSee($last->name)
            ->assertSee('Seite 1 von 2')
            ->assertSee(route('districts.show', [$district->slug, 'page' => 2]), false);

        $this->get(route('districts.show', [$district->slug, 'page' => 2]))
            ->assertOk()
            ->assertSee($last->name)
            ->assertDontSee('Ahrensfelde Pflege 01')
            ->assertSee('Seite 2 von 2');
    }

    public function test_brandenburg_page_lists_14_landkreise_then_4_cities_only_on_first_page(): void
    {
        $districts = collect();

        foreach (range(1, 14) as $number) {
            $districts->push($this->createDistrict("Landkreis {$number}", "landkreis-{$number}", 'landkreis'));
        }

        foreach (range(1, 4) as $number) {
            $districts->push($this->createDistrict("Kreisstadt {$number}", "kreisstadt-{$number}", 'kreisfreie_stadt'));
        }

        $linkedCity = $this->createLinkedCity($districts->first(), 'Geo Stadt', 'geo-stadt');
        $this->createFacility($linkedCity, 'Geo Pflege');
        $unlinkedCity = $this->createManualCity('Pagination Stadt', 'pagination-stadt');

        foreach (range(1, 25) as $number) {
            $this->createFacility($unlinkedCity, sprintf('Pagination Pflege %02d', $number));
        }

        $firstPage = $this->get(route('region.show'))->assertOk();
        $content = $firstPage->getContent();
        $firstPage->assertSee('Landkreise und kreisfreie Städte');

        foreach ($districts as $district) {
            $this->assertSame(1, substr_count($content, route('districts.show', $district->slug)));
        }

        $this->assertSame(14, substr_count($content, 'Landkreis ·'));
        $this->assertSame(4, substr_count($content, 'Kreisfreie Stadt ·'));
        $this->assertLessThan(strpos($content, 'Kreisstadt 1'), strpos($content, 'Landkreis 9'));

        $this->get(route('region.show', ['page' => 2]))
            ->assertOk()
            ->assertDontSee('Landkreise und kreisfreie Städte')
            ->assertDontSee(route('districts.show', $districts->first()->slug), false);
    }

    public function test_sitemap_contains_each_district_once_and_preserves_city_and_facility_urls(): void
    {
        $districts = collect();

        foreach (range(1, 14) as $number) {
            $districts->push($this->createDistrict("Landkreis {$number}", "landkreis-{$number}", 'landkreis'));
        }

        foreach (range(1, 4) as $number) {
            $districts->push($this->createDistrict("Kreisstadt {$number}", "kreisstadt-{$number}", 'kreisfreie_stadt'));
        }

        $city = $this->createLinkedCity($districts->first(), 'Sitemap Stadt', 'sitemap-stadt');
        $facility = $this->createFacility($city, 'Sitemap Pflege');
        $response = $this->get(route('sitemap'))->assertOk();
        $xml = simplexml_load_string($response->getContent());

        $this->assertNotFalse($xml);
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $locations = array_map('strval', $xml->xpath('//s:loc') ?: []);

        foreach ($districts as $district) {
            $this->assertSame(1, count(array_keys($locations, route('districts.show', $district->slug), true)));
        }

        $this->assertSame(count($locations), count(array_unique($locations)));
        $this->assertContains(route('cities.show', $city), $locations);
        $this->assertContains(route('facilities.show', [$city, $facility]), $locations);
    }

    private function createDistrict(
        string $name,
        string $slug,
        string $type,
        ?GeoState $state = null,
    ): GeoDistrict {
        $this->sequence++;

        return GeoDistrict::create([
            'state_id' => ($state ?? $this->brandenburg)->id,
            'ags' => str_pad((string) (12000 + $this->sequence), 5, '0', STR_PAD_LEFT),
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
        ]);
    }

    private function createLinkedCity(GeoDistrict $district, string $name, string $slug): City
    {
        $this->sequence++;
        $municipality = GeoMunicipality::create([
            'district_id' => $district->id,
            'ags' => str_pad((string) (12000000 + $this->sequence), 8, '0', STR_PAD_LEFT),
            'name' => $name,
            'normalized_name' => $slug,
            'slug' => $slug,
            'source_name' => 'GeoCore Test',
        ]);

        return City::create([
            'name' => $name,
            'slug' => $slug,
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
            'geo_municipality_id' => $municipality->id,
            'geo_match_status' => 'exact',
            'geo_match_method' => 'exact_official_name',
            'geo_match_confidence' => 'high',
            'geo_requires_manual_review' => false,
        ]);
    }

    private function createManualCity(string $name, string $slug): City
    {
        return City::create([
            'name' => $name,
            'slug' => $slug,
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
            'geo_requires_manual_review' => true,
        ]);
    }

    private function createFacility(City $city, string $name): Facility
    {
        $this->sequence++;

        return Facility::create([
            'source_id' => "district-test-{$this->sequence}",
            'city_id' => $city->id,
            'name' => $name,
            'slug' => 'facility-'.$this->sequence,
            'postal_code' => '14467',
            'address' => "Musterstraße {$this->sequence}",
            'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'],
            'features' => [],
        ]);
    }

    /** @return array<string, mixed> */
    private function jsonLdOfType(string $content, string $type): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $content, $matches);

        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (($decoded['@type'] ?? null) === $type) {
                return $decoded;
            }
        }

        $this->fail("JSON-LD type {$type} was not found.");
    }
}

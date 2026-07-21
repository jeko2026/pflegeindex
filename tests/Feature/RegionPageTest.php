<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Models\GeoCountry;
use App\Models\GeoState;
use App\Projects\PflegeIndex\Directory\Presentation\PflegeEntryCardViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegionPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $country = GeoCountry::create([
            'iso2' => 'DE',
            'iso3' => 'DEU',
            'name' => 'Deutschland',
            'slug' => 'deutschland',
        ]);
        GeoState::create([
            'country_id' => $country->id,
            'ags' => '12',
            'name' => 'Brandenburg',
            'slug' => 'brandenburg',
        ]);
    }

    public function test_brandenburg_first_page_shows_filtered_city_and_facility_blocks(): void
    {
        $potsdam = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');
        $cottbus = $this->createCity('Cottbus', 'cottbus', 'Brandenburg', 'brandenburg');
        $emptyCity = $this->createCity('Leere Stadt', 'leere-stadt', 'Brandenburg', 'brandenburg');
        $saxony = $this->createCity('Dresden', 'dresden', 'Sachsen', 'sachsen');
        $potsdamFacility = $this->createFacility($potsdam, 1, 'Pflegezentrum Potsdam');
        $this->createFacility($potsdam, 2, 'Pflegehaus Potsdam');
        $cottbusFacility = $this->createFacility($cottbus, 3, 'Pflegezentrum Cottbus');
        $otherStateFacility = $this->createFacility($saxony, 4, 'Pflegezentrum Dresden');

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $response = $this->get(route('region.show'))
            ->assertOk()
            ->assertSee('Pflegeeinrichtungen in Brandenburg')
            ->assertSee('Pflegeeinrichtungen nach Stadt')
            ->assertSee('Alle Pflegeeinrichtungen in Brandenburg')
            ->assertSee($potsdamFacility->name)
            ->assertSee($cottbusFacility->name)
            ->assertSee(route('facilities.show', [$potsdam, $potsdamFacility]), false)
            ->assertSee(url('/brandenburg/potsdam.html'), false)
            ->assertSee(url('/brandenburg/cottbus.html'), false)
            ->assertSee('<strong>2</strong><span>Orte</span>', false)
            ->assertSee('2 Einrichtungen')
            ->assertSee('1 Einrichtung')
            ->assertDontSee($emptyCity->name)
            ->assertDontSee(url('/brandenburg/leere-stadt.html'), false)
            ->assertDontSee($saxony->name)
            ->assertDontSee(url('/brandenburg/dresden.html'), false)
            ->assertDontSee($otherStateFacility->name);
        $facilities = $response->viewData('facilities');

        $this->assertInstanceOf(LengthAwarePaginator::class, $facilities);
        $this->assertInstanceOf(PflegeEntryCardViewModel::class, $facilities->items()[0]);
        $this->assertLessThanOrEqual(8, $queryCount, 'Region page introduced too many SQL queries.');
        $response
            ->assertSee('<link rel="canonical" href="'.route('region.show').'">', false)
            ->assertDontSee('<nav aria-label="Breadcrumb">', false);

        $this->assertTrue(
            strpos($response->getContent(), $cottbus->name)
            < strpos($response->getContent(), $potsdam->name),
        );
    }

    public function test_brandenburg_has_open_graph_and_collection_page_metadata(): void
    {
        $title = 'Pflegeeinrichtungen in Brandenburg – PflegeIndex';
        $description = '0 Pflegeeinrichtungen in 0 Orten Brandenburgs entdecken.';
        $pageUrl = route('region.show');
        $imageUrl = asset('assets/og-image.png');
        $response = $this->get($pageUrl)->assertOk();

        $response
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:title" content="'.$title.'">', false)
            ->assertSee('<meta property="og:description" content="'.$description.'">', false)
            ->assertSee('<meta property="og:url" content="'.$pageUrl.'">', false)
            ->assertSee('<meta property="og:site_name" content="PflegeIndex">', false)
            ->assertSee('<meta property="og:locale" content="de_DE">', false)
            ->assertSee('<meta property="og:image" content="'.$imageUrl.'">', false);

        $collectionPage = $this->jsonLdOfType($response->getContent(), 'CollectionPage');

        $this->assertSame($title, $collectionPage['name']);
        $this->assertSame($description, $collectionPage['description']);
        $this->assertSame($pageUrl, $collectionPage['url']);
        $this->assertSame('de-DE', $collectionPage['inLanguage']);
        $this->assertSame('Brandenburg', $collectionPage['about']['name']);
        $this->assertArrayNotHasKey('mainEntity', $collectionPage);
        $this->assertStringNotContainsString('"@type":"ItemList"', $response->getContent());
    }

    public function test_brandenburg_facilities_are_stably_sorted_and_paginated(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');

        foreach (range(1, 25) as $number) {
            $this->createFacility($city, $number, sprintf('Einrichtung %02d', $number));
        }

        $firstPage = $this->get(route('region.show'))->assertOk();
        $firstPage->assertSee('Pflegeeinrichtungen nach Stadt')
            ->assertSee(url('/brandenburg/potsdam.html'), false)
            ->assertSee('Einrichtung 01')
            ->assertSee('Einrichtung 24')
            ->assertDontSee('Einrichtung 25')
            ->assertSee('Seite 1 von 2')
            ->assertSee(route('region.show', ['page' => 2]), false);
        $this->assertTrue(
            strpos($firstPage->getContent(), 'Einrichtung 01')
            < strpos($firstPage->getContent(), 'Einrichtung 02'),
        );

        $this->get(route('region.show', ['page' => 2]))
            ->assertOk()
            ->assertSee('Pflegeeinrichtungen in Brandenburg')
            ->assertSee('Alle Pflegeeinrichtungen in Brandenburg')
            ->assertDontSee('Pflegeeinrichtungen nach Stadt')
            ->assertDontSee(url('/brandenburg/potsdam.html'), false)
            ->assertSee('Einrichtung 25')
            ->assertDontSee('Einrichtung 01')
            ->assertSee('Seite 2 von 2');
    }

    public function test_brandenburg_pagination_has_page_specific_seo_metadata(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');

        foreach (range(1, 49) as $number) {
            $this->createFacility($city, $number, sprintf('SEO Einrichtung %02d', $number));
        }

        foreach ([1, 2, 3] as $page) {
            $canonical = $page === 1
                ? route('region.show')
                : route('region.show', ['page' => $page]);
            $title = $page === 1
                ? 'Pflegeeinrichtungen in Brandenburg – PflegeIndex'
                : "Pflegeeinrichtungen in Brandenburg – Seite {$page} – PflegeIndex";
            $description = $page === 1
                ? '49 Pflegeeinrichtungen in 1 Orten Brandenburgs entdecken.'
                : "Seite {$page} mit weiteren Pflegeeinrichtungen in Brandenburg.";
            $response = $this->get(route('region.show', ['page' => $page]))->assertOk();

            $response
                ->assertSee('<title>'.$title.'</title>', false)
                ->assertSee('<meta name="description" content="'.$description.'">', false)
                ->assertSee('<link rel="canonical" href="'.$canonical.'">', false)
                ->assertSee('<meta property="og:title" content="'.$title.'">', false)
                ->assertSee('<meta property="og:description" content="'.$description.'">', false)
                ->assertSee('<meta property="og:url" content="'.$canonical.'">', false);

            $this->assertSame(
                $canonical,
                $this->jsonLdOfType($response->getContent(), 'CollectionPage')['url'],
            );
        }
    }

    private function createCity(string $name, string $slug, string $state, string $stateSlug): City
    {
        return City::create([
            'name' => $name,
            'slug' => $slug,
            'state' => $state,
            'state_slug' => $stateSlug,
        ]);
    }

    private function createFacility(City $city, int $number, string $name): Facility
    {
        return Facility::create([
            'source_id' => "region-{$number}-{$city->slug}",
            'city_id' => $city->id,
            'name' => $name,
            'slug' => "einrichtung-{$number}-{$city->slug}",
            'postal_code' => '14467',
            'address' => "Musterstraße {$number}",
            'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'],
            'features' => [],
        ]);
    }

    /** @return array<string, mixed> */
    private function jsonLdOfType(string $content, string $type): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);

        foreach ($matches[1] ?? [] as $json) {
            $schema = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (($schema['@type'] ?? null) === $type) {
                return $schema;
            }
        }

        $this->fail("JSON-LD type {$type} was not found.");
    }
}

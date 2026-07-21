<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Projects\PflegeIndex\Directory\Presentation\PflegeEntryCardViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CityPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_brandenburg_city_page_shows_only_its_facilities_and_seo_metadata(): void
    {
        $potsdam = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');
        $cottbus = $this->createCity('Cottbus', 'cottbus', 'Brandenburg', 'brandenburg');
        $saxony = $this->createCity('Dresden', 'dresden', 'Sachsen', 'sachsen');
        $first = $this->createFacility($potsdam, 1, 'Alpha Pflege Potsdam', 'Ambulante Pflege');
        $second = $this->createFacility($potsdam, 2, 'Beta Pflege Potsdam', 'Stationäre Pflege');
        $otherCity = $this->createFacility($cottbus, 3, 'Pflege Cottbus', 'Ambulante Pflege');
        $otherState = $this->createFacility($saxony, 4, 'Pflege Dresden', 'Ambulante Pflege');
        $canonicalUrl = route('cities.show', $potsdam);

        $response = $this->get($canonicalUrl)
            ->assertOk()
            ->assertSee('<title>Pflegeeinrichtungen in Potsdam – PflegeIndex</title>', false)
            ->assertSee('<meta name="description" content="2 Pflegeeinrichtungen in Potsdam: Anschriften, Einrichtungsarten und geprüfte Kontaktdaten.">', false)
            ->assertSee('<h1>Pflegeeinrichtungen in Potsdam</h1>', false)
            ->assertSee($first->name)
            ->assertSee($second->name)
            ->assertDontSee($otherCity->name)
            ->assertDontSee($otherState->name)
            ->assertSee('href="'.route('facilities.show', [$potsdam, $first]).'"', false)
            ->assertSee('href="'.route('facilities.show', [$potsdam, $second]).'"', false)
            ->assertSee('<link rel="canonical" href="'.$canonicalUrl.'">', false)
            ->assertSee('<meta property="og:title" content="Pflegeeinrichtungen in Potsdam – PflegeIndex">', false)
            ->assertSee('<meta property="og:description" content="2 Pflegeeinrichtungen in Potsdam: Anschriften, Einrichtungsarten und geprüfte Kontaktdaten.">', false)
            ->assertSee('<meta property="og:url" content="'.$canonicalUrl.'">', false)
            ->assertSee('<strong>2</strong><span>Einrichtungen</span>', false)
            ->assertSee('<strong>2</strong><span>Einrichtungsarten</span>', false);

        $paginator = $response->viewData('facilities');

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertInstanceOf(PflegeEntryCardViewModel::class, $paginator->items()[0]);

        $this->assertSame(url('/brandenburg/potsdam.html'), $canonicalUrl);
        $this->assertTrue(
            strpos($response->getContent(), $first->name)
            < strpos($response->getContent(), $second->name),
        );

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
        $schema = json_decode($matches[1] ?? '', true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('CollectionPage', $schema['@type']);
        $this->assertSame($canonicalUrl, $schema['url']);
        $this->assertSame('Potsdam', $schema['about']['name']);
        $this->assertSame('Brandenburg', $schema['about']['address']['addressRegion']);
    }

    public function test_city_page_has_accessible_breadcrumbs_and_correct_active_navigation(): void
    {
        $city = $this->createCity('Cottbus', 'cottbus', 'Brandenburg', 'brandenburg');
        $canonicalUrl = route('cities.show', $city);

        $response = $this->get($canonicalUrl)
            ->assertOk()
            ->assertSee('<nav aria-label="Breadcrumb">', false)
            ->assertSee('<li><a href="'.route('home').'">Startseite</a></li>', false)
            ->assertSee('<a href="'.route('region.show').'">Brandenburg</a>', false)
            ->assertSee('<li aria-current="page"><span aria-hidden="true">›</span><span>Cottbus</span></li>', false)
            ->assertDontSee('<a href="'.$canonicalUrl.'">Cottbus</a>', false)
            ->assertSee('<a href="'.route('region.show').'"  aria-current="page" >Brandenburg</a>', false)
            ->assertDontSee('<a href="'.route('directory.index').'"  aria-current="page" >Pflege finden</a>', false);

        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
        $schemas = collect($matches[1] ?? [])->map(
            fn (string $json): array => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        );
        $breadcrumbSchema = $schemas->firstWhere('@type', 'BreadcrumbList');

        $this->assertNotNull($breadcrumbSchema);
        $this->assertSame([1, 2, 3], array_column($breadcrumbSchema['itemListElement'], 'position'));
        $this->assertSame(route('home'), $breadcrumbSchema['itemListElement'][0]['item']);
        $this->assertSame(route('region.show'), $breadcrumbSchema['itemListElement'][1]['item']);
        $this->assertSame($canonicalUrl, $breadcrumbSchema['itemListElement'][2]['item']);
    }

    public function test_unknown_city_slug_returns_not_found(): void
    {
        $this->get('/brandenburg/unbekannt.html')->assertNotFound();
    }

    public function test_city_from_another_state_returns_not_found(): void
    {
        $city = $this->createCity('Dresden', 'dresden', 'Sachsen', 'sachsen');

        $this->get(route('cities.show', $city))->assertNotFound();
    }

    public function test_city_facilities_are_paginated_by_twenty_four(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');

        foreach (range(1, 25) as $number) {
            $this->createFacility($city, $number, sprintf('Einrichtung %02d', $number), 'Ambulante Pflege');
        }

        $firstPage = $this->get(route('cities.show', $city))->assertOk();
        $firstPage->assertSee('Einrichtung 01')
            ->assertSee('Einrichtung 24')
            ->assertDontSee('Einrichtung 25')
            ->assertSee('Seite 1 von 2')
            ->assertSee(route('cities.show', [$city, 'page' => 2]), false);

        $secondPage = $this->get(route('cities.show', [$city, 'page' => 2]))
            ->assertOk()
            ->assertSee('Einrichtung 25')
            ->assertDontSee('Einrichtung 01')
            ->assertSee('Seite 2 von 2')
            ->assertSee('<link rel="canonical" href="'.route('cities.show', [$city, 'page' => 2]).'">', false);

        $this->assertSame(2, $secondPage->viewData('facilities')->currentPage());
    }

    public function test_city_pagination_has_page_specific_seo_metadata(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');

        foreach (range(1, 49) as $number) {
            $this->createFacility($city, $number, sprintf('SEO Einrichtung %02d', $number), 'Ambulante Pflege');
        }

        foreach ([1, 2, 3] as $page) {
            $canonical = $page === 1
                ? route('cities.show', $city)
                : route('cities.show', [$city, 'page' => $page]);
            $title = $page === 1
                ? 'Pflegeeinrichtungen in Potsdam – PflegeIndex'
                : "Pflegeeinrichtungen in Potsdam – Seite {$page} – PflegeIndex";
            $description = $page === 1
                ? '49 Pflegeeinrichtungen in Potsdam: Anschriften, Einrichtungsarten und geprüfte Kontaktdaten.'
                : "Seite {$page} mit weiteren Pflegeeinrichtungen in Potsdam.";
            $response = $this->get(route('cities.show', [$city, 'page' => $page]))->assertOk();

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

    public function test_city_facilities_with_equal_names_are_stably_sorted_by_id(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');
        $first = $this->createFacility($city, 1, 'Alpha Pflege', 'Ambulante Pflege');
        $second = $this->createFacility($city, 2, 'Alpha Pflege', 'Ambulante Pflege');
        $third = $this->createFacility($city, 3, 'Beta Pflege', 'Ambulante Pflege');

        $content = $this->get(route('cities.show', $city))->assertOk()->getContent();

        $this->assertTrue(
            strpos($content, $first->address) < strpos($content, $second->address)
            && strpos($content, $second->address) < strpos($content, $third->address),
        );
    }

    public function test_empty_city_and_invalid_page_are_handled_safely(): void
    {
        $emptyCity = $this->createCity('Leere Stadt', 'leere-stadt', 'Brandenburg', 'brandenburg');
        $populatedCity = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');
        $this->createFacility($populatedCity, 1, 'Pflege Potsdam', 'Ambulante Pflege');

        $emptyResponse = $this->get(route('cities.show', $emptyCity))
            ->assertOk()
            ->assertSee('<h1>Pflegeeinrichtungen in Leere Stadt</h1>', false)
            ->assertSee('<strong>0</strong><span>Einrichtungen</span>', false);

        $this->assertSame(0, $emptyResponse->viewData('facilities')->total());

        $invalidPageResponse = $this->get(route('cities.show', [$populatedCity, 'page' => 'invalid']))
            ->assertOk()
            ->assertSee('Pflege Potsdam');

        $this->assertSame(1, $invalidPageResponse->viewData('facilities')->currentPage());
    }

    public function test_city_page_query_count_does_not_grow_with_facility_cards(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');

        foreach (range(1, 10) as $number) {
            $this->createFacility($city, $number, sprintf('Pflege %02d', $number), 'Ambulante Pflege');
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->get(route('cities.show', $city))->assertOk();

        $this->assertLessThanOrEqual(5, $queryCount, 'City page introduced too many SQL queries.');
    }

    public function test_sitemap_contains_only_public_brandenburg_city_urls(): void
    {
        $potsdam = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');
        $emptyCity = $this->createCity('Leere Stadt', 'leere-stadt', 'Brandenburg', 'brandenburg');
        $dresden = $this->createCity('Dresden', 'dresden', 'Sachsen', 'sachsen');
        $this->createFacility($potsdam, 1, 'Pflege Potsdam', 'Ambulante Pflege');
        $this->createFacility($dresden, 2, 'Pflege Dresden', 'Ambulante Pflege');

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(url('/brandenburg/potsdam.html'), false)
            ->assertDontSee(url('/brandenburg/'.$emptyCity->slug.'.html'), false)
            ->assertDontSee(url('/brandenburg/'.$dresden->slug.'.html'), false);
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

    private function createFacility(City $city, int $number, string $name, string $type): Facility
    {
        return Facility::create([
            'source_id' => "city-page-{$number}-{$city->slug}",
            'city_id' => $city->id,
            'name' => $name,
            'slug' => "einrichtung-{$number}-{$city->slug}",
            'postal_code' => '14467',
            'address' => "Musterstraße {$number}",
            'type' => $type,
            'care_types' => [$type],
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

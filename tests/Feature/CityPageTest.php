<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('<h1>Pflegeheime in Potsdam</h1>', false)
            ->assertSee($first->name)
            ->assertSee($second->name)
            ->assertDontSee($otherCity->name)
            ->assertDontSee($otherState->name)
            ->assertSee('<link rel="canonical" href="'.$canonicalUrl.'">', false)
            ->assertSee('<meta property="og:url" content="'.$canonicalUrl.'">', false)
            ->assertSee('<strong>2</strong><span>Einrichtungen</span>', false)
            ->assertSee('<strong>2</strong><span>Einrichtungsarten</span>', false);

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

        $this->get(route('cities.show', [$city, 'page' => 2]))
            ->assertOk()
            ->assertSee('Einrichtung 25')
            ->assertDontSee('Einrichtung 01')
            ->assertSee('Seite 2 von 2');
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
}

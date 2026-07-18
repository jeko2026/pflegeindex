<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionPageTest extends TestCase
{
    use RefreshDatabase;

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

        $response = $this->get(route('region.show'))
            ->assertOk()
            ->assertSee('Pflegeheime in Brandenburg')
            ->assertSee('Pflegeheime nach Stadt')
            ->assertSee('Alle Pflegeheime in Brandenburg')
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

        $this->assertTrue(
            strpos($response->getContent(), $cottbus->name)
            < strpos($response->getContent(), $potsdam->name),
        );
    }

    public function test_brandenburg_facilities_are_stably_sorted_and_paginated(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');

        foreach (range(1, 25) as $number) {
            $this->createFacility($city, $number, sprintf('Einrichtung %02d', $number));
        }

        $firstPage = $this->get(route('region.show'))->assertOk();
        $firstPage->assertSee('Pflegeheime nach Stadt')
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
            ->assertSee('Pflegeheime in Brandenburg')
            ->assertSee('Alle Pflegeheime in Brandenburg')
            ->assertDontSee('Pflegeheime nach Stadt')
            ->assertDontSee(url('/brandenburg/potsdam.html'), false)
            ->assertSee('Einrichtung 25')
            ->assertDontSee('Einrichtung 01')
            ->assertSee('Seite 2 von 2');
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
}

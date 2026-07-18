<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_brandenburg_page_lists_only_facilities_from_brandenburg(): void
    {
        $brandenburg = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');
        $saxony = $this->createCity('Dresden', 'dresden', 'Sachsen', 'sachsen');
        $publicFacility = $this->createFacility($brandenburg, 1, 'Pflegezentrum Potsdam');
        $otherStateFacility = $this->createFacility($saxony, 2, 'Pflegezentrum Dresden');

        $this->get(route('region.show'))
            ->assertOk()
            ->assertSee('Pflege in Brandenburg')
            ->assertSee($publicFacility->name)
            ->assertSee(route('facilities.show', [$brandenburg, $publicFacility]), false)
            ->assertDontSee($otherStateFacility->name);
    }

    public function test_brandenburg_facilities_are_stably_sorted_and_paginated(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam', 'Brandenburg', 'brandenburg');

        foreach (range(1, 25) as $number) {
            $this->createFacility($city, $number, sprintf('Einrichtung %02d', $number));
        }

        $firstPage = $this->get(route('region.show'))->assertOk();
        $firstPage->assertSee('Einrichtung 01')
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

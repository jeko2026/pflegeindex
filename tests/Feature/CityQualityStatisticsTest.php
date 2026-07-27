<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CityQualityStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_page_shows_quality_aggregates(): void
    {
        $city = City::create(['name' => 'Cottbus', 'slug' => 'cottbus', 'state_slug' => 'brandenburg']);
        $this->facility($city, 'Eins', 'verified');
        $this->facility($city, 'Zwei', null);

        $this->get(route('cities.show', [$city, 'stateSlug' => 'brandenburg']))
            ->assertOk()
            ->assertSee('Datenqualität in Cottbus')
            ->assertSee('Geprüfte Einrichtungen:')
            ->assertSee('1 von 2')
            ->assertSee('50 % der Einrichtungen wurden geprüft')
            ->assertSee('data-city-quality-score=', false);
    }

    public function test_city_quality_block_is_omitted_when_city_is_empty(): void
    {
        $city = City::create(['name' => 'Leere Stadt', 'slug' => 'leere-stadt', 'state_slug' => 'brandenburg']);

        $this->get(route('cities.show', [$city, 'stateSlug' => 'brandenburg']))
            ->assertOk()
            ->assertDontSee('city-quality-score', false)
            ->assertDontSee('Datenqualität in Leere Stadt');
    }

    private function facility(City $city, string $name, ?string $status): Facility
    {
        return Facility::create([
            'source_id' => 'city-quality-'.strtolower($name),
            'city_id' => $city->id,
            'name' => $name,
            'slug' => strtolower($name),
            'type' => 'Pflege',
            'address' => 'Straße 1',
            'postal_code' => '03046',
            'phone' => '+49 355 123456',
            'contact_status' => $status,
        ]);
    }
}

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
        $this->facility($city, 'Eins', 'verified', true);
        $this->facility($city, 'Zwei', null);

        $this->get(route('cities.show', [$city, 'stateSlug' => 'brandenburg']))
            ->assertOk()
            ->assertSee('Datenqualität in Cottbus')
            ->assertSee('Geprüfte Einrichtungen:')
            ->assertSee('1 von 2')
            ->assertSee('50 % der Einrichtungen wurden geprüft')
            ->assertSee('nicht auf die Pflegequalität')
            ->assertSee('data-city-quality-score=', false);
    }

    public function test_verified_status_without_documented_review_is_not_counted_as_publicly_checked(): void
    {
        $city = City::create(['name' => 'Cottbus', 'slug' => 'cottbus', 'state_slug' => 'brandenburg']);
        $this->facility($city, 'Dokumentiert', 'verified', true);
        $this->facility($city, 'Ohne Quelle', 'verified');

        $this->get(route('cities.show', [$city, 'stateSlug' => 'brandenburg']))
            ->assertOk()
            ->assertSee('Geprüfte Einrichtungen:')
            ->assertSee('1 von 2')
            ->assertSee('50 % der Einrichtungen wurden geprüft');
    }

    public function test_city_quality_block_is_omitted_when_city_is_empty(): void
    {
        $city = City::create(['name' => 'Leere Stadt', 'slug' => 'leere-stadt', 'state_slug' => 'brandenburg']);

        $this->get(route('cities.show', [$city, 'stateSlug' => 'brandenburg']))
            ->assertOk()
            ->assertDontSee('city-quality-score', false)
            ->assertDontSee('Datenqualität in Leere Stadt');
    }

    private function facility(City $city, string $name, ?string $status, bool $reviewDocumented = false): Facility
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
        ] + ($reviewDocumented ? [
            'contact_source' => 'https://example.de/kontakt',
            'contact_checked_at' => '2026-08-16 12:00:00',
        ] : []));
    }
}

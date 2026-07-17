<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_page_lists_its_facilities(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();

        $this->get(route('cities.show', $city))
            ->assertOk()
            ->assertSee($city->name)
            ->assertSee($facility->name);
    }

    public function test_facility_page_uses_the_pretty_nested_url(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee($facility->name)
            ->assertSee($facility->address);
    }

    public function test_facility_page_shows_safe_custom_description(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update(['description' => "Persönliche Beratung vor Ort.\n<script>alert('x')</script>"]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('Persönliche Beratung vor Ort.')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertDontSee('im offiziellen Einrichtungsverzeichnis');
    }

    public function test_facility_page_shows_email_without_requiring_a_phone(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'email' => 'kontakt@example.de',
            'contact_status' => 'verified',
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('mailto:kontakt@example.de', false)
            ->assertSee('E-Mail senden')
            ->assertSee('In Google Maps öffnen')
            ->assertSee('google.com/maps/search/?api=1&amp;query=', false);
    }

    public function test_short_german_phone_does_not_leave_a_two_digit_group(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'phone' => '+4933188700',
            'contact_status' => 'verified',
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('+49 331 88700');
    }

    public function test_directory_can_filter_by_query_and_type(): void
    {
        [, $matchingFacility] = $this->createDirectoryEntry();
        $otherCity = City::create([
            'name' => 'Calau',
            'slug' => 'calau',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);
        $otherFacility = Facility::create([
            'source_id' => 'other-03205-test',
            'city_id' => $otherCity->id,
            'name' => 'Anderes Krankenhaus',
            'slug' => 'anderes-krankenhaus-03205',
            'postal_code' => '03205',
            'address' => 'Testweg 2',
            'type' => 'Krankenhaus',
            'care_types' => ['Krankenhaus'],
            'features' => [],
        ]);

        $this->get(route('directory.index', ['q' => 'Potsdam', 'type' => 'Ambulante Pflege']))
            ->assertOk()
            ->assertSee($matchingFacility->name)
            ->assertDontSee($otherFacility->name);
    }

    /** @return array{City, Facility} */
    private function createDirectoryEntry(): array
    {
        $city = City::create([
            'name' => 'Potsdam',
            'slug' => 'potsdam',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);

        $facility = Facility::create([
            'source_id' => 'example-14467-test',
            'city_id' => $city->id,
            'name' => 'Beispiel Pflegezentrum',
            'slug' => 'beispiel-pflegezentrum-14467',
            'postal_code' => '14467',
            'address' => 'Musterstraße 1',
            'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'],
            'features' => [],
        ]);

        return [$city, $facility];
    }
}

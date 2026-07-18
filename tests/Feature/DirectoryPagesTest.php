<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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

    public function test_facility_page_has_unique_and_escaped_open_graph_metadata(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update(['name' => 'Pflege & Wohnen "Am Park"']);

        $expectedTitle = "{$facility->name} in {$city->name} – PflegeIndex";
        $expectedDescription = "{$facility->name}: {$facility->type} in {$city->name}, {$facility->address}, {$facility->postal_code} {$city->name}.";
        $canonicalUrl = route('facilities.show', [$city, $facility]);

        $response = $this->get($canonicalUrl)->assertOk();
        $content = $response->getContent();
        $head = $this->headHtml($response);

        $this->assertStringContainsString('<meta property="og:type" content="website">', $head);
        $this->assertStringContainsString('<meta property="og:title" content="'.e($expectedTitle).'">', $head);
        $this->assertStringContainsString('<meta name="description" content="'.e($expectedDescription).'">', $head);
        $this->assertStringContainsString('<meta property="og:description" content="'.e($expectedDescription).'">', $head);
        $this->assertStringContainsString('<link rel="canonical" href="'.$canonicalUrl.'">', $head);
        $this->assertStringContainsString('<meta property="og:url" content="'.$canonicalUrl.'">', $head);
        $this->assertStringContainsString('<meta property="og:site_name" content="PflegeIndex">', $head);
        $this->assertStringContainsString('<meta property="og:locale" content="de_DE">', $head);
        $this->assertStringNotContainsString('content="Pflege & Wohnen "Am Park"', $content);

        foreach (['og:type', 'og:title', 'og:description', 'og:url', 'og:site_name', 'og:locale'] as $property) {
            $this->assertSame(1, substr_count($content, 'property="'.$property.'"'));
        }
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

    public function test_facility_page_shows_other_facilities_from_the_same_city_and_links_to_the_city(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $relatedFacility = $this->createFacility(
            $city,
            'related-14467-test',
            'Pflege am Park',
            'pflege-am-park-14467',
            'Parkstraße 2',
        );
        $otherCity = City::create([
            'name' => 'Calau',
            'slug' => 'calau',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);
        $otherCityFacility = $this->createFacility(
            $otherCity,
            'other-city-03205-test',
            'Pflege in Calau',
            'pflege-in-calau-03205',
            'Calauer Weg 3',
        );

        $response = $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee("Weitere Pflegeeinrichtungen in {$city->name}")
            ->assertSee($relatedFacility->name)
            ->assertSee("Alle Pflegeeinrichtungen in {$city->name} ansehen")
            ->assertSee('href="'.route('cities.show', $city).'"', false);

        $relatedHtml = $this->relatedFacilitiesHtml($response);

        $this->assertStringNotContainsString($facility->name, $relatedHtml);
        $this->assertStringNotContainsString($otherCityFacility->name, $relatedHtml);
    }

    public function test_related_facilities_are_limited_to_three_and_stably_sorted(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $firstById = $this->createFacility(
            $city,
            'alpha-first-14467-test',
            'Alpha Pflege',
            'alpha-pflege-erster-eintrag-14467',
            'Sortierung 1',
        );
        $secondById = $this->createFacility(
            $city,
            'alpha-second-14467-test',
            'Alpha Pflege',
            'alpha-pflege-zweiter-eintrag-14467',
            'Sortierung 2',
        );
        $afterAlpha = $this->createFacility(
            $city,
            'beta-14467-test',
            'Beta Pflege',
            'beta-pflege-14467',
            'Sortierung 3',
        );
        $excludedByLimit = $this->createFacility(
            $city,
            'stationary-14467-test',
            'A Pflegeheim',
            'a-pflegeheim-14467',
            'Sortierung 4',
            'Stationäre Pflege',
        );

        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $relatedHtml = $this->relatedFacilitiesHtml($response);

        $this->assertSame(3, substr_count($relatedHtml, '<article class="result-card">'));
        $this->assertStringContainsString($firstById->address, $relatedHtml);
        $this->assertStringContainsString($secondById->address, $relatedHtml);
        $this->assertStringContainsString($afterAlpha->address, $relatedHtml);
        $this->assertStringNotContainsString($excludedByLimit->address, $relatedHtml);
        $this->assertTrue(
            strpos($relatedHtml, $firstById->address) < strpos($relatedHtml, $secondById->address)
            && strpos($relatedHtml, $secondById->address) < strpos($relatedHtml, $afterAlpha->address),
        );
    }

    public function test_facility_page_hides_related_block_when_the_facility_is_alone_in_its_city(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertDontSee('id="related-facilities"', false)
            ->assertDontSee("Weitere Pflegeeinrichtungen in {$city->name}");
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

    private function createFacility(
        City $city,
        string $sourceId,
        string $name,
        string $slug,
        string $address,
        string $type = 'Ambulante Pflege',
    ): Facility {
        return Facility::create([
            'source_id' => $sourceId,
            'city_id' => $city->id,
            'name' => $name,
            'slug' => $slug,
            'postal_code' => '14467',
            'address' => $address,
            'type' => $type,
            'care_types' => [$type],
            'features' => [],
        ]);
    }

    private function relatedFacilitiesHtml(TestResponse $response): string
    {
        $content = $response->getContent();
        $start = strpos($content, '<section class="section section--white" id="related-facilities"');

        if ($start === false) {
            return '';
        }

        $end = strpos($content, '</section>', $start);

        return substr($content, $start, $end - $start + strlen('</section>'));
    }

    private function headHtml(TestResponse $response): string
    {
        $content = $response->getContent();
        $start = strpos($content, '<head>');
        $end = strpos($content, '</head>');

        return substr($content, $start, $end - $start + strlen('</head>'));
    }
}

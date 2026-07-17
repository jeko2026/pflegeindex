<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contains_public_directory_pages_only(): void
    {
        $city = City::create([
            'name' => 'Potsdam',
            'slug' => 'potsdam',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);
        $facility = Facility::create([
            'source_id' => 'sitemap-example-14467',
            'city_id' => $city->id,
            'name' => 'Sitemap Pflegezentrum',
            'slug' => 'sitemap-pflegezentrum-14467',
            'postal_code' => '14467',
            'address' => 'Musterstraße 1',
            'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'],
            'features' => [],
        ]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('cities.show', $city), false)
            ->assertSee(route('facilities.show', [$city, $facility]), false)
            ->assertDontSee(route('pages.imprint'), false)
            ->assertDontSee(route('pages.privacy'), false);
    }

    public function test_robots_file_points_to_the_sitemap(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *')
            ->assertSee('Sitemap: '.route('sitemap'));
    }
}

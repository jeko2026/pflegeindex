<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Services\CarePageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoActionSprintTest extends TestCase
{
    use RefreshDatabase;

    private function seedCategory(string $citySlug, string $category, int $count): City
    {
        $city = City::firstOrCreate(['slug' => $citySlug], ['name' => ucfirst($citySlug), 'state' => 'Brandenburg', 'state_slug' => 'brandenburg']);
        $type = config('care_pages.categories.'.$category.'.types')[0];
        for ($i = 0; $i < $count; $i++) {
            $id = Facility::count() + 1;
            Facility::create(['city_id' => $city->id, 'source_id' => 'sprint-'.$id, 'slug' => 'sprint-'.$id, 'name' => 'Anbieter '.$id, 'type' => $type, 'care_types' => [$type], 'features' => [], 'address' => 'Beispielweg '.$id, 'postal_code' => '12345']);
        }

        return $city;
    }

    public function test_new_pages_with_fewer_than_three_matches_are_absent_everywhere(): void
    {
        $city = $this->seedCategory('bernau', 'tagespflege', 2);
        $url = route('cities.care.show', [$city, 'tagespflege']);
        $this->get($url)->assertNotFound();
        $this->get(route('cities.show', $city))->assertOk()->assertDontSee('href="'.$url.'"', false);
        $this->get(route('facilities.show', [$city, $city->facilities()->first()]))->assertOk()->assertDontSee('href="'.$url.'"', false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('<loc>'.$url.'</loc>', false);
        $this->seedCategory('bernau', 'tagespflege', 1);
        $this->get($url)->assertOk();
        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.$url.'</loc>', false);
    }

    public function test_all_ten_new_pages_have_visible_faq_truthful_schema_and_reciprocal_links(): void
    {
        $titles = [];
        $count = 0;
        foreach (config('seo_action.new_pages') as $slug => $categories) {
            foreach ($categories as $category) {
                $city = $this->seedCategory($slug, $category, 3);
                $url = route('cities.care.show', [$city, $category]);
                $html = $this->get($url.'?utm_source=qa')->assertOk()->assertDontSee('noindex')
                    ->assertSee('<link rel="canonical" href="'.$url.'">', false)->getContent();
                preg_match('/<title>(.*?)<\/title>/s', $html, $title);
                $titles[] = $title[1];
                $this->assertSame(1, substr_count($html, '<h1>'));
                preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $json);
                $graph = json_decode($json[1], true, 512, JSON_THROW_ON_ERROR)['@graph'];
                $this->assertSame(3, $graph[0]['mainEntity']['numberOfItems']);
                $this->assertSame('FAQPage', $graph[2]['@type']);
                $this->assertCount(4, $graph[2]['mainEntity']);
                foreach ($graph[2]['mainEntity'] as $q) {
                    $this->assertStringContainsString(e($q['name']), $html);
                    $this->assertStringContainsString(e($q['acceptedAnswer']['text']), $html);
                }
                foreach (['AggregateRating', '"@type":"Review"', '"@type":"Offer"', '"price"'] as $unsupported) {
                    $this->assertStringNotContainsString($unsupported, $json[1]);
                }
                $this->get(route('cities.show', $city))->assertOk()->assertSee('href="'.$url.'"', false);
                $facility = app(CarePageService::class)->facilities($city, $category)->first();
                $this->get(route('facilities.show', [$city, $facility]))->assertOk()->assertSee('href="'.$url.'"', false);
                $count++;
            }
        }
        $this->assertSame(10, $count);
        $this->assertCount(10, array_unique($titles));
        $this->get('/robots.txt')->assertOk()->assertSee('Allow: /');
    }

    public function test_cost_guide_does_not_pretend_to_compute_and_is_linked(): void
    {
        $url = route('guides.care-costs');
        $this->get($url)->assertOk()->assertSee('Noch kein Kostenrechner')->assertDontSee('<input', false)
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)->assertSee('Pflegegeld einordnen');
        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.$url.'</loc>', false);
        $this->get(route('lexicon.show', 'pflegeheim'))->assertOk()->assertSee('href="'.$url.'"', false);
    }

    public function test_unknown_city_does_not_gain_a_generated_service_page(): void
    {
        $city = $this->seedCategory('nicht-freigegeben', 'tagespflege', 5);
        $this->get(route('cities.care.show', [$city, 'tagespflege']))->assertNotFound();
        $this->assertSame([], app(CarePageService::class)->links($city));
    }
}

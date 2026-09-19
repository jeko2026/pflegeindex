<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Services\CarePageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CityCarePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_neuruppin_page_has_filtered_cards_metadata_breadcrumbs_and_schema(): void
    {
        $city = $this->city('Neuruppin', 'neuruppin');
        $included = $this->facility($city, 'Ambulanter Pflegedienst Neuruppin', 'Ambulante Pflege');
        $excluded = $this->facility($city, 'Tagespflege Beispiel', 'Stationäre/teilstationäre Pflege');
        $elsewhere = $this->facility($this->city('Potsdam', 'potsdam'), 'Andere Stadt', 'Ambulante Pflege');
        $url = route('cities.care.show', [$city, 'ambulante-pflegedienste']);
        $detail = route('facilities.show', [$city, $included]);
        $response = $this->get($url.'?utm_source=test')->assertOk()
            ->assertSee('<h1>Ambulante Pflegedienste in Neuruppin</h1>', false)
            ->assertSee('<title>Ambulante Pflegedienste in Neuruppin | PflegeIndex</title>', false)
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('Ambulante Pflegedienste in Neuruppin: Adressen, Telefonnummern, Websites und weitere Kontaktdaten auf PflegeIndex.')
            ->assertSee($included->name)->assertDontSee($excluded->name)->assertDontSee($elsewhere->name)
            ->assertSee('href="'.$detail.'"', false)
            ->assertSee('href="'.route('cities.show', $city).'"', false)
            ->assertSee('Alle Pflegeeinrichtungen in Neuruppin')
            ->assertSee('aria-label="Breadcrumb"', false)->assertDontSee('noindex');
        $this->assertSame(1, substr_count($response->getContent(), '<h1>'));
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $match);
        $graph = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR)['@graph'];
        $this->assertSame('CollectionPage', $graph[0]['@type']);
        $this->assertSame(1, $graph[0]['mainEntity']['numberOfItems']);
        $this->assertSame($detail, $graph[0]['mainEntity']['itemListElement'][0]['url']);
        $this->assertSame('BreadcrumbList', $graph[1]['@type']);
        $this->assertSame(['PflegeIndex', 'Brandenburg', 'Neuruppin', 'Ambulante Pflegedienste'], array_column($graph[1]['itemListElement'], 'name'));
        $this->assertSame($url, $graph[1]['itemListElement'][3]['item']);
    }

    public function test_city_and_matching_facility_link_only_to_published_category(): void
    {
        $city = $this->city('Neuruppin', 'neuruppin');
        $ambulant = $this->facility($city, 'Ambulante Beispielpflege', 'Ambulante Pflege');
        $day = $this->facility($city, 'Tagespflege Beispiel', 'Stationäre/teilstationäre Pflege');
        $url = route('cities.care.show', [$city, 'ambulante-pflegedienste']);
        $this->get(route('cities.show', $city))->assertOk()->assertSee('href="'.$url.'"', false)
            ->assertDontSee('/neuruppin/tagespflege.html');
        $this->get(route('facilities.show', [$city, $ambulant]))->assertOk()
            ->assertSee('href="'.$url.'"', false)->assertSee('Weitere ambulante Pflegedienste in Neuruppin');
        $this->get(route('facilities.show', [$city, $day]))->assertOk()->assertDontSee('href="'.$url.'"', false);
    }

    public function test_empty_unknown_unpublished_and_wrong_state_combinations_return_404(): void
    {
        $city = $this->city('Neuruppin', 'neuruppin');
        $this->facility($city, 'Tagespflege ohne ambulanten Dienst', 'Stationäre/teilstationäre Pflege');
        foreach (['neuruppin/ambulante-pflegedienste', 'neuruppin/tagespflege', 'neuruppin/unbekannt', 'unbekannt/tagespflege', 'frankfurt-an-der-oder/pflegeheime'] as $path) {
            $this->get('/brandenburg/'.$path.'.html')->assertNotFound();
        }
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/neuruppin/ambulante-pflegedienste.html');
        $city->update(['state_slug' => 'sachsen']);
        $this->facility($city, 'Ambulante Pflege in anderem Bundesland', 'Ambulante Pflege');
        $this->get('/brandenburg/neuruppin/ambulante-pflegedienste.html')->assertNotFound();
    }

    public function test_all_five_pilots_are_unique_indexable_and_in_sitemap(): void
    {
        $titles = $headings = $descriptions = [];
        $urls = [];
        foreach ([
            ['Potsdam', 'potsdam', 'ambulante-pflegedienste', 'Ambulante Pflege', 'Pflegedienst Potsdam'],
            ['Neuruppin', 'neuruppin', 'ambulante-pflegedienste', 'Ambulante Pflege', 'Pflegedienst Neuruppin'],
            ['Falkensee', 'falkensee', 'tagespflege', 'Stationäre/teilstationäre Pflege', 'ASB Tagespflege Falkensee'],
            ['Frankfurt (Oder)', 'frankfurt-oder', 'pflegeheime', 'Stationäre/teilstationäre Pflege', 'Altenpflegeheim Theodor-Fliedner-Haus'],
            ['Cottbus', 'cottbus', 'tagespflege', 'Stationäre/teilstationäre Pflege', 'Tagespflege Cottbus'],
        ] as [$name, $slug, $category, $type, $facilityName]) {
            $city = $this->city($name, $slug);
            $facility = $this->facility($city, $facilityName, $type);
            $url = route('cities.care.show', [$city, $category]);
            $urls[] = $url;
            $response = $this->get($url)->assertOk()->assertDontSee('noindex')
                ->assertSee('<link rel="canonical" href="'.$url.'">', false)
                ->assertSee('content="index, follow"', false);
            $html = $response->getContent();
            preg_match('/<title>(.*?)<\/title>/', $html, $title);
            preg_match_all('/<h1>(.*?)<\/h1>/', $html, $h1);
            preg_match('/<meta name="description" content="(.*?)">/', $html, $description);
            $titles[] = $title[1];
            $headings[] = $h1[1][0];
            $descriptions[] = $description[1];
            $this->assertCount(1, $h1[1]);
            $this->get(route('cities.show', $city))->assertOk()->assertSee('href="'.$url.'"', false);
            $this->get(route('facilities.show', [$city, $facility]))->assertOk()->assertSee('href="'.$url.'"', false);
        }
        $this->assertCount(5, array_unique($titles));
        $this->assertCount(5, array_unique($headings));
        $this->assertCount(5, array_unique($descriptions));
        $sitemap = $this->get('/sitemap.xml')->assertOk();
        foreach ($urls as $url) {
            $sitemap->assertSee('<loc>'.$url.'</loc>', false);
        }
        $this->assertNotFalse(simplexml_load_string($sitemap->getContent()));
        $this->get('/robots.txt')->assertOk()->assertSee('Allow: /');
    }

    public function test_broad_sector_is_not_assumed_to_be_a_nursing_home(): void
    {
        $city = $this->city('Frankfurt (Oder)', 'frankfurt-oder');
        $home = $this->facility($city, 'Seniorenheim Beispiel', 'Stationäre/teilstationäre Pflege');
        foreach (['Seniorenheim Beispiel Tagespflege', 'Unklare Einrichtung', 'Seniorenzentrum Kurzzeitpflege', 'Seniorenzentrum Wohngruppe'] as $name) {
            $this->facility($city, $name, 'Stationäre/teilstationäre Pflege');
        }
        $this->facility($city, 'Krankenhaus Pflegeheimstraße', 'Krankenhaus');
        $ids = app(CarePageService::class)->facilities($city, 'pflegeheime')->pluck('id')->all();
        $this->assertSame([$home->id], $ids);
    }

    public function test_day_care_supports_explicit_taxonomy_and_combined_day_night_name(): void
    {
        $city = $this->city('Cottbus', 'cottbus');
        $explicit = $this->facility($city, 'Haus A', 'Tagespflege');
        $name = $this->facility($city, 'Haus B Tages- und Nachtpflege', 'Stationäre/teilstationäre Pflege');
        $careType = $this->facility($city, 'Haus C', 'Stationäre/teilstationäre Pflege');
        $careType->update(['care_types' => ['Tagespflege']]);
        $this->facility($city, 'Seniorenheim D', 'Stationäre/teilstationäre Pflege');
        $this->facility($city, 'Ambulanter Anbieter mit Tagespflege im Namen', 'Ambulante Pflege');
        $this->assertEqualsCanonicalizing([$explicit->id, $name->id, $careType->id], app(CarePageService::class)->facilities($city, 'tagespflege')->pluck('id')->all());
    }

    public function test_card_queries_do_not_grow_with_facility_count(): void
    {
        $city = $this->city('Potsdam', 'potsdam');
        $this->facility($city, 'Pflege A', 'Ambulante Pflege');
        $url = route('cities.care.show', [$city, 'ambulante-pflegedienste']);
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $firstCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 35; $i++) {
            $this->facility($city, 'Pflege '.$i, 'Ambulante Pflege');
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get($url)->assertOk();
        $this->assertLessThanOrEqual($firstCount, count(DB::getQueryLog()));
        $this->assertSame(36, substr_count($response->getContent(), '<article class="result-card">'));
        DB::disableQueryLog();
    }

    public function test_verified_structured_corrections_are_included_without_broadening_name_fallback(): void
    {
        $frankfurt = $this->city('Frankfurt (Oder)', 'frankfurt-oder');
        $seniorenhaus = $this->facility($frankfurt, 'Seniorenhaus', 'Pflegeheim');
        $wohngruppeOne = $this->facility($frankfurt, 'Wohn- und Pflegezentrum Wohngruppe eins', 'Stationäre/teilstationäre Pflege');
        $wohngruppeTwo = $this->facility($frankfurt, 'Wohn- und Pflegezentrum Wohngruppe zwei', 'Stationäre/teilstationäre Pflege');

        $cottbus = $this->city('Cottbus', 'cottbus');
        $spreewehr = $this->facility($cottbus, 'Tagesbetreuung "Am großen Spreewehr"', 'Tagespflege');

        $falkensee = $this->city('Falkensee', 'falkensee');
        $daycare = $this->facility($falkensee, 'DeFalia Daycare', 'Stationäre/teilstationäre Pflege');

        $homes = app(CarePageService::class)->facilities($frankfurt, 'pflegeheime')->pluck('id')->all();
        $dayCare = app(CarePageService::class)->facilities($cottbus, 'tagespflege')->pluck('id')->all();
        $falkenseeDayCare = app(CarePageService::class)->facilities($falkensee, 'tagespflege')->pluck('id')->all();

        $this->assertContains($seniorenhaus->id, $homes);
        $this->assertNotContains($wohngruppeOne->id, $homes);
        $this->assertNotContains($wohngruppeTwo->id, $homes);
        $this->assertContains($spreewehr->id, $dayCare);
        $this->assertNotContains($daycare->id, $falkenseeDayCare);
    }

    public function test_confirmed_classification_overrides_survive_a_raw_import(): void
    {
        $path = storage_path('framework/testing/city-care-classification-overrides.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            $this->importRecord('seniorenhaus-15232-e3f69200ae', 'Seniorenhaus', 'Frankfurt (Oder)', 'frankfurt-oder'),
            $this->importRecord('tagesbetreuung-am-grossen-spreewehr-03044-bfc0412ceb', 'Tagesbetreuung "Am großen Spreewehr"', 'Cottbus', 'cottbus'),
        ], JSON_THROW_ON_ERROR));

        try {
            $this->artisan('pflegeindex:import', ['path' => $path])->assertSuccessful();
        } finally {
            File::delete($path);
        }

        $this->assertDatabaseHas('facilities', ['source_id' => 'seniorenhaus-15232-e3f69200ae', 'type' => 'Pflegeheim']);
        $this->assertDatabaseHas('facilities', ['source_id' => 'tagesbetreuung-am-grossen-spreewehr-03044-bfc0412ceb', 'type' => 'Tagespflege']);

        $frankfurt = City::query()->where('slug', 'frankfurt-oder')->firstOrFail();
        $cottbus = City::query()->where('slug', 'cottbus')->firstOrFail();
        $this->assertCount(1, app(CarePageService::class)->facilities($frankfurt, 'pflegeheime')->get());
        $this->assertCount(1, app(CarePageService::class)->facilities($cottbus, 'tagespflege')->get());
    }

    /** @return array<string, mixed> */
    private function importRecord(string $id, string $name, string $city, string $citySlug): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'city' => $city,
            'citySlug' => $citySlug,
            'slug' => $id,
            'postalCode' => '00000',
            'address' => 'Beispielstraße 1',
            'type' => 'Stationäre/teilstationäre Pflege',
            'careTypes' => ['Stationäre/teilstationäre Pflege'],
            'features' => [],
        ];
    }

    private function city(string $name, string $slug): City
    {
        return City::create(['name' => $name, 'slug' => $slug, 'state' => 'Brandenburg', 'state_slug' => 'brandenburg']);
    }

    private function facility(City $city, string $name, string $type): Facility
    {
        $number = Facility::count() + 1;

        return Facility::create([
            'city_id' => $city->id, 'source_id' => 'care-test-'.$number,
            'slug' => 'einrichtung-'.$number, 'name' => $name, 'type' => $type,
            'address' => 'Beispielstraße 1', 'postal_code' => '16816',
            'care_types' => [$type], 'features' => [],
        ]);
    }
}

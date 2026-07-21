<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Projects\PflegeIndex\Directory\Presentation\PflegeEntryCardViewModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DirectoryCorePageTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_directory_renders_core_results_with_unchanged_card_url_and_seo(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam');
        $facility = $this->createFacility(
            $city,
            'Beispiel Pflegezentrum',
            'Ambulante Pflege',
            'Musterstraße 1',
            '14467',
            '+4933188700',
        );
        $canonical = route('directory.index');
        $facilityUrl = route('facilities.show', [$city, $facility]);

        $response = $this->get($canonical)->assertOk();
        $paginator = $this->paginator($response);

        $response
            ->assertSee($facility->name)
            ->assertSee('href="'.$facilityUrl.'"', false)
            ->assertSee('+49 331 88700')
            ->assertSee('<title>Pflegeangebote finden – PflegeIndex</title>', false)
            ->assertSee('<meta name="description" content="Pflegeangebote in Brandenburg nach Ort, Postleitzahl, Name und Einrichtungsart durchsuchen.">', false)
            ->assertSee('<link rel="canonical" href="'.$canonical.'">', false);

        $this->assertInstanceOf(PflegeEntryCardViewModel::class, $paginator->items()[0]);
        $this->assertSame(1, $paginator->total());
        $this->assertSame(24, $paginator->perPage());
    }

    public function test_directory_searches_by_name_address_and_postal_code(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam');
        $matching = $this->createFacility(
            $city,
            'Park Pflege',
            'Ambulante Pflege',
            'Lindenweg 17',
            '14469',
        );
        $other = $this->createFacility(
            $city,
            'Anderes Zentrum',
            'Ambulante Pflege',
            'Musterstraße 2',
            '14467',
        );

        foreach (['Park Pflege', 'Lindenweg', '14469'] as $query) {
            $this->get(route('directory.index', ['q' => $query]))
                ->assertOk()
                ->assertSee($matching->name)
                ->assertDontSee($other->name);
        }
    }

    public function test_directory_filter_parameters_are_noindex_but_plain_catalog_and_pagination_are_indexable(): void
    {
        $robotsMeta = '<meta name="robots" content="noindex,follow">';
        $city = $this->createCity('Potsdam', 'potsdam');

        foreach (range(1, 25) as $number) {
            $this->createFacility(
                $city,
                sprintf('Pflege Potsdam %02d', $number),
                'Ambulante Pflege',
            );
        }

        $this->get(route('directory.index'))
            ->assertOk()
            ->assertDontSee($robotsMeta, false);

        $this->get(route('directory.index', ['page' => 2]))
            ->assertOk()
            ->assertDontSee($robotsMeta, false);

        foreach ([
            ['q' => 'Potsdam'],
            ['city' => 'potsdam'],
            ['type' => 'Ambulante Pflege'],
            ['q' => 'Potsdam', 'page' => 2],
            ['pflegeform' => 'ambulant'],
            ['q' => ''],
        ] as $parameters) {
            $this->get(route('directory.index', $parameters))
                ->assertOk()
                ->assertSee($robotsMeta, false);
        }
    }

    public function test_search_city_and_type_filters_keep_and_semantics_in_every_combination(): void
    {
        $potsdam = $this->createCity('Potsdam', 'potsdam');
        $calau = $this->createCity('Calau', 'calau');
        $matching = $this->createFacility($potsdam, 'Park Ambulant Potsdam', 'Ambulante Pflege');
        $wrongType = $this->createFacility($potsdam, 'Park Krankenhaus Potsdam', 'Krankenhaus');
        $wrongCity = $this->createFacility($calau, 'Park Ambulant Calau', 'Ambulante Pflege');
        $wrongSearch = $this->createFacility($potsdam, 'Zuhause Ambulant Potsdam', 'Ambulante Pflege');

        $this->assertDirectoryResult(['q' => 'Park'], [$matching, $wrongType, $wrongCity], [$wrongSearch]);
        $this->assertDirectoryResult(['city' => 'potsdam'], [$matching, $wrongType, $wrongSearch], [$wrongCity]);
        $this->assertDirectoryResult(['type' => 'Ambulante Pflege'], [$matching, $wrongCity, $wrongSearch], [$wrongType]);
        $this->assertDirectoryResult(['q' => 'Park', 'city' => 'potsdam'], [$matching, $wrongType], [$wrongCity, $wrongSearch]);
        $this->assertDirectoryResult(['q' => 'Park', 'type' => 'Ambulante Pflege'], [$matching, $wrongCity], [$wrongType, $wrongSearch]);
        $this->assertDirectoryResult(['city' => 'potsdam', 'type' => 'Ambulante Pflege'], [$matching, $wrongSearch], [$wrongType, $wrongCity]);
        $this->assertDirectoryResult(
            ['q' => 'Park', 'city' => 'potsdam', 'type' => 'Ambulante Pflege'],
            [$matching],
            [$wrongType, $wrongCity, $wrongSearch],
        );
    }

    public function test_empty_filter_parameters_match_absent_parameters(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam');
        $facility = $this->createFacility($city, 'Pflege Potsdam', 'Ambulante Pflege');

        $withoutParameters = $this->paginator($this->get(route('directory.index'))->assertOk());
        $withEmptyParameters = $this->paginator($this->get(route('directory.index', [
            'q' => '   ',
            'city' => '   ',
            'type' => '   ',
        ]))->assertOk());

        $this->assertSame($withoutParameters->total(), $withEmptyParameters->total());
        $this->assertSame(1, $withEmptyParameters->currentPage());
        $this->assertSame($facility->name, $withEmptyParameters->items()[0]->name);
    }

    public function test_directory_paginates_by_24_stably_and_preserves_filters_in_links(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam');

        for ($index = 1; $index <= 25; $index++) {
            $this->createFacility(
                $city,
                'Pflege am Park',
                'Ambulante Pflege',
                "Sortierung {$index}",
            );
        }

        $parameters = [
            'q' => 'Pflege',
            'city' => 'potsdam',
            'type' => 'Ambulante Pflege',
        ];
        $response = $this->get(route('directory.index', $parameters))->assertOk();
        $paginator = $this->paginator($response);
        $nextPageUrl = $paginator->nextPageUrl();

        $this->assertCount(24, $paginator->items());
        $this->assertSame('Sortierung 1', $paginator->items()[0]->address);
        $this->assertSame('Sortierung 24', $paginator->items()[23]->address);
        $this->assertNotNull($nextPageUrl);
        $this->assertSame(1, substr_count((string) parse_url($nextPageUrl, PHP_URL_QUERY), 'page='));

        parse_str((string) parse_url($nextPageUrl, PHP_URL_QUERY), $nextPageQuery);
        $this->assertSame($parameters + ['page' => '2'], $nextPageQuery);
        $response->assertSee('href="'.e($nextPageUrl).'"', false);

        $secondPage = $this->paginator($this->get($nextPageUrl)->assertOk());
        $this->assertSame(2, $secondPage->currentPage());
        $this->assertCount(1, $secondPage->items());
        $this->assertSame('Sortierung 25', $secondPage->items()[0]->address);
    }

    public function test_directory_pagination_has_page_specific_seo_metadata(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam');

        foreach (range(1, 49) as $number) {
            $this->createFacility(
                $city,
                sprintf('SEO Einrichtung %02d', $number),
                'Ambulante Pflege',
            );
        }

        foreach ([1, 2, 3] as $page) {
            $canonical = $page === 1
                ? route('directory.index')
                : route('directory.index', ['page' => $page]);
            $title = $page === 1
                ? 'Pflegeangebote finden – PflegeIndex'
                : "Pflegeangebote finden – Seite {$page} – PflegeIndex";
            $description = $page === 1
                ? 'Pflegeangebote in Brandenburg nach Ort, Postleitzahl, Name und Einrichtungsart durchsuchen.'
                : "Seite {$page} mit weiteren Pflegeangeboten in Brandenburg.";
            $response = $this->get(route('directory.index', ['page' => $page]))->assertOk();

            $response
                ->assertSee('<title>'.$title.'</title>', false)
                ->assertSee('<meta name="description" content="'.$description.'">', false)
                ->assertSee('<link rel="canonical" href="'.$canonical.'">', false);
        }
    }

    public function test_directory_shows_the_existing_empty_state(): void
    {
        $this->get(route('directory.index', ['q' => 'nicht-vorhanden']))
            ->assertOk()
            ->assertSee('Keine passenden Einrichtungen')
            ->assertSee('Ändern Sie den Suchbegriff oder setzen Sie die Filter zurück.');
    }

    public function test_directory_keeps_a_constant_query_count_without_n_plus_one(): void
    {
        $potsdam = $this->createCity('Potsdam', 'potsdam');
        $calau = $this->createCity('Calau', 'calau');

        for ($index = 1; $index <= 5; $index++) {
            $this->createFacility($index % 2 === 0 ? $potsdam : $calau, "Pflege {$index}", 'Ambulante Pflege');
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->get(route('directory.index'))->assertOk();

        $this->assertLessThanOrEqual(6, $queryCount, 'Directory page introduced too many SQL queries.');
    }

    /**
     * @param  array<string, string>  $parameters
     * @param  list<Facility>  $included
     * @param  list<Facility>  $excluded
     */
    private function assertDirectoryResult(array $parameters, array $included, array $excluded): void
    {
        $response = $this->get(route('directory.index', $parameters))->assertOk();

        foreach ($included as $facility) {
            $response->assertSee($facility->name);
        }

        foreach ($excluded as $facility) {
            $response->assertDontSee($facility->name);
        }
    }

    private function paginator(TestResponse $response): LengthAwarePaginator
    {
        $paginator = $response->viewData('facilities');

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);

        return $paginator;
    }

    private function createCity(string $name, string $slug): City
    {
        return City::create([
            'name' => $name,
            'slug' => $slug,
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);
    }

    private function createFacility(
        City $city,
        string $name,
        string $type,
        string $address = 'Musterstraße 1',
        string $postalCode = '14467',
        ?string $phone = null,
    ): Facility {
        $this->sequence++;

        return Facility::create([
            'source_id' => "directory-page-{$this->sequence}",
            'city_id' => $city->id,
            'name' => $name,
            'slug' => "directory-page-{$this->sequence}",
            'postal_code' => $postalCode,
            'address' => $address,
            'type' => $type,
            'phone' => $phone,
            'care_types' => [$type],
            'features' => [],
        ]);
    }
}

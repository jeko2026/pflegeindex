<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Models\GeoCountry;
use App\Models\GeoDistrict;
use App\Models\GeoMunicipality;
use App\Models\GeoState;
use App\Platform\DirectoryCore\Domain\EntrySort;
use App\Platform\DirectoryCore\Domain\LocationScope;
use App\Platform\DirectoryCore\Domain\LocationScopeType;
use App\Platform\DirectoryCore\Domain\PaginationOptions;
use App\Platform\DirectoryCore\ReadModel\EntrySummary;
use App\Platform\DirectoryCore\ReadModel\ListingCriteria;
use App\Platform\DirectoryCore\ReadModel\ListingResult;
use App\Projects\PflegeIndex\Directory\PflegeEntryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PflegeEntryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_paginated_entry_summaries_sorted_by_name(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam');
        $this->createFacility($city, 'Zulu Pflege', 'Ambulante Pflege');
        $alpha = $this->createFacility($city, 'Alpha Pflege', 'Stationäre Pflege');

        $result = $this->repository()->list(new ListingCriteria(
            pagination: new PaginationOptions(page: 1, perPage: 1),
            sort: EntrySort::NameAscending,
        ));

        $this->assertInstanceOf(ListingResult::class, $result);
        $this->assertCount(1, $result->entries);
        $this->assertInstanceOf(EntrySummary::class, $result->entries[0]);
        $this->assertSame($alpha->name, $result->entries[0]->name);
        $this->assertSame(LocationScopeType::City, $result->entries[0]->locationScope?->type);
        $this->assertSame('potsdam', $result->entries[0]->locationScope?->identifier);
        $this->assertSame('Potsdam', $result->entries[0]->locationName);
        $this->assertSame(2, $result->total);
        $this->assertSame(2, $result->lastPage());
        $this->assertTrue($result->hasNextPage());
    }

    public function test_default_sort_uses_city_facility_and_id_order(): void
    {
        $potsdam = $this->createCity('Potsdam', 'potsdam');
        $calau = $this->createCity('Calau', 'calau');
        $potsdamFacility = $this->createFacility($potsdam, 'Alpha Pflege', 'Ambulante Pflege');
        $firstCalauFacility = $this->createFacility($calau, 'Zulu Pflege', 'Ambulante Pflege');
        $secondCalauFacility = $this->createFacility($calau, 'Zulu Pflege', 'Ambulante Pflege');

        $result = $this->repository()->list(new ListingCriteria(
            pagination: new PaginationOptions(1, 24),
            sort: EntrySort::Default,
        ));

        $this->assertSame([
            (string) $firstCalauFacility->id,
            (string) $secondCalauFacility->id,
            (string) $potsdamFacility->id,
        ], array_map(
            fn (EntrySummary $entry): string => $entry->id->value(),
            $result->entries,
        ));
    }

    public function test_it_applies_search_location_and_category_filters(): void
    {
        $potsdam = $this->createCity('Potsdam', 'potsdam');
        $calau = $this->createCity('Calau', 'calau');
        $matching = $this->createFacility($potsdam, 'Pflege am Park', 'Ambulante Pflege');
        $this->createFacility($potsdam, 'Klinik am Park', 'Krankenhaus');
        $this->createFacility($calau, 'Pflege am Park Calau', 'Ambulante Pflege');

        $result = $this->repository()->list(new ListingCriteria(
            pagination: new PaginationOptions(1, 24),
            sort: EntrySort::Default,
            searchQuery: 'Park',
            locationScope: LocationScope::city('potsdam'),
            categoryIdentifier: 'Ambulante Pflege',
        ));

        $this->assertSame(1, $result->total);
        $this->assertSame($matching->name, $result->entries[0]->name);
        $this->assertSame('Ambulante Pflege', $result->entries[0]->categoryIdentifier);
        $this->assertSame('14467', $result->entries[0]->postalCode);
    }

    public function test_it_eager_loads_cities_in_one_relation_query(): void
    {
        $potsdam = $this->createCity('Potsdam', 'potsdam');
        $calau = $this->createCity('Calau', 'calau');
        $this->createFacility($potsdam, 'Pflege Potsdam', 'Ambulante Pflege');
        $this->createFacility($calau, 'Pflege Calau', 'Ambulante Pflege');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->repository()->list(new ListingCriteria(
            pagination: new PaginationOptions(1, 24),
            sort: EntrySort::Default,
        ));

        $cityRelationQueries = array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], 'from "cities" where "cities"."id" in'),
        );

        $this->assertCount(1, $cityRelationQueries);
    }

    public function test_it_does_not_filter_when_location_scope_is_absent(): void
    {
        $potsdam = $this->createCity('Potsdam', 'potsdam');
        $calau = $this->createCity('Calau', 'calau');
        $this->createFacility($potsdam, 'Pflege Potsdam', 'Ambulante Pflege');
        $this->createFacility($calau, 'Pflege Calau', 'Ambulante Pflege');

        $result = $this->repository()->list(new ListingCriteria(
            pagination: new PaginationOptions(1, 24),
            sort: EntrySort::Default,
        ));

        $this->assertSame(2, $result->total);
    }

    public function test_it_filters_district_scope_through_real_geocore_relations(): void
    {
        $targetDistrict = $this->createDistrict('12061', 'dahme-spreewald');
        $otherDistrict = $this->createDistrict('12065', 'oberhavel');
        $targetCity = $this->createLinkedCity($targetDistrict, 'Lübben', 'luebben');
        $otherCity = $this->createLinkedCity($otherDistrict, 'Oranienburg', 'oranienburg');
        $unlinkedCity = $this->createCity('Ortsteil Test', 'ortsteil-test');
        $matching = $this->createFacility($targetCity, 'Pflege Lübben', 'Ambulante Pflege');
        $otherDistrictFacility = $this->createFacility($otherCity, 'Pflege Oberhavel', 'Ambulante Pflege');
        $unlinkedFacility = $this->createFacility($unlinkedCity, 'Pflege Ortsteil', 'Ambulante Pflege');

        $result = $this->repository()->list(new ListingCriteria(
            pagination: new PaginationOptions(1, 24),
            sort: EntrySort::Default,
            locationScope: LocationScope::district($targetDistrict->ags),
        ));

        $this->assertSame(1, $result->total);
        $this->assertSame([$matching->name], array_column($result->entries, 'name'));
        $this->assertNotContains($otherDistrictFacility->name, array_column($result->entries, 'name'));
        $this->assertNotContains($unlinkedFacility->name, array_column($result->entries, 'name'));
    }

    public function test_district_scope_preserves_default_sort_and_pagination(): void
    {
        $district = $this->createDistrict('12060', 'barnim');
        $alphaCity = $this->createLinkedCity($district, 'Ahrensfelde', 'ahrensfelde');
        $betaCity = $this->createLinkedCity($district, 'Bernau', 'bernau');

        foreach (range(1, 24) as $number) {
            $this->createFacility($alphaCity, sprintf('Ahrensfelde Pflege %02d', $number), 'Ambulante Pflege');
        }

        $last = $this->createFacility($betaCity, 'Bernau Pflege 01', 'Ambulante Pflege');
        $criteria = fn (int $page): ListingCriteria => new ListingCriteria(
            pagination: new PaginationOptions($page, 24),
            sort: EntrySort::Default,
            locationScope: LocationScope::district($district->ags),
        );

        $firstPage = $this->repository()->list($criteria(1));
        $secondPage = $this->repository()->list($criteria(2));

        $this->assertSame(25, $firstPage->total);
        $this->assertCount(24, $firstPage->entries);
        $this->assertSame('Ahrensfelde Pflege 01', $firstPage->entries[0]->name);
        $this->assertSame('Ahrensfelde Pflege 24', $firstPage->entries[23]->name);
        $this->assertTrue($firstPage->hasNextPage());
        $this->assertCount(1, $secondPage->entries);
        $this->assertSame($last->name, $secondPage->entries[0]->name);
    }

    public function test_it_filters_state_scope_by_official_geocore_relation_with_unmapped_legacy_fallback(): void
    {
        $brandenburg = $this->createState('12', 'Brandenburg', 'brandenburg');
        $saxony = $this->createState('14', 'Sachsen', 'sachsen');
        $brandenburgDistrict = $this->createDistrict('12054', 'potsdam', $brandenburg);
        $saxonyDistrict = $this->createDistrict('14612', 'dresden', $saxony);
        $officialBrandenburg = $this->createLinkedCity(
            $brandenburgDistrict,
            'Cottbus',
            'cottbus',
        );
        $officialSaxony = $this->createLinkedCity(
            $saxonyDistrict,
            'Dresden',
            'dresden',
            'Sachsen',
            'sachsen',
        );
        $legacyBrandenburg = $this->createCity('Potsdam', 'potsdam');
        $legacySaxony = $this->createCity('Leipzig', 'leipzig', 'Sachsen', 'sachsen');
        $cottbusFacility = $this->createFacility($officialBrandenburg, 'Pflege Cottbus', 'Ambulante Pflege');
        $potsdamFacility = $this->createFacility($legacyBrandenburg, 'Pflege Potsdam', 'Ambulante Pflege');
        $dresdenFacility = $this->createFacility($officialSaxony, 'Pflege Dresden', 'Ambulante Pflege');
        $leipzigFacility = $this->createFacility($legacySaxony, 'Pflege Leipzig', 'Ambulante Pflege');

        $result = $this->repository()->list(new ListingCriteria(
            pagination: new PaginationOptions(1, 24),
            sort: EntrySort::Default,
            locationScope: LocationScope::state($brandenburg->slug),
        ));

        $this->assertSame(2, $result->total);
        $this->assertSame(
            [$cottbusFacility->name, $potsdamFacility->name],
            array_column($result->entries, 'name'),
        );
        $this->assertNotContains($dresdenFacility->name, array_column($result->entries, 'name'));
        $this->assertNotContains($leipzigFacility->name, array_column($result->entries, 'name'));
    }

    private function repository(): PflegeEntryRepository
    {
        return new PflegeEntryRepository;
    }

    private function createCity(
        string $name,
        string $slug,
        string $state = 'Brandenburg',
        string $stateSlug = 'brandenburg',
    ): City {
        return City::create([
            'name' => $name,
            'slug' => $slug,
            'state' => $state,
            'state_slug' => $stateSlug,
        ]);
    }

    private function createState(string $ags, string $name, string $slug): GeoState
    {
        $country = GeoCountry::query()->firstOrCreate(
            ['iso2' => 'DE'],
            ['iso3' => 'DEU', 'name' => 'Deutschland', 'slug' => 'deutschland'],
        );

        return GeoState::query()->firstOrCreate(
            ['country_id' => $country->id, 'ags' => $ags],
            ['name' => $name, 'slug' => $slug],
        );
    }

    private function createDistrict(
        string $ags,
        string $slug,
        ?GeoState $state = null,
    ): GeoDistrict {
        $state ??= $this->createState('12', 'Brandenburg', 'brandenburg');

        return GeoDistrict::create([
            'state_id' => $state->id,
            'ags' => $ags,
            'name' => str_replace('-', ' ', ucfirst($slug)),
            'slug' => $slug,
            'type' => 'landkreis',
        ]);
    }

    private function createLinkedCity(
        GeoDistrict $district,
        string $name,
        string $slug,
        string $state = 'Brandenburg',
        string $stateSlug = 'brandenburg',
    ): City {
        $sequence = GeoMunicipality::query()->count() + 1;
        $municipality = GeoMunicipality::create([
            'district_id' => $district->id,
            'ags' => str_pad((string) (12000000 + $sequence), 8, '0', STR_PAD_LEFT),
            'name' => $name,
            'normalized_name' => $slug,
            'slug' => $slug,
            'source_name' => 'GeoCore Test',
        ]);

        return City::create([
            'name' => $name,
            'slug' => $slug,
            'state' => $state,
            'state_slug' => $stateSlug,
            'geo_municipality_id' => $municipality->id,
            'geo_match_status' => 'exact',
            'geo_match_method' => 'exact_official_name',
            'geo_match_confidence' => 'high',
            'geo_requires_manual_review' => false,
        ]);
    }

    private function createFacility(City $city, string $name, string $type): Facility
    {
        $sequence = Facility::query()->count() + 1;

        return Facility::create([
            'source_id' => "directory-core-{$sequence}",
            'city_id' => $city->id,
            'name' => $name,
            'slug' => "directory-core-{$sequence}",
            'postal_code' => '14467',
            'address' => "Musterstraße {$sequence}",
            'type' => $type,
            'care_types' => [$type],
            'features' => [],
        ]);
    }
}

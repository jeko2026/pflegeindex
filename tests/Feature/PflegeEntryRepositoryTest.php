<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
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
use LogicException;
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

    public function test_it_rejects_district_location_scope_until_it_is_supported(): void
    {
        $this->assertUnsupportedLocationScope(LocationScope::district('potsdam-mittelmark'));
    }

    public function test_it_rejects_state_location_scope_until_it_is_supported(): void
    {
        $this->assertUnsupportedLocationScope(LocationScope::state('brandenburg'));
    }

    private function assertUnsupportedLocationScope(LocationScope $scope): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Location scope {$scope->type->name} is not supported");

        $this->repository()->list(new ListingCriteria(
            pagination: new PaginationOptions(1, 24),
            sort: EntrySort::Default,
            locationScope: $scope,
        ));
    }

    private function repository(): PflegeEntryRepository
    {
        return new PflegeEntryRepository;
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

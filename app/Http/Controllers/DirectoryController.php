<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use App\Platform\DirectoryCore\Application\ListEntries;
use App\Platform\DirectoryCore\Domain\EntrySort;
use App\Platform\DirectoryCore\Domain\LocationScope;
use App\Platform\DirectoryCore\Domain\PaginationOptions;
use App\Platform\DirectoryCore\ReadModel\ListingCriteria;
use App\Projects\PflegeIndex\Directory\PflegeEntryRepository;
use App\Projects\PflegeIndex\Directory\Presentation\PflegeEntryPresenter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class DirectoryController extends Controller
{
    public function index(
        Request $request,
        PflegeEntryRepository $repository,
        PflegeEntryPresenter $presenter,
    ): View {
        $query = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $citySlug = trim((string) $request->query('city', ''));
        $page = max(1, $request->integer('page', 1));

        $listingResult = (new ListEntries($repository))->execute(new ListingCriteria(
            pagination: new PaginationOptions(page: $page, perPage: 24),
            sort: EntrySort::Default,
            searchQuery: $query,
            locationScope: $citySlug === '' ? null : LocationScope::city($citySlug),
            categoryIdentifier: $type,
        ));

        $facilities = new LengthAwarePaginator(
            items: $presenter->presentMany($listingResult->entries),
            total: $listingResult->total,
            perPage: $listingResult->perPage,
            currentPage: $listingResult->currentPage,
            options: ['path' => $request->url()],
        );
        $facilities->withQueryString();

        return view('directory.index', [
            'facilities' => $facilities,
            'cities' => City::query()->withCount('facilities')->orderBy('name')->get(),
            'types' => Facility::query()->distinct()->orderBy('type')->pluck('type'),
            'query' => $query,
            'selectedType' => $type,
            'selectedCity' => $citySlug,
            'totalCount' => Facility::count(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $citySlug = trim((string) $request->query('city', ''));

        $facilities = Facility::query()
            ->select('facilities.*')
            ->join('cities', 'cities.id', '=', 'facilities.city_id')
            ->with('city')
            ->when($query !== '', function (Builder $builder) use ($query): void {
                $builder->where(function (Builder $search) use ($query): void {
                    $like = "%{$query}%";
                    $search->where('facilities.name', 'like', $like)
                        ->orWhere('facilities.address', 'like', $like)
                        ->orWhere('facilities.postal_code', 'like', $like)
                        ->orWhere('cities.name', 'like', $like);
                });
            })
            ->when($type !== '', fn (Builder $builder) => $builder->where('facilities.type', $type))
            ->when($citySlug !== '', fn (Builder $builder) => $builder->where('cities.slug', $citySlug))
            ->orderBy('cities.name')
            ->orderBy('facilities.name')
            ->paginate(24)
            ->withQueryString();

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

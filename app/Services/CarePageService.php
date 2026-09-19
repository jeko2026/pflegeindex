<?php

namespace App\Services;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Builder;

class CarePageService
{
    public function category(City $city, string $slug): ?array
    {
        if ($city->state_slug !== 'brandenburg'
            || ! in_array($slug, config('care_pages.pilots.'.$city->slug, []), true)) {
            return null;
        }

        $category = config('care_pages.categories.'.$slug);

        return $category ? ['slug' => $slug, ...$category] : null;
    }

    public function facilities(City $city, string $slug): Builder
    {
        $category = $this->category($city, $slug);
        $query = Facility::query()->where('city_id', $city->id);

        if ($category === null) {
            return $query->whereRaw('1 = 0');
        }

        // Exact taxonomy wins. Broad LASV records require an explicit name marker;
        // absence of "Tagespflege" alone never makes a record a nursing home.
        return $query->where(function (Builder $match) use ($category, $slug): void {
            $match->whereIn('type', $category['types']);
            $match->orWhere(function (Builder $broad) use ($category, $slug): void {
                $broad->where('type', 'Stationäre/teilstationäre Pflege')
                    ->where(function (Builder $specific) use ($category): void {
                        $specific->whereRaw('1 = 0');
                        foreach ($category['types'] as $type) {
                            $specific->orWhereJsonContains('care_types', $type);
                        }
                        foreach ($category['name_markers'] as $marker) {
                            $specific->orWhere('name', 'like', '%'.$marker.'%');
                        }
                    });

                if ($slug === 'pflegeheime') {
                    foreach (['tagespflege', 'tages- und nachtpflege', 'nachtpflege', 'kurzzeitpflege', 'wohngruppe', 'wohngemeinschaft', 'betreutes wohnen'] as $excluded) {
                        $broad->where('name', 'not like', '%'.$excluded.'%');
                    }
                    $broad->whereJsonDoesntContain('care_types', 'Tagespflege');
                }
            });
        });
    }

    public function links(City $city, ?Facility $facility = null): array
    {
        $links = [];
        foreach (config('care_pages.pilots.'.$city->slug, []) as $slug) {
            $category = $this->category($city, $slug);
            if ($category === null) {
                continue;
            }
            $query = $this->facilities($city, $slug);
            if ($facility !== null) {
                $query->whereKey($facility->id);
            }
            if ($query->exists()) {
                $links[] = [...$category, 'url' => route('cities.care.show', [$city, $slug])];
            }
        }

        return $links;
    }

    public function sitemapPages(): array
    {
        $pages = [];
        $cities = City::query()->where('state_slug', 'brandenburg')
            ->whereIn('slug', array_keys(config('care_pages.pilots', [])))->get();
        foreach ($cities as $city) {
            foreach ($this->links($city) as $link) {
                $pages[] = ['loc' => $link['url'], 'changefreq' => 'weekly', 'priority' => '0.8'];
            }
        }

        return $pages;
    }
}

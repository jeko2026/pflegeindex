<?php

namespace App\Services;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
                            $this->whereCareTypeContains($specific, $type);
                        }
                        foreach ($category['name_markers'] as $marker) {
                            $specific->orWhere('name', 'like', '%'.$marker.'%');
                        }
                    });

                if ($slug === 'pflegeheime') {
                    foreach (['tagespflege', 'tages- und nachtpflege', 'nachtpflege', 'kurzzeitpflege', 'wohngruppe', 'wohngemeinschaft', 'betreutes wohnen'] as $excluded) {
                        $broad->where('name', 'not like', '%'.$excluded.'%');
                    }
                    $this->whereCareTypeDoesNotContain($broad, 'Tagespflege');
                }
            });
        });
    }

    /**
     * care_types is stored as a JSON array in a TEXT column. This quoted match
     * avoids SQLite JSON1 so legacy production SQLite installations remain supported.
     */
    private function whereCareTypeContains(Builder $query, string $careType): void
    {
        $query->orWhere('care_types', 'like', '%'.$this->jsonString($careType).'%');
    }

    /**
     * A missing care_types value does not contain the excluded care type.
     */
    private function whereCareTypeDoesNotContain(Builder $query, string $careType): void
    {
        $query->where(function (Builder $condition) use ($careType): void {
            $condition->whereNull('care_types')
                ->orWhere('care_types', 'not like', '%'.$this->jsonString($careType).'%');
        });
    }

    private function jsonString(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
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

    private function sitemapLastModified(City $city, string $slug): ?Carbon
    {
        $facilityLastModified = $this->facilities($city, $slug)->max('updated_at');
        $lastModified = collect([$city->updated_at, $facilityLastModified])
            ->filter()
            ->map(fn ($timestamp) => Carbon::parse($timestamp))
            ->max();

        return $lastModified instanceof Carbon ? $lastModified : null;
    }

    public function sitemapPages(): array
    {
        $pages = [];
        $cities = City::query()->where('state_slug', 'brandenburg')
            ->whereIn('slug', array_keys(config('care_pages.pilots', [])))->get();
        foreach ($cities as $city) {
            foreach ($this->links($city) as $link) {
                $pages[] = [
                    'loc' => $link['url'],
                    'lastmod' => $this->sitemapLastModified($city, $link['slug']),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            }
        }

        return $pages;
    }
}

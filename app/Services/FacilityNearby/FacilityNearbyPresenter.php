<?php

namespace App\Services\FacilityNearby;

use App\Models\Facility;

class FacilityNearbyPresenter
{
    private const ORDER = ['pharmacy', 'doctor', 'bus_stop', 'railway_station', 'supermarket'];

    private const LABELS = [
        'pharmacy' => 'Apotheke',
        'doctor' => 'Arztpraxis',
        'bus_stop' => 'Bushaltestelle',
        'railway_station' => 'Bahnhof',
        'supermarket' => 'Supermarkt',
    ];

    public function for(Facility $facility): array
    {
        $facility->loadMissing('city');
        $facilityLocality = $this->normalize($facility->city?->name);

        return $facility->nearbyPlaces()->get()->whereIn('category', self::ORDER)->sortBy(fn ($place) => array_search($place->category, self::ORDER, true))->map(fn ($place) => [
            'label' => self::LABELS[$place->category],
            'name' => $this->displayName($place->name, $place->locality, $facilityLocality),
            'distance' => $place->distance_meters >= 1000 ? number_format($place->distance_meters / 1000, 1, ',', '').' km' : $place->distance_meters.' m',
        ])->values()->all();
    }

    private function displayName(string $name, ?string $locality, string $facilityLocality): string
    {
        if (blank($locality) || $this->normalize($locality) === $facilityLocality) {
            return $name;
        }

        return $name.' — '.$locality;
    }

    private function normalize(?string $value): string
    {
        return strtolower(trim((string) $value));
    }
}

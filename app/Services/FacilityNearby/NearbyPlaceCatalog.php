<?php

namespace App\Services\FacilityNearby;

final class NearbyPlaceCatalog
{
    public const RADII = [
        'hospital' => 10000,
        'pharmacy' => 5000,
        'doctor' => 5000,
        'bus_stop' => 2000,
        'railway_station' => 10000,
        'supermarket' => 5000,
    ];

    public const LABELS = [
        'hospital' => 'Krankenhaus',
        'pharmacy' => 'Apotheke',
        'doctor' => 'Arztpraxis',
        'bus_stop' => 'Bushaltestelle',
        'railway_station' => 'Bahnhof',
        'supermarket' => 'Supermarkt',
    ];

    public static function categories(array $tags): array
    {
        if (self::isInactive($tags) || mb_strlen(trim((string) ($tags['name'] ?? ''))) < 3) {
            return [];
        }

        $categories = [];
        if (($tags['amenity'] ?? null) === 'hospital' || ($tags['healthcare'] ?? null) === 'hospital') {
            $categories[] = 'hospital';
        }
        if (($tags['amenity'] ?? null) === 'pharmacy' || ($tags['healthcare'] ?? null) === 'pharmacy') {
            $categories[] = 'pharmacy';
        }
        if (($tags['amenity'] ?? null) === 'doctors' || ($tags['healthcare'] ?? null) === 'doctor') {
            $categories[] = 'doctor';
        }
        if (($tags['highway'] ?? null) === 'bus_stop') {
            $categories[] = 'bus_stop';
        }
        if (in_array($tags['railway'] ?? null, ['station', 'halt'], true)) {
            $categories[] = 'railway_station';
        }
        if (($tags['shop'] ?? null) === 'supermarket') {
            $categories[] = 'supermarket';
        }

        return $categories;
    }

    private static function isInactive(array $tags): bool
    {
        $lifecycle = ['disused', 'abandoned', 'demolished', 'construction', 'proposed'];
        foreach ($lifecycle as $key) {
            if (array_key_exists($key, $tags) && ! in_array(strtolower((string) $tags[$key]), ['', '0', 'false', 'no'], true)) {
                return true;
            }
        }

        foreach (['amenity', 'healthcare', 'shop', 'railway', 'highway'] as $key) {
            foreach ($lifecycle as $prefix) {
                if (str_starts_with((string) ($tags[$key] ?? ''), $prefix.':')) {
                    return true;
                }
            }
        }

        return false;
    }
}

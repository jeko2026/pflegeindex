<?php

namespace App\Services;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Collection;

class FacilityTitleDiscriminator
{
    public function for(Facility $facility): ?string
    {
        $sameNameFacilities = Facility::query()
            ->select(['id', 'address', 'postal_code', 'type', 'slug'])
            ->where('city_id', $facility->city_id)
            ->where('name', $facility->name)
            ->orderBy('id')
            ->get();

        if ($sameNameFacilities->count() < 2) {
            return null;
        }

        $candidates = [
            static fn (Facility $item): ?string => filled($item->address) ? trim((string) $item->address) : null,
            static fn (Facility $item): ?string => filled($item->address) && filled($item->type) ? trim((string) $item->address).' · '.trim((string) $item->type) : null,
            static fn (Facility $item): ?string => filled($item->postal_code) ? trim((string) $item->postal_code) : null,
            static fn (Facility $item): ?string => filled($item->postal_code) && filled($item->type) ? trim((string) $item->postal_code).' · '.trim((string) $item->type) : null,
            static fn (Facility $item): ?string => filled($item->type) ? trim((string) $item->type) : null,
            static fn (Facility $item): ?string => filled($item->slug) ? trim((string) $item->slug) : null,
        ];

        foreach ($candidates as $candidate) {
            $value = $candidate($facility);
            if ($value !== null && $this->isUnique($sameNameFacilities, $candidate, $value)) {
                return $value;
            }
        }

        return null;
    }

    private function isUnique(Collection $facilities, callable $candidate, string $value): bool
    {
        $normalize = static fn (string $text): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text));
        $normalizedValue = $normalize($value);

        return $facilities->filter(static function (Facility $facility) use ($candidate, $normalizedValue, $normalize): bool {
            $otherValue = $candidate($facility);

            return $otherValue !== null && $normalize($otherValue) === $normalizedValue;
        })->count() === 1;
    }
}

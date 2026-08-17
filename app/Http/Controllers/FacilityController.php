<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Facility;
use App\Services\QualityScoreService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function show(City $city, Facility $facility, QualityScoreService $qualityScoreService): View
    {
        $relatedFacilities = Facility::query()
            ->with('city')
            ->where('city_id', $facility->city_id)
            ->where('id', '!=', $facility->id)
            ->orderBy('type')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(3)
            ->get();

        return view('facilities.show', [
            'city' => $city,
            'facility' => $facility,
            'relatedFacilities' => $relatedFacilities,
            'qualityScore' => $qualityScoreService->evaluate($facility),
            'titleDiscriminator' => $this->titleDiscriminator($facility),
        ]);
    }

    private function titleDiscriminator(Facility $facility): ?string
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
            static fn (Facility $item): ?string => filled($item->address)
                ? trim((string) $item->address)
                : null,
            static fn (Facility $item): ?string => filled($item->address) && filled($item->type)
                ? trim((string) $item->address).' · '.trim((string) $item->type)
                : null,
            static fn (Facility $item): ?string => filled($item->postal_code)
                ? trim((string) $item->postal_code)
                : null,
            static fn (Facility $item): ?string => filled($item->postal_code) && filled($item->type)
                ? trim((string) $item->postal_code).' · '.trim((string) $item->type)
                : null,
            static fn (Facility $item): ?string => filled($item->type)
                ? trim((string) $item->type)
                : null,
            static fn (Facility $item): ?string => filled($item->slug)
                ? trim((string) $item->slug)
                : null,
        ];

        foreach ($candidates as $candidate) {
            $value = $candidate($facility);

            if ($value !== null && $this->candidateIsUnique($sameNameFacilities, $candidate, $value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Facility>  $facilities
     * @param  callable(Facility): ?string  $candidate
     */
    private function candidateIsUnique(Collection $facilities, callable $candidate, string $value): bool
    {
        $normalizedValue = mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));

        return $facilities->filter(function (Facility $facility) use ($candidate, $normalizedValue): bool {
            $otherValue = $candidate($facility);

            if ($otherValue === null) {
                return false;
            }

            $normalizedOtherValue = mb_strtolower(
                preg_replace('/\s+/u', ' ', trim($otherValue)) ?? trim($otherValue),
            );

            return $normalizedOtherValue === $normalizedValue;
        })->count() === 1;
    }
}

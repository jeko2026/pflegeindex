<?php

namespace App\Projects\PflegeIndex\Trust;

use App\Models\City;
use App\Models\Facility;
use App\Support\HttpUrl;

final readonly class FacilityDataQuality
{
    /** @param list<array{key: string, label: string, weight: int, fulfilled: bool}> $criteria */
    private function __construct(
        public int $score,
        public int $fulfilledCount,
        public int $totalCount,
        public array $criteria,
    ) {}

    public static function evaluate(
        Facility $facility,
        City $city,
        string $canonicalUrl,
        ?string $website,
        bool $hasDescription,
    ): self {
        $phoneIsValid = self::phoneIsPlausible($facility->phone);
        $websiteIsValid = HttpUrl::isValid($website);
        $emailIsValid = is_string($facility->email)
            && filter_var(trim($facility->email), FILTER_VALIDATE_EMAIL) !== false;
        $addressIsComplete = self::isFilled($facility->address)
            && preg_match('/^\d{5}$/', trim((string) $facility->postal_code)) === 1
            && self::isFilled($city->name);
        $coordinatesAreValid = self::coordinatesAreValid($facility);
        $hasOfficialData = self::isFilled($facility->source_id);
        $canonicalIsValid = HttpUrl::isValid($canonicalUrl)
            && self::isFilled($facility->slug)
            && self::isFilled($city->slug);
        $hasDocumentedReview = self::hasDocumentedReview($facility);
        $hasNoObviousErrors = self::hasNoObviousErrors(
            $facility,
            $phoneIsValid,
            $emailIsValid,
            $websiteIsValid,
        );

        $criteria = [
            ['key' => 'phone', 'label' => 'Telefon vorhanden', 'weight' => 20, 'fulfilled' => $phoneIsValid],
            ['key' => 'website', 'label' => 'Website vorhanden', 'weight' => 15, 'fulfilled' => $websiteIsValid],
            ['key' => 'email', 'label' => 'E-Mail vorhanden', 'weight' => 10, 'fulfilled' => $emailIsValid],
            ['key' => 'address', 'label' => 'Vollständige Adresse', 'weight' => 15, 'fulfilled' => $addressIsComplete],
            ['key' => 'description', 'label' => 'Beschreibung vorhanden', 'weight' => 15, 'fulfilled' => $hasDescription],
            ['key' => 'coordinates', 'label' => 'Koordinaten vorhanden', 'weight' => 10, 'fulfilled' => $coordinatesAreValid],
            ['key' => 'official', 'label' => 'Amtliche Grunddaten', 'weight' => 10, 'fulfilled' => $hasOfficialData],
            ['key' => 'canonical', 'label' => 'Canonical URL', 'weight' => 5, 'fulfilled' => $canonicalIsValid],
            ['key' => 'review', 'label' => 'Prüfung dokumentiert', 'weight' => 5, 'fulfilled' => $hasDocumentedReview],
            ['key' => 'errors', 'label' => 'Keine offensichtlichen Datenfehler', 'weight' => 5, 'fulfilled' => $hasNoObviousErrors],
        ];

        $maximum = array_sum(array_column($criteria, 'weight'));
        $earned = array_sum(array_column(array_filter(
            $criteria,
            static fn (array $criterion): bool => $criterion['fulfilled'],
        ), 'weight'));

        return new self(
            score: (int) round(($earned / $maximum) * 100),
            fulfilledCount: count(array_filter(
                $criteria,
                static fn (array $criterion): bool => $criterion['fulfilled'],
            )),
            totalCount: count($criteria),
            criteria: $criteria,
        );
    }

    /** @return list<array{key: string, label: string, weight: int, fulfilled: bool}> */
    public function fulfilledCriteria(): array
    {
        return array_values(array_filter(
            $this->criteria,
            static fn (array $criterion): bool => $criterion['fulfilled'],
        ));
    }

    /** @return list<array{key: string, label: string}> */
    public function badges(): array
    {
        $fulfilled = array_column($this->fulfilledCriteria(), null, 'key');
        $badges = [];

        if (isset($fulfilled['official'])) {
            $badges[] = ['key' => 'official', 'label' => 'Amtliche Daten'];
        }

        if (isset($fulfilled['phone']) || isset($fulfilled['email'])) {
            $badges[] = ['key' => 'contact', 'label' => 'Kontaktdaten'];
        }

        if (isset($fulfilled['description'])) {
            $badges[] = ['key' => 'description', 'label' => 'Beschreibung'];
        }

        if (isset($fulfilled['address'])) {
            $badges[] = ['key' => 'location', 'label' => 'Standort'];
        }

        if (isset($fulfilled['website'])) {
            $badges[] = ['key' => 'website', 'label' => 'Website'];
        }

        return $badges;
    }

    private static function isFilled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private static function phoneIsPlausible(mixed $phone): bool
    {
        if (! self::isFilled($phone)) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);

        return is_string($digits) && strlen($digits) >= 6;
    }

    private static function coordinatesAreValid(Facility $facility): bool
    {
        $latitude = $facility->getAttribute('latitude') ?? $facility->getAttribute('lat');
        $longitude = $facility->getAttribute('longitude') ?? $facility->getAttribute('lng');

        return is_numeric($latitude)
            && is_numeric($longitude)
            && (float) $latitude >= -90
            && (float) $latitude <= 90
            && (float) $longitude >= -180
            && (float) $longitude <= 180;
    }

    private static function hasDocumentedReview(Facility $facility): bool
    {
        $contactWasReviewed = $facility->contact_status === 'verified'
            && $facility->contact_checked_at !== null
            && self::isFilled($facility->contact_source);
        $descriptionWasReviewed = $facility->description_checked_at !== null
            && is_array($facility->description_sources)
            && $facility->description_sources !== [];

        return $contactWasReviewed || $descriptionWasReviewed;
    }

    private static function hasNoObviousErrors(
        Facility $facility,
        bool $phoneIsValid,
        bool $emailIsValid,
        bool $websiteIsValid,
    ): bool {
        if (! self::isFilled($facility->name)
            || ! self::isFilled($facility->type)
            || ! self::isFilled($facility->source_id)
            || ! self::isFilled($facility->address)
            || preg_match('/^\d{5}$/', trim((string) $facility->postal_code)) !== 1) {
            return false;
        }

        return (! self::isFilled($facility->phone) || $phoneIsValid)
            && (! self::isFilled($facility->email) || $emailIsValid)
            && (! self::isFilled($facility->website) || $websiteIsValid);
    }
}

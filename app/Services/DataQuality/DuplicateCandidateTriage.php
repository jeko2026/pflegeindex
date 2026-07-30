<?php

namespace App\Services\DataQuality;

use App\Models\Facility;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DuplicateCandidateTriage
{
    public const CLASSIFICATIONS = [
        'exact_duplicate',
        'strong_duplicate_candidate',
        'possible_duplicate',
        'shared_network_website',
        'shared_network_email',
        'shared_central_phone',
        'same_address_different_service',
        'same_operator_different_location',
        'manual_review',
    ];

    /**
     * @param  Collection<int, Facility>  $facilities
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    public function classifyGroups(Collection $facilities, array $groups): array
    {
        $byId = $facilities->keyBy('id');

        return collect($groups)->map(function (array $group) use ($byId): array {
            $ids = collect(explode('|', (string) ($group['facility_ids'] ?? '')))
                ->filter(fn (string $id): bool => ctype_digit($id))
                ->map(fn (string $id): int => (int) $id)
                ->unique()
                ->values();
            $members = $ids->map(fn (int $id) => $byId->get($id))->filter()->values();
            $classification = $this->classify($members);

            return $group + [
                'classification' => $classification,
                'triage_priority' => in_array($classification, ['exact_duplicate', 'strong_duplicate_candidate'], true) ? 'high' : (in_array($classification, ['possible_duplicate', 'manual_review'], true) ? 'medium' : 'low'),
                'triage_reason' => $this->reason($classification),
            ];
        })->all();
    }

    /** @param  Collection<int, Facility>  $members */
    public function classify(Collection $members): string
    {
        if ($members->count() < 2) {
            return 'manual_review';
        }

        $sameName = $this->oneValue($members, fn (Facility $f): ?string => $this->textKey($f->name));
        $sameCity = $this->oneValue($members, fn (Facility $f): ?string => $this->textKey($f->city?->name));
        $sameAddress = $this->oneValue($members, fn (Facility $f): ?string => $this->textKey($f->address));
        $sameType = $this->oneValue($members, fn (Facility $f): ?string => $this->textKey($f->type));
        $samePhone = $this->oneValue($members, fn (Facility $f): ?string => $this->phoneKey($f->phone));
        $sameEmail = $this->oneValue($members, fn (Facility $f): ?string => $this->textKey($f->email));
        $sameWebsite = $this->oneValue($members, fn (Facility $f): ?string => $this->websiteKey($f->website));
        $specificWebsite = $sameWebsite && $this->hasSpecificWebsitePath($members->first()?->website);
        $differentService = $members->map(fn (Facility $f): ?string => $this->serviceKind($f->name))->filter()->unique()->count() > 1;

        if ($sameName && $sameCity && $sameAddress && $sameType) {
            return 'exact_duplicate';
        }

        if ($sameAddress && (! $sameType || $differentService)) {
            return 'same_address_different_service';
        }

        if ($sameWebsite && ! $specificWebsite && ! $sameName && ! $sameAddress) {
            return 'shared_network_website';
        }

        $sameOperator = $this->oneValue($members, fn (Facility $f): ?string => $this->operatorKey($f->name));
        if ($sameOperator && (! $sameCity || ! $sameAddress)) {
            return 'same_operator_different_location';
        }

        $allMainContacts = $sameName && $sameCity && $sameType && $samePhone && $sameEmail && $specificWebsite;
        if ($allMainContacts) {
            return 'exact_duplicate';
        }

        if ($sameEmail && ! $sameName && ! $sameAddress) {
            return 'shared_network_email';
        }

        if ($samePhone && ! $sameName && ! $sameAddress) {
            return 'shared_central_phone';
        }

        $significantMatches = collect([$sameName, $sameAddress, $samePhone, $sameEmail, $specificWebsite, $sameType])->filter()->count();
        if ($significantMatches >= 2 && ($sameName || $sameAddress)) {
            return 'strong_duplicate_candidate';
        }

        if ($sameWebsite) {
            return 'shared_network_website';
        }
        if ($sameEmail) {
            return 'shared_network_email';
        }
        if ($samePhone) {
            return 'shared_central_phone';
        }
        if ($sameAddress) {
            return 'same_address_different_service';
        }

        return $sameName ? 'possible_duplicate' : 'manual_review';
    }

    private function reason(string $classification): string
    {
        return match ($classification) {
            'exact_duplicate' => 'Name, Ort, Adresse und Typ oder mehrere wesentliche Kontaktfelder stimmen überein.',
            'strong_duplicate_candidate' => 'Mindestens zwei wesentliche Merkmale einschließlich Name oder Adresse stimmen überein.',
            'possible_duplicate' => 'Es gibt Ähnlichkeiten, aber nicht genügend Belege für einen starken Kandidaten.',
            'shared_network_website' => 'Mehrere Einrichtungen verwenden dieselbe zentrale Netzwerk-Website.',
            'shared_network_email' => 'Mehrere Einrichtungen verwenden dieselbe zentrale E-Mail-Adresse.',
            'shared_central_phone' => 'Mehrere Einrichtungen verwenden dieselbe zentrale Telefonnummer.',
            'same_address_different_service' => 'Am selben Standort werden unterschiedliche Dienste oder Einrichtungsarten geführt.',
            'same_operator_different_location' => 'Derselbe erkennbare Betreiber führt unterschiedliche Standorte.',
            default => 'Die vorhandenen lokalen Daten reichen für eine automatische Einordnung nicht aus.',
        };
    }

    /** @param  Collection<int, Facility>  $members */
    private function oneValue(Collection $members, callable $keyer): bool
    {
        $values = $members->map($keyer)->filter(fn (?string $value): bool => filled($value))->unique();

        return $values->count() === 1 && $members->every(fn (Facility $facility): bool => filled($keyer($facility)));
    }

    private function textKey(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower(trim($value)))));
    }

    private function phoneKey(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value);
        if (str_starts_with($digits, '0049')) {
            $digits = '49'.substr($digits, 4);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '49'.substr($digits, 1);
        }

        return strlen($digits) >= 6 ? $digits : null;
    }

    private function websiteKey(?string $value): ?string
    {
        if (blank($value) || filter_var(trim($value), FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $host = preg_replace('/^www\./i', '', strtolower((string) parse_url(trim($value), PHP_URL_HOST)));
        $path = rtrim((string) parse_url(trim($value), PHP_URL_PATH), '/');

        return $host.$path;
    }

    private function hasSpecificWebsitePath(?string $value): bool
    {
        if (blank($value)) {
            return false;
        }

        return trim((string) parse_url(trim($value), PHP_URL_PATH), '/') !== '';
    }

    private function operatorKey(?string $name): ?string
    {
        if (blank($name)) {
            return null;
        }
        $normalized = $this->textKey($name);
        foreach (['awo', 'drk', 'caritas', 'stephanus', 'diakonie', 'volkssolidaritat', 'asb', 'johanniter', 'malteser'] as $operator) {
            if (preg_match('/\b'.preg_quote($operator, '/').'\b/', (string) $normalized)) {
                return $operator;
            }
        }

        return null;
    }

    private function serviceKind(?string $name): ?string
    {
        $normalized = $this->textKey($name);
        if ($normalized === null) {
            return null;
        }
        $patterns = [
            'ambulant' => '/(?:ambulant|sozialstation|hauskrankenpflege|pflegedienst)/',
            'tagespflege' => '/(?:tagespflege|tagesstatte|tagesbetreuung)/',
            'stationaer' => '/(?:seniorenheim|pflegeheim|seniorenzentrum|wohnpark|pflegezentrum)/',
            'krankenhaus' => '/(?:krankenhaus|klinik)/',
        ];
        foreach ($patterns as $kind => $pattern) {
            if (preg_match($pattern, $normalized)) {
                return $kind;
            }
        }

        return null;
    }
}

<?php

namespace App\Services\DataQuality;

use App\Models\Facility;
use App\Models\City;

final class FacilityDataAuditor
{
    /**
     * Audit email of a single facility.
     * Returns: null (if OK), 'missing', 'invalid', 'manual_review'
     */
    public static function auditEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return 'missing';
        }

        $emailTrimmed = trim($email);

        if (str_contains($emailTrimmed, '￼') || stripos($emailTrimmed, 'geschlossen') !== false) {
            return 'manual_review';
        }

        $placeholders = [
            'info@email.com',
            'info@example.com',
            'test@example.com',
            'example@example.com',
            'test@test.com',
        ];

        if (in_array(strtolower($emailTrimmed), $placeholders, true)) {
            return 'manual_review';
        }

        $domain = strrchr($emailTrimmed, '@');
        if ($domain !== false) {
            $domainLower = strtolower(substr($domain, 1));
            if (in_array($domainLower, ['example.com', 'example.org', 'example.net'], true)) {
                return 'manual_review';
            }
        }

        if (filter_var($emailTrimmed, FILTER_VALIDATE_EMAIL) === false) {
            return 'invalid';
        }

        return null;
    }

    /**
     * Audit website of a single facility.
     * Returns: null (if OK), 'missing', 'invalid', 'suspicious'
     */
    public static function auditWebsite(?string $website): ?string
    {
        if ($website === null || trim($website) === '') {
            return 'missing';
        }

        $webTrimmed = trim($website);

        // Check if absolute URL with http or https
        if (filter_var($webTrimmed, FILTER_VALIDATE_URL) === false || !preg_match('/^https?:\/\//i', $webTrimmed)) {
            return 'invalid';
        }

        if (str_starts_with(strtolower($webTrimmed), 'http://')) {
            return 'suspicious'; // HTTP protocol is suspicious but NOT invalid
        }

        return null;
    }

    /**
     * Audit phone of a single facility.
     * Returns: null (if OK), 'missing', 'suspicious'
     */
    public static function auditPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return 'missing';
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (preg_match('/[a-zA-Z]/', $phone)) {
            return 'suspicious'; // has letters
        }

        if (strlen($digits) < 6) {
            return 'suspicious'; // too short
        }

        // Entire number consists of one digit repeated
        if (preg_match('/^(\d)\1+$/', $digits)) {
            return 'suspicious';
        }

        // Has 8+ consecutive identical digits
        if (preg_match('/(\d)\1{7,}/', $digits)) {
            return 'suspicious';
        }

        return null;
    }

    /**
     * Run full database duplicate candidate check.
     * Returns array grouped by type.
     */
    public static function detectDuplicateCandidates(): array
    {
        $candidates = [
            'same_name_same_city' => [],
            'same_address_same_city' => [],
            'shared_phone' => [],
            'shared_website' => [],
        ];

        // 1. Same Name and City
        $names = Facility::select('name', 'city_id')
            ->groupBy('name', 'city_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($names as $row) {
            $ids = Facility::where('name', $row->name)
                ->where('city_id', $row->city_id)
                ->pluck('id')
                ->all();
            
            $cityName = City::where('id', $row->city_id)->value('name') ?? 'Unknown';
            $candidates['same_name_same_city'][] = [
                'name' => $row->name,
                'city' => $cityName,
                'ids' => $ids,
            ];
        }

        // 2. Same Address and City
        $addresses = Facility::select('address', 'city_id')
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->groupBy('address', 'city_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($addresses as $row) {
            $ids = Facility::where('address', $row->address)
                ->where('city_id', $row->city_id)
                ->pluck('id')
                ->all();

            $cityName = City::where('id', $row->city_id)->value('name') ?? 'Unknown';
            $candidates['same_address_same_city'][] = [
                'address' => $row->address,
                'city' => $cityName,
                'ids' => $ids,
            ];
        }

        // 3. Shared Phone
        $phones = Facility::select('phone')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($phones as $row) {
            $facilities = Facility::where('phone', $row->phone)
                ->select('id', 'name', 'city_id')
                ->get();
            
            $items = [];
            foreach ($facilities as $f) {
                $cityName = City::where('id', $f->city_id)->value('name') ?? 'Unknown';
                $items[] = [
                    'id' => $f->id,
                    'name' => $f->name,
                    'city' => $cityName,
                ];
            }

            $candidates['shared_phone'][] = [
                'phone' => $row->phone,
                'facilities' => $items,
            ];
        }

        // 4. Shared Website
        $websites = Facility::select('website')
            ->whereNotNull('website')
            ->where('website', '!=', '')
            ->groupBy('website')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($websites as $row) {
            $facilities = Facility::where('website', $row->website)
                ->select('id', 'name', 'city_id')
                ->get();

            $items = [];
            foreach ($facilities as $f) {
                $cityName = City::where('id', $f->city_id)->value('name') ?? 'Unknown';
                $items[] = [
                    'id' => $f->id,
                    'name' => $f->name,
                    'city' => $cityName,
                ];
            }

            $candidates['shared_website'][] = [
                'website' => $row->website,
                'facilities' => $items,
            ];
        }

        return $candidates;
    }
}

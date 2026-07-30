<?php

namespace App\Services\DataQuality;

use App\Models\City;
use App\Models\Facility;
use App\Models\GeoDistrict;
use App\Models\GeoMunicipality;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FullDataAudit
{
    private const PRIORITIES = ['critical', 'high', 'medium', 'low'];

    private const ALLOWED_TYPES = ['Ambulante Pflege', 'Stationäre/teilstationäre Pflege', 'Krankenhaus'];

    private const CONTACT_STATUSES = ['verified', 'pending', 'not_found'];

    private const MAJOR_CITIES = [
        'potsdam', 'cottbus', 'brandenburg-an-der-havel', 'frankfurt-oder',
        'eberswalde', 'oranienburg', 'neuruppin',
    ];

    /** @var array<string, array<string, mixed>> */
    private array $issues = [];

    /** @var array<int, array<string, mixed>> */
    private array $duplicates = [];

    /** @var array<int, array<string, mixed>> */
    private array $geography = [];

    /**
     * @return array{summary: array<string, mixed>, issues: array<int, array<string, mixed>>, facilities: array<int, array<string, mixed>>, duplicates: array<int, array<string, mixed>>, geography: array<int, array<string, mixed>>, verification_queue: array<int, array<string, mixed>>}
     */
    public function run(?string $citySlug = null, ?string $priority = null): array
    {
        $this->issues = [];
        $this->duplicates = [];
        $this->geography = [];

        $facilities = Facility::query()
            ->with(['city.geoMunicipality.district.state.country'])
            ->when($citySlug, fn ($query) => $query->whereHas('city', fn ($city) => $city->where('slug', $citySlug)))
            ->orderBy('id')
            ->get();

        $this->auditDatabaseIntegrity();
        $this->auditFacilities($facilities);
        $this->auditDuplicateGroups($facilities);
        $this->auditGeography($facilities, $citySlug);

        $issues = collect(array_values($this->issues));
        if ($priority !== null) {
            $issues = $issues->where('priority', $priority)->values();
        }

        $facilityRows = $this->facilityRows($facilities, $issues);
        $queue = $this->verificationQueue($facilities, $issues);

        return [
            'summary' => $this->summary($facilities, $issues),
            'issues' => $issues->all(),
            'facilities' => $facilityRows,
            'duplicates' => $this->duplicates,
            'geography' => $this->geography,
            'verification_queue' => $queue,
        ];
    }

    public static function validPriority(?string $priority): bool
    {
        return $priority === null || in_array($priority, self::PRIORITIES, true);
    }

    /** @param Collection<int, Facility> $facilities */
    private function auditFacilities(Collection $facilities): void
    {
        $slugCounts = $facilities->groupBy(fn (Facility $f): string => $f->city_id.'|'.$f->slug);

        foreach ($facilities as $facility) {
            $city = $facility->city;
            $name = trim((string) $facility->name);

            if ($name === '') {
                $this->add($facility, 'BASIC_NAME_MISSING', 'name', $facility->name, null, 'Name fehlt.', 'basic', 'high', 'high', 'Offiziellen Namen recherchieren.');
            } elseif (mb_strlen($name) < 3) {
                $this->add($facility, 'BASIC_NAME_TOO_SHORT', 'name', $facility->name, null, 'Name ist ungewöhnlich kurz.', 'basic', 'medium', 'medium', 'Name manuell mit der Quelle abgleichen.');
            } elseif (mb_strlen($name) > 180) {
                $this->add($facility, 'BASIC_NAME_TOO_LONG', 'name', $facility->name, null, 'Name ist ungewöhnlich lang.', 'basic', 'medium', 'medium', 'Name manuell mit der Quelle abgleichen.');
            }
            $this->auditText($facility, 'name', $facility->name, 'BASIC_NAME');

            if (blank($facility->type)) {
                $this->add($facility, 'BASIC_TYPE_MISSING', 'type', $facility->type, null, 'Einrichtungsart fehlt.', 'basic', 'high', 'high', 'Einrichtungsart aus offizieller Quelle ergänzen.');
            } elseif (! in_array($facility->type, self::ALLOWED_TYPES, true)) {
                $this->add($facility, 'BASIC_TYPE_UNKNOWN', 'type', $facility->type, null, 'Einrichtungsart ist nicht im aktuellen Typenkatalog.', 'basic', 'high', 'high', 'Typ manuell dem bestehenden Katalog zuordnen.');
            }

            if (blank($facility->slug)) {
                $this->add($facility, 'SEO_SLUG_MISSING', 'slug', $facility->slug, null, 'Öffentlicher Slug fehlt.', 'seo', 'critical', 'high', 'Slug und Weiterleitungsbedarf manuell prüfen.');
            } elseif (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $facility->slug)) {
                $this->add($facility, 'SEO_SLUG_INVALID', 'slug', $facility->slug, null, 'Slug hat ein ungültiges Format.', 'seo', 'high', 'high', 'Slug und permanente Weiterleitung manuell planen.');
            } elseif (! str_starts_with($facility->slug, Str::slug($name))) {
                $this->add($facility, 'SEO_SLUG_NAME_MISMATCH', 'slug', $facility->slug, Str::slug($name), 'Slug weicht vom aktuellen Namen ab.', 'seo', 'low', 'medium', 'Nur bei echtem Bedarf mit 301-Weiterleitung ändern.');
            }
            if (($slugCounts[$facility->city_id.'|'.$facility->slug] ?? collect())->count() > 1) {
                $this->add($facility, 'SEO_DUPLICATE_PRIMARY_SLUG', 'slug', $facility->slug, null, 'Öffentlicher Slug ist innerhalb der Stadt doppelt.', 'seo', 'critical', 'high', 'Öffentliche URLs und Weiterleitungen manuell bereinigen.');
            }

            if ($city === null) {
                $this->add($facility, 'GEO_ORPHAN_CITY', 'city_id', $facility->city_id, null, 'Zugehörige Stadt fehlt.', 'geography', 'critical', 'high', 'Stadtbeziehung aus offizieller Quelle wiederherstellen.');
            }

            $this->auditAddress($facility);
            $this->auditContacts($facility);
            $this->auditTrust($facility);
        }
    }

    private function auditAddress(Facility $facility): void
    {
        if (blank($facility->address)) {
            $this->add($facility, 'ADDRESS_MISSING', 'address', $facility->address, null, 'Adresse fehlt.', 'address', 'high', 'high', 'Adresse aus offizieller Quelle ergänzen.');
        } else {
            $this->auditText($facility, 'address', $facility->address, 'ADDRESS');
            $city = $facility->city?->name;
            if ($city && preg_match('/\b'.preg_quote($city, '/').'\b/ui', $facility->address)) {
                $this->add($facility, 'ADDRESS_REPEATS_CITY', 'address', $facility->address, null, 'Adresszeile enthält zusätzlich den Ort.', 'address', 'low', 'medium', 'Adressformat manuell prüfen; nicht automatisch ändern.');
            }
        }
        if (blank($facility->street)) {
            $this->add($facility, 'ADDRESS_STREET_MISSING', 'street', $facility->street, null, 'Straße fehlt als strukturiertes Feld.', 'address', 'medium', 'high', 'Straße aus der offiziellen Basisquelle prüfen.');
        }
        if (blank($facility->house_number)) {
            $this->add($facility, 'ADDRESS_HOUSE_NUMBER_MISSING', 'house_number', $facility->house_number, null, 'Hausnummer fehlt als strukturiertes Feld.', 'address', 'medium', 'high', 'Hausnummer aus der offiziellen Basisquelle prüfen.');
        }
        if (blank($facility->postal_code)) {
            $this->add($facility, 'ADDRESS_POSTAL_CODE_MISSING', 'postal_code', $facility->postal_code, null, 'Postleitzahl fehlt.', 'address', 'high', 'high', 'Postleitzahl aus offizieller Quelle ergänzen.');
        } elseif (! preg_match('/^\d{5}$/', $facility->postal_code)) {
            $this->add($facility, 'ADDRESS_POSTAL_CODE_INVALID', 'postal_code', $facility->postal_code, null, 'Postleitzahl besteht nicht aus fünf Ziffern.', 'address', 'high', 'high', 'Postleitzahl manuell verifizieren.');
        } elseif (! str_starts_with($facility->postal_code, '0') && ! str_starts_with($facility->postal_code, '1')) {
            $this->add($facility, 'ADDRESS_POSTAL_CODE_SUSPICIOUS', 'postal_code', $facility->postal_code, null, 'Postleitzahl ist für Brandenburg ungewöhnlich.', 'address', 'medium', 'medium', 'Ort und Postleitzahl manuell abgleichen.');
        }
    }

    private function auditContacts(Facility $facility): void
    {
        $phone = FacilityDataAuditor::auditPhone($facility->phone);
        if ($phone === 'missing') {
            $this->add($facility, 'PHONE_MISSING', 'phone', null, null, 'Telefonnummer fehlt.', 'phone', 'medium', 'high', 'Offizielle Einrichtungsseite prüfen.');
        } elseif ($phone !== null) {
            $this->add($facility, 'PHONE_INVALID', 'phone', $facility->phone, null, 'Telefonnummer hat ein verdächtiges Format.', 'phone', 'high', 'high', 'Telefonnummer manuell verifizieren.');
        } elseif ($facility->phone !== trim((string) $facility->phone)) {
            $this->add($facility, 'PHONE_FORMATTING', 'phone', $facility->phone, trim((string) $facility->phone), 'Telefonnummer enthält äußere Leerzeichen.', 'phone', 'low', 'high', 'Nach Verifizierung sicher normalisieren.');
        }
        if (filled($facility->phone) && preg_match('/(?:;|,|\/).*(?:\d{3,})/', $facility->phone)) {
            $this->add($facility, 'PHONE_MULTIPLE_VALUES', 'phone', $facility->phone, null, 'Möglicherweise mehrere Nummern in einem Feld.', 'phone', 'high', 'medium', 'Nummern und Verwendungszweck manuell prüfen.');
        }

        $email = FacilityDataAuditor::auditEmail($facility->email);
        if ($email === 'missing') {
            $this->add($facility, 'EMAIL_MISSING', 'email', null, null, 'E-Mail-Adresse fehlt.', 'email', 'medium', 'high', 'Offizielle Einrichtungsseite prüfen.');
        } elseif ($email === 'invalid') {
            $this->add($facility, 'EMAIL_INVALID', 'email', $facility->email, null, 'E-Mail-Adresse ist syntaktisch ungültig.', 'email', 'high', 'high', 'E-Mail-Adresse manuell verifizieren.');
        } elseif ($email === 'manual_review') {
            $this->add($facility, 'EMAIL_PLACEHOLDER_OR_CORRUPT', 'email', $facility->email, null, 'E-Mail-Adresse wirkt beschädigt oder wie ein Platzhalter.', 'email', 'high', 'high', 'Wert nicht automatisch korrigieren; Quelle manuell prüfen.');
        } elseif ($facility->email !== trim((string) $facility->email)) {
            $this->add($facility, 'EMAIL_WHITESPACE', 'email', $facility->email, trim((string) $facility->email), 'E-Mail-Adresse enthält äußere Leerzeichen.', 'email', 'low', 'high', 'Nach Verifizierung sicher normalisieren.');
        }
        if (filled($facility->email) && (str_contains(strtolower($facility->email), 'mailto:') || preg_match('/[,;]\s*\S+@/', $facility->email))) {
            $this->add($facility, 'EMAIL_MULTIPLE_OR_MAILTO', 'email', $facility->email, null, 'E-Mail-Feld enthält mailto oder mehrere Adressen.', 'email', 'high', 'high', 'Eine offizielle Adresse manuell auswählen.');
        }

        $website = FacilityDataAuditor::auditWebsite($facility->website);
        if ($website === 'missing') {
            $this->add($facility, 'WEBSITE_MISSING', 'website', null, null, 'Website fehlt.', 'website', 'medium', 'high', 'Spezifische offizielle Einrichtungsseite suchen.');
        } elseif ($website === 'invalid') {
            $this->add($facility, 'WEBSITE_INVALID', 'website', $facility->website, null, 'Website-URL ist ungültig oder nicht absolut.', 'website', 'high', 'high', 'Website manuell verifizieren.');
        } elseif ($website === 'suspicious') {
            $this->add($facility, 'WEBSITE_HTTP', 'website', $facility->website, preg_replace('/^http:/i', 'https:', $facility->website), 'Website verwendet HTTP.', 'website', 'low', 'medium', 'HTTPS-Verfügbarkeit manuell prüfen.');
        }
        if (filled($facility->website)) {
            $host = strtolower((string) parse_url($facility->website, PHP_URL_HOST));
            if (preg_match('/(google\.|bing\.|facebook\.|instagram\.|pflegeheim|branchenbuch|11880|gelbeseiten)/i', $host)) {
                $this->add($facility, 'WEBSITE_NON_OFFICIAL', 'website', $facility->website, null, 'URL wirkt wie Such-, Social- oder Aggregator-Seite.', 'website', 'medium', 'medium', 'Spezifische offizielle Einrichtungsseite suchen.');
            }
            if (parse_url($facility->website, PHP_URL_QUERY) || parse_url($facility->website, PHP_URL_FRAGMENT)) {
                $this->add($facility, 'WEBSITE_TRACKING_OR_FRAGMENT', 'website', $facility->website, null, 'URL enthält Query-Parameter oder Fragment.', 'website', 'low', 'medium', 'Nur nach manueller Prüfung kanonische URL übernehmen.');
            }
        }
    }

    private function auditTrust(Facility $facility): void
    {
        $status = $facility->contact_status;
        if ($status === null || trim($status) === '') {
            $this->add($facility, 'TRUST_STATUS_MISSING', 'contact_status', $status, null, 'Kontakt-Prüfstatus fehlt.', 'trust', 'medium', 'high', 'Datensatz in die manuelle Prüfung aufnehmen.');
        } elseif (! in_array($status, self::CONTACT_STATUSES, true)) {
            $this->add($facility, 'TRUST_STATUS_UNKNOWN', 'contact_status', $status, null, 'Kontakt-Prüfstatus ist unbekannt.', 'trust', 'high', 'high', 'Status anhand des bestehenden Workflows korrigieren.');
        }

        if ($status === 'verified') {
            if (blank($facility->contact_source)) {
                $this->add($facility, 'TRUST_VERIFIED_WITHOUT_SOURCE', 'contact_source', null, null, 'Geprüfter Kontakt hat keine Quelle.', 'trust', 'high', 'high', 'Exakte offizielle Quell-URL ergänzen.');
            }
            if ($facility->contact_checked_at === null) {
                $this->add($facility, 'TRUST_VERIFIED_WITHOUT_DATE', 'contact_checked_at', null, null, 'Geprüfter Kontakt hat kein Prüfdatum.', 'trust', 'high', 'high', 'Kontakte erneut prüfen und Datum dokumentieren.');
            }
            if (blank($facility->phone) && blank($facility->email) && blank($facility->website)) {
                $this->add($facility, 'TRUST_VERIFIED_WITHOUT_CONTACT', 'contact_status', $status, null, 'Status verified ohne Kontaktdaten.', 'trust', 'critical', 'high', 'Status und Kontakte manuell klären.');
            }
        }
        if ($status === 'not_found' && (filled($facility->phone) || filled($facility->email) || filled($facility->website))) {
            $this->add($facility, 'TRUST_NOT_FOUND_WITH_CONTACT', 'contact_status', $status, null, 'Status not_found trotz vorhandener Kontaktdaten.', 'trust', 'high', 'high', 'Status und Kontakte manuell klären.');
        }
        if (filled($facility->contact_source) && FacilityDataAuditor::auditWebsite($facility->contact_source) === 'invalid') {
            $this->add($facility, 'TRUST_SOURCE_URL_INVALID', 'contact_source', $facility->contact_source, null, 'Quell-URL ist ungültig.', 'trust', 'high', 'high', 'Exakte Quell-URL manuell ersetzen.');
        }
        if ($facility->contact_checked_at?->isFuture()) {
            $this->add($facility, 'TRUST_DATE_IN_FUTURE', 'contact_checked_at', $facility->contact_checked_at?->toIso8601String(), null, 'Prüfdatum liegt in der Zukunft.', 'trust', 'high', 'high', 'Prüfdatum manuell korrigieren.');
        } elseif ($facility->contact_checked_at?->lt(now()->subYear())) {
            $this->add($facility, 'TRUST_DATE_STALE', 'contact_checked_at', $facility->contact_checked_at?->toDateString(), null, 'Kontaktprüfung ist älter als ein Jahr.', 'trust', 'medium', 'high', 'Kontakte erneut verifizieren.');
        }
    }

    /** @param Collection<int, Facility> $facilities */
    private function auditDuplicateGroups(Collection $facilities): void
    {
        $rules = [
            'same_name_same_city' => fn (Facility $f): ?string => $this->key($f->name).'|'.$this->key($f->city?->name),
            'same_name_same_address' => fn (Facility $f): ?string => $this->key($f->name).'|'.$this->key($f->address),
            'shared_phone' => fn (Facility $f): ?string => $this->phoneKey($f->phone),
            'shared_email' => fn (Facility $f): ?string => $this->key($f->email),
            'shared_website' => fn (Facility $f): ?string => $this->websiteKey($f->website),
            'same_address_same_city' => fn (Facility $f): ?string => $this->key($f->address).'|'.$this->key($f->city?->name),
        ];

        $seen = [];
        foreach ($rules as $rule => $keyer) {
            $groups = $facilities->groupBy($keyer)->filter(fn (Collection $group, string $key): bool => $key !== '' && ! str_contains($key, '||') && $group->count() > 1);
            foreach ($groups as $key => $group) {
                $ids = $group->pluck('id')->sort()->values()->all();
                $identity = implode('-', $ids);
                $isStrong = in_array($rule, ['same_name_same_city', 'same_name_same_address'], true);
                $recommendation = $isStrong ? 'likely_duplicate' : ($rule === 'same_address_same_city' ? 'same_address_not_duplicate' : 'shared_contact_only');
                $this->duplicates[] = [
                    'group_id' => hash('sha256', $rule.'|'.$identity),
                    'match_type' => 'exact',
                    'rule' => $rule,
                    'facility_ids' => implode('|', $ids),
                    'facility_names' => $group->pluck('name')->implode(' | '),
                    'cities' => $group->map(fn (Facility $f) => $f->city?->name)->unique()->implode(' | '),
                    'matching_fields' => $rule,
                    'matching_value' => $key,
                    'confidence' => $isStrong ? 'high' : 'medium',
                    'recommendation' => $recommendation,
                ];
                foreach ($group as $facility) {
                    $issueCode = 'DUPLICATE_'.strtoupper($rule);
                    $this->add($facility, $issueCode, 'duplicate_candidate', $identity, null, 'Mögliche Dublettengruppe: '.$rule.'.', 'duplicate', $isStrong ? 'high' : 'medium', $isStrong ? 'high' : 'medium', 'Nicht zusammenführen; Gruppe manuell vergleichen.');
                }
                $seen[$identity] = true;
            }
        }

        // Fuzzy names are compared only inside an exact shared address/phone/website bucket.
        foreach (['address', 'phone', 'website'] as $field) {
            $keyer = match ($field) {
                'phone' => fn (Facility $f): ?string => $this->phoneKey($f->phone),
                'website' => fn (Facility $f): ?string => $this->websiteKey($f->website),
                default => fn (Facility $f): ?string => $this->key($f->address),
            };
            foreach ($facilities->groupBy($keyer)->filter(fn (Collection $g, string $key): bool => $key !== '' && $g->count() > 1) as $key => $group) {
                $items = $group->values();
                for ($i = 0; $i < $items->count(); $i++) {
                    for ($j = $i + 1; $j < $items->count(); $j++) {
                        $a = $items[$i];
                        $b = $items[$j];
                        $identity = min($a->id, $b->id).'-'.max($a->id, $b->id);
                        if (isset($seen[$identity]) || $this->similarity($a->name, $b->name) < 78) {
                            continue;
                        }
                        $this->duplicates[] = [
                            'group_id' => hash('sha256', 'fuzzy|'.$field.'|'.$identity),
                            'match_type' => 'fuzzy', 'rule' => 'similar_name_shared_'.$field,
                            'facility_ids' => $a->id.'|'.$b->id,
                            'facility_names' => $a->name.' | '.$b->name,
                            'cities' => collect([$a->city?->name, $b->city?->name])->unique()->implode(' | '),
                            'matching_fields' => 'name|'.$field, 'matching_value' => $key,
                            'confidence' => 'medium', 'recommendation' => 'possible_duplicate',
                        ];
                        foreach ([$a, $b] as $facility) {
                            $this->add($facility, 'DUPLICATE_FUZZY_'.$field, 'duplicate_candidate', $identity, null, 'Ähnlicher Name mit gemeinsamem '.$field.'.', 'duplicate', 'high', 'medium', 'Nicht zusammenführen; beide Quellen manuell vergleichen.');
                        }
                        $seen[$identity] = true;
                    }
                }
            }
        }
    }

    /** @param Collection<int, Facility> $facilities */
    private function auditGeography(Collection $facilities, ?string $citySlug): void
    {
        $cities = City::query()->with(['geoMunicipality.district.state.country'])->when($citySlug, fn ($q) => $q->where('slug', $citySlug))->orderBy('id')->get();
        foreach ($cities as $city) {
            $municipality = $city->geoMunicipality;
            $district = $municipality?->district;
            $state = $district?->state;
            $country = $state?->country;
            $codes = [];
            if ($municipality === null) {
                $codes[] = 'GEO_MUNICIPALITY_MISSING';
            }
            if ($district === null) {
                $codes[] = 'GEO_DISTRICT_MISSING';
            }
            if ($city->state !== 'Brandenburg' || $city->state_slug !== 'brandenburg') {
                $codes[] = 'GEO_STATE_INVALID';
            }
            if ($state && ($state->ags !== '12' || $state->slug !== 'brandenburg')) {
                $codes[] = 'GEO_CHAIN_STATE_MISMATCH';
            }
            if ($country && $country->iso2 !== 'DE') {
                $codes[] = 'GEO_CHAIN_COUNTRY_MISMATCH';
            }
            if ($city->geo_requires_manual_review || in_array($city->geo_match_status, [null, 'unmatched', 'ambiguous', 'partial', 'locality'], true)) {
                $codes[] = 'GEO_UNRESOLVED';
            }
            if (in_array($city->slug, ['hennickendorf', 'reichenberg'], true)) {
                $codes[] = 'GEO_NAMED_MANUAL_CHECK';
            }

            $this->geography[] = [
                'city_id' => $city->id, 'city' => $city->name, 'city_slug' => $city->slug,
                'facilities_count' => $city->facilities()->count(),
                'gemeinde_id' => $municipality?->id, 'gemeinde' => $municipality?->name,
                'landkreis_id' => $district?->id, 'landkreis' => $district?->display_name,
                'state' => $state?->name ?? $city->state, 'state_slug' => $state?->slug ?? $city->state_slug,
                'geo_match_status' => $city->geo_match_status, 'manual_review_required' => $city->geo_requires_manual_review ? 'yes' : 'no',
                'issue_codes' => implode('|', array_unique($codes)),
            ];

            foreach ($facilities->where('city_id', $city->id) as $facility) {
                foreach (array_unique($codes) as $code) {
                    $priority = str_contains($code, 'MISMATCH') || $code === 'GEO_STATE_INVALID' ? 'high' : 'medium';
                    $this->add($facility, $code, 'geography', $city->name, null, 'Geografieprüfung: '.$code.'.', 'geography', $priority, 'high', 'Gemeinde- und Landkreis-Zuordnung manuell prüfen.');
                }
            }
        }
    }

    private function auditDatabaseIntegrity(): void
    {
        foreach (DB::select('PRAGMA integrity_check') as $row) {
            $value = (string) (array_values((array) $row)[0] ?? 'unknown');
            if ($value !== 'ok') {
                $this->add(null, 'DB_INTEGRITY_FAILURE', 'database', $value, null, 'SQLite integrity_check meldet einen Fehler.', 'database', 'critical', 'high', 'Keine Daten ändern; Datenbank aus Backup untersuchen.');
            }
        }
        foreach (DB::select('PRAGMA foreign_key_check') as $row) {
            $this->add(null, 'DB_FOREIGN_KEY_FAILURE', 'database', json_encode($row), null, 'SQLite meldet eine verletzte Fremdschlüsselbeziehung.', 'database', 'critical', 'high', 'Beziehung und Backup manuell untersuchen.');
        }
    }

    private function auditText(Facility $facility, string $field, ?string $value, string $prefix): void
    {
        if ($value === null) {
            return;
        }
        if ($value !== trim($value) || preg_match('/\s{2,}/u', $value)) {
            $this->add($facility, $prefix.'_WHITESPACE', $field, $value, preg_replace('/\s+/u', ' ', trim($value)), 'Text enthält überflüssige Leerzeichen.', strtolower($prefix), 'low', 'high', 'Nach manueller Prüfung normalisieren.');
        }
        if ($value !== strip_tags($value) || preg_match('/&(?:[a-z]+|#\d+);/i', $value)) {
            $this->add($facility, $prefix.'_HTML', $field, $value, null, 'Text enthält HTML oder HTML-Entitäten.', strtolower($prefix), 'high', 'high', 'Originalquelle prüfen und Klartext übernehmen.');
        }
        if (preg_match('/(?:ï¿½|\x{FFFD}|Ã.|Â.|Р[А-Яа-я])/u', $value)) {
            $this->add($facility, $prefix.'_ENCODING', $field, $value, null, 'Text enthält Hinweise auf beschädigte Zeichenkodierung.', strtolower($prefix), 'high', 'medium', 'Wert gegen die Originalquelle prüfen.');
        }
    }

    private function add(?Facility $facility, string $code, string $field, mixed $current, mixed $normalized, string $issue, string $category, string $priority, string $confidence, string $action): void
    {
        $key = ($facility?->id ?? 'database').'|'.$code.'|'.$field.'|'.json_encode($current);
        $this->issues[$key] = [
            'issue_code' => $code,
            'facility_id' => $facility?->id,
            'facility_name' => $facility?->name,
            'city' => $facility?->city?->name,
            'field' => $field,
            'current_value' => is_scalar($current) || $current === null ? $current : json_encode($current, JSON_UNESCAPED_UNICODE),
            'normalized_value' => $normalized,
            'issue' => $issue,
            'category' => $category,
            'priority' => $priority,
            'confidence' => $confidence,
            'recommended_action' => $action,
            'verification_status' => $facility?->contact_status,
            'source' => $facility?->source_id,
            'source_url' => $facility?->contact_source,
            'detected_at' => now()->toIso8601String(),
        ];
    }

    /** @param Collection<int, Facility> $facilities @param Collection<int, array<string, mixed>> $issues */
    private function facilityRows(Collection $facilities, Collection $issues): array
    {
        return $facilities->map(function (Facility $f) use ($issues): array {
            $own = $issues->where('facility_id', $f->id);
            $counts = collect(self::PRIORITIES)->mapWithKeys(fn (string $p) => [$p => $own->where('priority', $p)->count()]);
            $highest = collect(self::PRIORITIES)->first(fn (string $p) => $counts[$p] > 0);

            return [
                'facility_id' => $f->id, 'name' => $f->name, 'slug' => $f->slug, 'city' => $f->city?->name,
                'gemeinde' => $f->city?->geoMunicipality?->name, 'landkreis' => $f->city?->geoMunicipality?->district?->display_name,
                'type' => $f->type, 'phone' => $f->phone, 'email' => $f->email, 'website' => $f->website,
                'verification_status' => $f->contact_status, 'provenance' => $f->source_id,
                'verified_at' => $f->contact_checked_at?->toIso8601String(), 'issues_total' => $own->count(),
                'critical_count' => $counts['critical'], 'high_count' => $counts['high'], 'medium_count' => $counts['medium'], 'low_count' => $counts['low'],
                'overall_audit_status' => $highest ?? 'clean', 'manual_review_required' => $own->isNotEmpty() ? 'yes' : 'no',
            ];
        })->all();
    }

    /** @param Collection<int, Facility> $facilities @param Collection<int, array<string, mixed>> $issues */
    private function verificationQueue(Collection $facilities, Collection $issues): array
    {
        $rows = $facilities->map(function (Facility $f) use ($issues): ?array {
            $own = $issues->where('facility_id', $f->id);
            if ($own->isEmpty()) {
                return null;
            }
            $ordered = $own->sortBy(fn (array $i): int => array_search($i['priority'], self::PRIORITIES, true));
            $main = $ordered->first();
            $rank = array_search($main['priority'], self::PRIORITIES, true) * 100;
            if ($own->contains(fn ($i) => $i['category'] === 'duplicate')) {
                $rank += 10;
            }
            if ($f->contact_status === 'pending') {
                $rank += 20;
            }
            if (blank($f->contact_source)) {
                $rank += 30;
            }
            if (blank($f->phone) && blank($f->email) && blank($f->website)) {
                $rank += 40;
            }
            $cityRank = array_search($f->city?->slug, self::MAJOR_CITIES, true);
            if ($cityRank !== false) {
                $rank += 50 + $cityRank;
            }

            return [
                '_rank' => $rank, 'queue_position' => 0, 'priority' => $main['priority'], 'facility_id' => $f->id,
                'name' => $f->name, 'city' => $f->city?->name, 'type' => $f->type, 'address' => $f->address,
                'phone' => $f->phone, 'email' => $f->email, 'website' => $f->website,
                'verification_status' => $f->contact_status, 'provenance' => $f->source_id,
                'main_issue' => $main['issue'], 'all_issue_codes' => $own->pluck('issue_code')->unique()->implode('|'),
                'recommended_action' => $main['recommended_action'], 'manual_review_required' => 'yes',
                'review_status' => '', 'review_notes' => '', 'verified_name' => '', 'verified_address' => '',
                'verified_phone' => '', 'verified_email' => '', 'verified_website' => '', 'verified_source_url' => '', 'final_status' => '',
            ];
        })->filter()->sortBy(['_rank', 'facility_id'])->values();

        return $rows->map(function (array $row, int $index): array {
            unset($row['_rank']);
            $row['queue_position'] = $index + 1;

            return $row;
        })->all();
    }

    /** @param Collection<int, Facility> $facilities @param Collection<int, array<string, mixed>> $issues */
    private function summary(Collection $facilities, Collection $issues): array
    {
        $idsWithIssues = $issues->pluck('facility_id')->filter()->unique();
        $statuses = ['verified', 'partially_verified', 'needs_review', 'conflict', 'not_reviewed', 'closed', 'duplicate'];
        $statusCounts = collect($statuses)->mapWithKeys(fn (string $s) => [$s => $facilities->where('contact_status', $s)->count()])->all();
        $total = $facilities->count();
        $coverage = fn (callable $test): array => ['count' => $facilities->filter($test)->count(), 'percent' => $total ? round($facilities->filter($test)->count() * 100 / $total, 1) : 0];

        return [
            'run_at' => now()->toIso8601String(), 'commit' => $this->gitHead(), 'database' => (string) config('database.connections.sqlite.database'),
            'database_integrity' => $issues->contains(fn ($i) => $i['category'] === 'database') ? 'failed' : 'ok',
            'facilities_total' => $total, 'cities_total' => $facilities->pluck('city_id')->filter()->unique()->count(),
            'gemeinden_total' => GeoMunicipality::count(), 'landkreise_total' => GeoDistrict::count(),
            'issues_by_category' => $issues->countBy('category')->sortKeys()->all(),
            'issues_by_priority' => collect(self::PRIORITIES)->mapWithKeys(fn ($p) => [$p => $issues->where('priority', $p)->count()])->all(),
            'facilities_clean' => $total - $idsWithIssues->count(), 'facilities_with_issues' => $idsWithIssues->count(),
            'facilities_with_critical' => $issues->where('priority', 'critical')->pluck('facility_id')->filter()->unique()->count(),
            'facilities_with_high' => $issues->where('priority', 'high')->pluck('facility_id')->filter()->unique()->count(),
            'coverage' => [
                'phone' => $coverage(fn (Facility $f) => filled($f->phone)), 'email' => $coverage(fn (Facility $f) => filled($f->email)),
                'website' => $coverage(fn (Facility $f) => filled($f->website)), 'verified' => $coverage(fn (Facility $f) => $f->contact_status === 'verified'),
                'source' => $coverage(fn (Facility $f) => filled($f->contact_source)), 'verification_date' => $coverage(fn (Facility $f) => $f->contact_checked_at !== null),
            ],
            'potential_duplicate_groups' => count($this->duplicates),
            'unresolved_geography' => collect($this->geography)->filter(fn ($g) => filled($g['issue_codes']))->count(),
            'verification_statuses' => $statusCounts + ['pending' => $facilities->where('contact_status', 'pending')->count(), 'not_found' => $facilities->where('contact_status', 'not_found')->count(), 'null' => $facilities->whereNull('contact_status')->count()],
        ];
    }

    private function key(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        $value = Str::ascii(mb_strtolower(trim($value)));
        $value = preg_replace('/\b(?:g|ggmbh|gmbh|ev|e\.v|pflege gmbh|standort|filiale)\b/i', '', $value);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private function phoneKey(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value);
        if (str_starts_with($digits, '0049')) {
            $digits = '49'.substr($digits, 4);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '49'.substr($digits, 1);
        }

        return strlen($digits) >= 6 ? $digits : null;
    }

    private function websiteKey(?string $value): ?string
    {
        if (blank($value) || FacilityDataAuditor::auditWebsite($value) === 'invalid') {
            return null;
        }
        $host = preg_replace('/^www\./i', '', strtolower((string) parse_url(trim($value), PHP_URL_HOST)));
        $path = rtrim((string) parse_url(trim($value), PHP_URL_PATH), '/');

        return $host.$path;
    }

    private function similarity(?string $a, ?string $b): float
    {
        similar_text((string) $this->key($a), (string) $this->key($b), $percent);

        return $percent;
    }

    private function gitHead(): ?string
    {
        $git = base_path('.git');
        $head = @file_get_contents($git.'/HEAD');
        if (! is_string($head)) {
            return null;
        }
        $head = trim($head);
        if (! str_starts_with($head, 'ref: ')) {
            return $head;
        }
        $ref = substr($head, 5);
        $value = @file_get_contents($git.'/'.$ref);

        return is_string($value) ? trim($value) : null;
    }
}

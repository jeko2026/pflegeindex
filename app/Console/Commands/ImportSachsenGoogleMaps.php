<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Facility;
use App\Models\FacilityOpeningHour;
use App\Models\FacilitySocialLink;
use App\Models\FacilitySource;
use App\Models\ServiceType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportSachsenGoogleMaps extends Command
{
    protected $signature = 'facilities:import-sachsen-gmaps {path=../DB/G-Maps-Extracktor} {--dry-run : Parse and report without database writes} {--include-outside-city : Import records whose Municipality differs from the query city}';

    protected $description = 'Import Sachsen Google Maps Extractor CSVs with provenance and conservative deduplication.';

    private array $review = [];

    private array $stats = [];

    public function handle(): int
    {
        $path = realpath(base_path($this->argument('path')));
        if ($path === false) {
            $this->error('CSV directory was not found.');

            return self::FAILURE;
        }
        $files = glob($path.DIRECTORY_SEPARATOR.'*.csv') ?: [];
        if ($files === []) {
            $this->error('No CSV files found.');

            return self::FAILURE;
        }
        $this->seedServices();
        foreach ($files as $file) {
            $this->importFile($file);
        }
        $this->writeReports();
        $total = collect($this->stats)->reduce(fn ($carry, $row) => $this->sum($carry, $row), $this->emptyStats());
        $this->table(array_keys($total), [$total]);
        $this->info(sprintf('%d CSV files, %d raw records, %d unique facilities.', count($files), $total['raw_records'], $total['unique_facilities']));

        return self::SUCCESS;
    }

    private function importFile(string $file): void
    {
        $handle = fopen($file, 'rb');
        $header = fgetcsv($handle);
        if (! is_array($header)) {
            return;
        }
        $cityName = null;
        $rowNo = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $rowNo++;
            // Extractor leaves Street unquoted although it may contain a comma.
            if (count($values) === count($header) + 1) {
                $values[2] = $values[2].','.$values[3];
                array_splice($values, 3, 1);
            }
            $row = array_combine($header, array_pad($values, count($header), null));
            if (! is_array($row)) {
                continue;
            }
            $keyword = trim((string) ($row['Search Keyword'] ?? ''));
            [$queryType, $targetCity] = $this->queryMeta($keyword, basename($file));
            $cityName ??= $targetCity;
            $this->stats[$targetCity] ??= $this->emptyStats();
            $this->stats[$targetCity]['city'] = $targetCity;
            $this->stats[$targetCity]['raw_records']++;
            $name = $this->clean($row['Name'] ?? null);
            $address = $this->clean($row['Fulladdress'] ?? null);
            if ($name === null || $address === null) {
                $this->stats[$targetCity]['invalid_records']++;

                continue;
            }
            $municipality = $this->clean($row['Municipality'] ?? null);
            if ($municipality !== null && ! $this->sameText($municipality, $targetCity)) {
                $this->stats[$targetCity]['outside_city']++;
                if (! $this->option('include-outside-city')) {
                    $this->review($targetCity, $file, $rowNo, 'outside_city', $row);

                    continue;
                }
            }
            $normalized = $this->normalize($row, $targetCity);
            $match = $this->findMatch($normalized);
            if ($match['type'] === 'FUZZY') {
                $this->review($targetCity, $file, $rowNo, 'fuzzy_duplicate', $row, $match);
                $this->stats[$targetCity]['duplicates_removed']++;

                continue;
            }
            if (! $this->option('dry-run')) {
                DB::transaction(function () use ($normalized, $row, $file, $rowNo, $queryType, $targetCity, $match): void {
                    $this->persist($normalized, $row, $file, $rowNo, $queryType, $targetCity, $match);
                });
            }
            if ($match['facility'] === null) {
                $this->stats[$targetCity]['unique_facilities']++;
            } else {
                $this->stats[$targetCity]['duplicates_removed']++;
            }
            foreach (['phone', 'website', 'email'] as $field) {
                if ($normalized[$field] !== null) {
                    $this->stats[$targetCity]['with_'.$field]++;
                }
            }
            foreach (['facebook', 'instagram', 'linkedin'] as $platform) {
                if ($normalized['social'][$platform] ?? null) {
                    $this->stats[$targetCity]['with_'.$platform]++;
                }
            }
            $this->stats[$targetCity][$queryType.'_count']++;
        }
        fclose($handle);
    }

    private function persist(array $n, array $raw, string $file, int $rowNo, string $query, string $cityName, array $match): void
    {
        $city = City::firstOrCreate(['slug' => Str::slug($cityName)], ['name' => $cityName, 'state' => 'Sachsen', 'state_slug' => 'sachsen']);
        $facility = $match['facility'];
        if ($facility === null) {
            $slug = Str::slug($n['name'].'-'.$n['postal_code']);
            $baseSlug = $slug;
            $number = 2;
            while (Facility::where('city_id', $city->id)->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$number++;
            }
            $facility = Facility::create([
                'source_id' => 'gmaps-'.($n['place_id'] ?: $n['cid'] ?: substr(sha1($n['name'].'|'.$n['address']), 0, 24)),
                'city_id' => $city->id, 'name' => $n['name'], 'slug' => $slug, 'postal_code' => $n['postal_code'], 'address' => $n['address'],
                'street' => $n['street'], 'house_number' => null, 'type' => 'Pflegeeinrichtung', 'care_types' => [],
                'phone' => $n['phone'], 'email' => $n['email'], 'website' => $n['website'], 'website_domain' => $n['domain'],
                'latitude' => $n['latitude'], 'longitude' => $n['longitude'], 'google_rating' => $n['rating'], 'google_review_count' => $n['reviews'],
                'google_place_id' => $n['place_id'], 'google_cid' => $n['cid'], 'is_active' => true, 'verification_status' => 'unverified',
            ]);
        }
        $service = ServiceType::where('slug', $query)->first();
        if ($service) {
            $facility->serviceTypes()->syncWithoutDetaching([$service->id]);
        }
        FacilitySource::updateOrCreate(['facility_id' => $facility->id, 'source_type' => 'google_maps', 'source_record_id' => basename($file).'#'.$rowNo], [
            'source_name' => 'Google Maps Extractor', 'source_url' => $raw['Google Maps URL'] ?? null, 'source_file' => basename($file), 'search_keyword' => $raw['Search Keyword'] ?? null, 'query_type' => $query,
            'raw_name' => $raw['Name'] ?? null, 'raw_address' => $raw['Fulladdress'] ?? null, 'raw_phone' => $raw['Phone'] ?? null, 'raw_email' => $raw['Emails'] ?? null, 'raw_website' => $raw['Website'] ?? null,
            'first_seen_at' => now(), 'last_seen_at' => now(), 'imported_at' => now(), 'raw_payload_json' => $raw,
        ]);
        foreach ($n['social'] as $platform => $url) {
            if ($url) {
                FacilitySocialLink::firstOrCreate(['facility_id' => $facility->id, 'platform' => $platform, 'url' => $url], ['source_type' => 'google_maps']);
            }
        }
        if ($n['hours']) {
            FacilityOpeningHour::updateOrCreate(['facility_id' => $facility->id], ['hours_text' => $n['hours'], 'source_type' => 'google_maps']);
        }
    }

    private function normalize(array $r, string $city): array
    {
        $website = $this->url($r['Website'] ?? null);
        $address = preg_replace('/,\s*Germany\s*$/i', '', $this->clean($r['Fulladdress']) ?? '');
        preg_match('/\b(\d{5})\b/', $address, $postcode);
        $social = [];
        foreach (['Facebook Links' => 'facebook', 'Instagram Links' => 'instagram', 'Linkedin Links' => 'linkedin', 'Youtube Links' => 'youtube', 'Tiktok Links' => 'tiktok', 'Twitter Links' => 'twitter'] as $column => $platform) {
            $social[$platform] = $this->url($r[$column] ?? null);
        }

        return ['name' => $this->clean($r['Name']), 'address' => $address, 'street' => $this->clean($r['Street']), 'postal_code' => $postcode[1] ?? '00000', 'phone' => $this->phone($r['Phone'] ?? null), 'email' => $this->email($r['Emails'] ?? null), 'website' => $website, 'domain' => $website ? parse_url($website, PHP_URL_HOST) : null, 'place_id' => $this->clean($r['Place Id'] ?? null), 'cid' => $this->clean($r['Cid'] ?? null), 'latitude' => is_numeric($r['Latitude'] ?? null) ? $r['Latitude'] : null, 'longitude' => is_numeric($r['Longitude'] ?? null) ? $r['Longitude'] : null, 'rating' => is_numeric($r['Average Rating'] ?? null) ? $r['Average Rating'] : null, 'reviews' => is_numeric($r['Review Count'] ?? null) ? (int) $r['Review Count'] : null, 'hours' => $this->clean($r['Opening Hours'] ?? null), 'social' => $social];
    }

    private function findMatch(array $n): array
    {
        foreach ([['google_place_id', $n['place_id'], 'PLACE_ID', 1.0], ['google_cid', $n['cid'], 'CID', .99]] as [$column,$value,$type,$confidence]) {
            if ($value && ($f = Facility::where($column, $value)->first())) {
                return compact('f', 'type', 'confidence') + ['facility' => $f];
            }
        }
        if ($n['phone'] && ($f = Facility::where('address', $n['address'])->where('phone', $n['phone'])->first())) {
            return ['facility' => $f, 'type' => 'ADDRESS_PHONE', 'confidence' => .95];
        }
        if (($f = Facility::where('name', $n['name'])->where('address', $n['address'])->first())) {
            return ['facility' => $f, 'type' => 'NAME_ADDRESS', 'confidence' => .90];
        }
        $candidate = Facility::where('address', $n['address'])->first();
        if ($candidate && similar_text(mb_strtolower($candidate->name), mb_strtolower($n['name'])) >= 80) {
            return ['facility' => $candidate, 'type' => 'FUZZY', 'confidence' => .80];
        }

        return ['facility' => null, 'type' => 'NEW', 'confidence' => 1.0];
    }

    private function queryMeta(string $keyword, string $file): array
    {
        preg_match('/^(Pflegedienst|Pflegeheim|Tagespflege|Kurzzeitpflege)\s+(.+)$/u', $keyword ?: str_replace(['_records.csv', '_'], ['', ' '], $file), $m);
        $map = ['Pflegedienst' => 'ambulante_pflege', 'Pflegeheim' => 'pflegeheim', 'Tagespflege' => 'tagespflege', 'Kurzzeitpflege' => 'kurzzeitpflege'];

        return [$map[$m[1] ?? ''] ?? 'pflegeheim', $m[2] ?? 'Unbekannt'];
    }

    private function seedServices(): void
    {
        foreach (['ambulante_pflege' => 'Ambulante Pflege', 'pflegeheim' => 'Pflegeheim', 'tagespflege' => 'Tagespflege', 'kurzzeitpflege' => 'Kurzzeitpflege'] as $slug => $name) {
            if (! $this->option('dry-run')) {
                ServiceType::firstOrCreate(['slug' => $slug], ['name' => $name]);
            }
        }
    }

    private function phone($value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) < 6) {
            return null;
        }

return str_starts_with($digits, '49') ? '+'.$digits : (str_starts_with($digits, '0') ? '+49'.substr($digits, 1) : '+'.$digits);
    }

    private function email($value): ?string
    {
        preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', (string) $value, $m);

        return isset($m[0]) ? mb_strtolower($m[0]) : null;
    }

    private function url($value): ?string
    {
        $v = trim(explode(',', (string) $value)[0]);
        if ($v === '' || ! filter_var('https://'.preg_replace('#^https?://#', '', $v), FILTER_VALIDATE_URL)) {
            return null;
        }

return 'https://'.ltrim(preg_replace('#^https?://#', '', $v), '/');
    }

    private function clean($value): ?string
    {
        $v = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $v === '' ? null : $v;
    }

    private function sameText(string $a, string $b): bool
    {
        $a = preg_replace('/^\\d{5}\\s+/u', '', trim($a)) ?? $a;

        return Str::slug($a) === Str::slug($b);
    }

    private function emptyStats(): array
    {
        return array_fill_keys(['city', 'raw_records', 'unique_facilities', 'duplicates_removed', 'outside_city', 'invalid_records', 'with_phone', 'with_website', 'with_email', 'with_facebook', 'with_instagram', 'with_linkedin', 'pflegeheim_count', 'ambulante_pflege_count', 'tagespflege_count', 'kurzzeitpflege_count'], '0');
    }

    private function sum(array $a, array $b): array
    {
        foreach ($a as $key => $value) {
            if ($key !== 'city') {
                $a[$key] = (int) $value + (int) ($b[$key] ?? 0);
            }
        } $a['city'] = 'TOTAL';

        return $a;
    }

    private function review(string $city, string $file, int $row, string $reason, array $raw, array $match = []): void
    {
        $this->review[] = ['city' => $city, 'file' => basename($file), 'row' => $row, 'reason' => $reason, 'name' => $raw['Name'] ?? '', 'address' => $raw['Fulladdress'] ?? '', 'match_type' => $match['type'] ?? '', 'match_confidence' => $match['confidence'] ?? ''];
    }

    private function writeReports(): void
    {
        $dir = base_path('reports');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        } $rows = array_values($this->stats);
        $rows[] = $total = collect($rows)->reduce(fn ($c, $r) => $this->sum($c, $r), $this->emptyStats());
        foreach ([['sachsen_gmaps_import_report.csv', $rows], ['sachsen_review_candidates.csv', $this->review]] as [$file,$data]) {
            $h = fopen($dir.'/'.$file, 'wb');
            if ($data) {
                fputcsv($h, array_keys($data[0]));
                foreach ($data as $line) {
                    fputcsv($h,$line);
                }
            } fclose($h);
        } file_put_contents($dir.'/sachsen-gmaps-import-report.html','<!doctype html><meta charset="utf-8"><title>Sachsen G Maps Import</title><pre>'.e(json_encode($rows,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)).'</pre>');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\GeoCountry;
use App\Models\GeoDistrict;
use App\Models\GeoMunicipality;
use App\Models\GeoState;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportBrandenburgGeoCore extends Command
{
    private const EXPECTED_CITIES = 257;

    private const EXPECTED_FACILITIES = 1557;

    private const EXPECTED_SAFE_MATCHES = 180;

    private const EXPECTED_MANUAL_REVIEW = 77;

    private const EXPECTED_MUNICIPALITIES = 413;

    /** @var list<string> */
    private const DISTRICT_TYPES = ['landkreis', 'kreisfreie_stadt'];

    /** @var list<string> */
    private const MATCH_STATUSES = ['exact', 'normalized', 'partial', 'locality', 'ambiguous', 'unmatched'];

    /** @var list<string> */
    private const MATCH_METHODS = [
        'exact_official_name',
        'normalized_official_name',
        'unique_partial_candidate',
        'audit_existing_candidate',
        'locality_lookup',
        'manual_required',
        'no_candidate',
    ];

    /** @var list<string> */
    private const CONFIDENCE_VALUES = ['high', 'medium', 'low', 'none'];

    protected $signature = 'geocore:import-brandenburg
                            {--dry-run : Validate and show the import plan without writing data}
                            {--force-local : Allow a non-production environment other than local/testing}
                            {--official= : Override official-municipalities.csv for local validation/testing}
                            {--mapping= : Override pflegeindex-city-mapping.csv for local validation/testing}';

    protected $description = 'Import the official Brandenburg GeoCore layer and verified city mappings';

    public function handle(): int
    {
        try {
            $this->guardEnvironment();
            $officialRows = $this->readCsv(
                $this->resolvePath('official', storage_path('app/geocore/brandenburg/official-municipalities.csv')),
            );
            $mappingRows = $this->readCsv(
                $this->resolvePath('mapping', storage_path('app/geocore/brandenburg/pflegeindex-city-mapping.csv')),
            );
            $plan = $this->validateAndBuildPlan($officialRows, $mappingRows);

            $this->renderPlan($plan, (bool) $this->option('dry-run'));

            if ($this->option('dry-run')) {
                $this->components->info('Dry-run completed. No database rows or timestamps were changed.');

                return self::SUCCESS;
            }

            DB::transaction(function () use ($plan): void {
                $this->importPlan($plan);
            });

            $this->components->info('Brandenburg GeoCore import completed successfully.');
            $this->renderDatabaseTotals();

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function guardEnvironment(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('GeoCore import is blocked in production. No production override is implemented.');
        }

        if (! app()->environment(['local', 'testing']) && ! $this->option('force-local')) {
            throw new RuntimeException('GeoCore import is limited to local/testing. Use --force-local only for an explicitly local database.');
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('GeoCore import requires a local SQLite database.');
        }
    }

    private function resolvePath(string $option, string $default): string
    {
        $path = $this->option($option) ?: $default;
        $resolved = realpath((string) $path);

        if ($resolved === false || ! is_file($resolved)) {
            throw new RuntimeException("GeoCore CSV not found: {$path}");
        }

        return $resolved;
    }

    /** @return list<array<string, string>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to read GeoCore CSV: {$path}");
        }

        try {
            if (fread($handle, 3) !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $header = fgetcsv($handle, null, ',', '"', '\\');

            if (! is_array($header) || $header === []) {
                throw new RuntimeException("GeoCore CSV has no header: {$path}");
            }

            if (count($header) !== count(array_unique($header))) {
                throw new RuntimeException("GeoCore CSV contains duplicate headers: {$path}");
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                $line++;

                if ($values === [null] || $values === []) {
                    continue;
                }

                if (count($values) !== count($header)) {
                    throw new RuntimeException("GeoCore CSV column mismatch at {$path}:{$line}");
                }

                $row = array_combine($header, array_map(
                    static fn (mixed $value): string => trim((string) $value),
                    $values,
                ));

                if (! is_array($row)) {
                    throw new RuntimeException("Unable to parse GeoCore CSV row at {$path}:{$line}");
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<array<string, string>>  $officialRows
     * @param  list<array<string, string>>  $mappingRows
     * @return array<string, mixed>
     */
    private function validateAndBuildPlan(array $officialRows, array $mappingRows): array
    {
        if (count($officialRows) !== self::EXPECTED_MUNICIPALITIES) {
            throw new RuntimeException('official-municipalities.csv must contain exactly 413 rows.');
        }

        if (count($mappingRows) !== self::EXPECTED_CITIES) {
            throw new RuntimeException('pflegeindex-city-mapping.csv must contain exactly 257 rows.');
        }

        $officialRequired = [
            'country_code', 'country_name', 'state_ags', 'state_name', 'district_ags',
            'district_name', 'district_type', 'municipality_ags', 'municipality_name_official',
            'municipality_name_normalized', 'municipality_type', 'municipality_slug',
            'postal_code_official', 'source_name', 'source_date', 'source_url',
        ];
        $mappingRequired = [
            'current_city_id', 'current_city_name', 'current_city_slug', 'current_state',
            'current_state_slug', 'current_postal_codes', 'facility_count',
            'official_municipality_ags', 'match_status', 'match_method', 'confidence',
            'requires_manual_review',
        ];
        $this->assertHeaders($officialRows[0], $officialRequired, 'official-municipalities.csv');
        $this->assertHeaders($mappingRows[0], $mappingRequired, 'pflegeindex-city-mapping.csv');

        $municipalityAgs = [];
        $districts = [];
        $country = null;
        $state = null;

        foreach ($officialRows as $index => $row) {
            $line = $index + 2;
            $this->assertRequired($row, $officialRequired, "official-municipalities.csv:{$line}", [
                'municipality_type',
                'postal_code_official',
                'source_date',
                'source_url',
            ]);
            $this->assertPattern($row['state_ags'], '/^\d{2}$/', "state_ags at line {$line}");
            $this->assertPattern($row['district_ags'], '/^\d{5}$/', "district_ags at line {$line}");
            $this->assertPattern($row['municipality_ags'], '/^\d{8}$/', "municipality_ags at line {$line}");
            $this->assertEnum($row['district_type'], self::DISTRICT_TYPES, "district_type at line {$line}");

            if ($row['postal_code_official'] !== '') {
                $this->assertPattern($row['postal_code_official'], '/^\d{5}$/', "postal_code_official at line {$line}");
            }

            if ($row['source_date'] !== '' && DateTimeImmutable::createFromFormat('!Y-m-d', $row['source_date']) === false) {
                throw new RuntimeException("Invalid source_date at official-municipalities.csv:{$line}");
            }

            if (isset($municipalityAgs[$row['municipality_ags']])) {
                throw new RuntimeException("Duplicate municipality AGS {$row['municipality_ags']}");
            }

            $municipalityAgs[$row['municipality_ags']] = true;
            $districtKey = $row['district_ags'];
            $districtData = [
                'ags' => $districtKey,
                'name' => $row['district_name'],
                'type' => $row['district_type'],
            ];

            if (isset($districts[$districtKey]) && $districts[$districtKey] !== $districtData) {
                throw new RuntimeException("Conflicting district data for AGS {$districtKey}");
            }

            $districts[$districtKey] = $districtData;
            $countryData = ['iso2' => $row['country_code'], 'name' => $row['country_name']];
            $stateData = ['ags' => $row['state_ags'], 'name' => $row['state_name']];

            if ($country !== null && $country !== $countryData) {
                throw new RuntimeException('Official CSV contains more than one country.');
            }

            if ($state !== null && $state !== $stateData) {
                throw new RuntimeException('Official CSV contains more than one state.');
            }

            $country = $countryData;
            $state = $stateData;
        }

        if ($country !== ['iso2' => 'DE', 'name' => 'Deutschland'] || $state !== ['ags' => '12', 'name' => 'Brandenburg']) {
            throw new RuntimeException('Official CSV must describe Germany / Brandenburg (DE / AGS 12).');
        }

        $districtTypeCounts = collect($districts)->countBy('type');

        if (count($districts) !== 18 || $districtTypeCounts->get('landkreis') !== 14 || $districtTypeCounts->get('kreisfreie_stadt') !== 4) {
            throw new RuntimeException('Official CSV must contain 14 Landkreise and 4 kreisfreie Städte.');
        }

        $cities = City::query()->withCount('facilities')->get()->keyBy(fn (City $city): string => (string) $city->id);

        if ($cities->count() !== self::EXPECTED_CITIES) {
            throw new RuntimeException('The database must contain exactly 257 cities before GeoCore import.');
        }

        $seenIds = [];
        $seenSlugs = [];
        $facilityTotal = 0;
        $safeMatches = 0;
        $manualReview = 0;

        foreach ($mappingRows as $index => &$row) {
            $line = $index + 2;
            $this->assertRequired($row, $mappingRequired, "pflegeindex-city-mapping.csv:{$line}", [
                'official_municipality_ags',
            ]);
            $this->assertPattern($row['current_city_id'], '/^\d+$/', "current_city_id at line {$line}");
            $this->assertPattern($row['facility_count'], '/^\d+$/', "facility_count at line {$line}");
            $this->assertEnum($row['match_status'], self::MATCH_STATUSES, "match_status at line {$line}");
            $this->assertEnum($row['match_method'], self::MATCH_METHODS, "match_method at line {$line}");
            $this->assertEnum($row['confidence'], self::CONFIDENCE_VALUES, "confidence at line {$line}");
            $row['_manual'] = $this->parseBoolean($row['requires_manual_review'], "requires_manual_review at line {$line}");

            if (isset($seenIds[$row['current_city_id']])) {
                throw new RuntimeException("Duplicate current_city_id {$row['current_city_id']}");
            }

            if (isset($seenSlugs[$row['current_city_slug']])) {
                throw new RuntimeException("Duplicate current_city_slug {$row['current_city_slug']}");
            }

            $seenIds[$row['current_city_id']] = true;
            $seenSlugs[$row['current_city_slug']] = true;
            $city = $cities->get($row['current_city_id']);

            if (! $city instanceof City) {
                throw new RuntimeException("City {$row['current_city_id']} does not exist in the database.");
            }

            if ($city->name !== $row['current_city_name'] || $city->slug !== $row['current_city_slug']) {
                throw new RuntimeException("City identity mismatch for ID {$row['current_city_id']}.");
            }

            if ((int) $city->facilities_count !== (int) $row['facility_count']) {
                throw new RuntimeException("Facility count mismatch for city ID {$row['current_city_id']}.");
            }

            $facilityTotal += (int) $row['facility_count'];
            $safe = $row['_manual'] === false
                && $row['confidence'] === 'high'
                && in_array($row['match_status'], ['exact', 'normalized'], true)
                && $row['official_municipality_ags'] !== '';

            if ($safe) {
                if (! isset($municipalityAgs[$row['official_municipality_ags']])) {
                    throw new RuntimeException("Unknown official municipality AGS {$row['official_municipality_ags']} at mapping line {$line}");
                }

                $safeMatches++;
            } else {
                if ($row['_manual'] !== true) {
                    throw new RuntimeException("Unsafe mapping must require manual review at line {$line}");
                }

                if ($row['official_municipality_ags'] !== '') {
                    throw new RuntimeException("Manual-review mapping must not assign an official AGS at line {$line}");
                }

                $manualReview++;
            }
        }
        unset($row);

        if (count($seenIds) !== self::EXPECTED_CITIES || count($seenSlugs) !== self::EXPECTED_CITIES) {
            throw new RuntimeException('Mapping must cover 257 unique city IDs and slugs.');
        }

        if ($facilityTotal !== self::EXPECTED_FACILITIES || DB::table('facilities')->count() !== self::EXPECTED_FACILITIES) {
            throw new RuntimeException('Facility total must remain exactly 1557.');
        }

        if ($safeMatches !== self::EXPECTED_SAFE_MATCHES || $manualReview !== self::EXPECTED_MANUAL_REVIEW) {
            throw new RuntimeException('Mapping must contain exactly 180 safe matches and 77 manual-review rows.');
        }

        return [
            'country' => $country,
            'state' => $state,
            'districts' => array_values($districts),
            'municipalities' => $officialRows,
            'mappings' => $mappingRows,
            'safe_matches' => $safeMatches,
            'manual_review' => $manualReview,
            'facility_total' => $facilityTotal,
        ];
    }

    /** @param array<string, string> $row @param list<string> $required */
    private function assertHeaders(array $row, array $required, string $file): void
    {
        $missing = array_diff($required, array_keys($row));

        if ($missing !== []) {
            throw new RuntimeException($file.' is missing headers: '.implode(', ', $missing));
        }
    }

    /** @param array<string, string> $row @param list<string> $required @param list<string> $nullable */
    private function assertRequired(array $row, array $required, string $context, array $nullable = []): void
    {
        $this->assertHeaders($row, $required, $context);

        foreach ($required as $field) {
            if (! in_array($field, $nullable, true) && $row[$field] === '') {
                throw new RuntimeException("Missing {$field} at {$context}");
            }
        }
    }

    private function assertPattern(string $value, string $pattern, string $context): void
    {
        if (preg_match($pattern, $value) !== 1) {
            throw new RuntimeException("Invalid {$context}: {$value}");
        }
    }

    /** @param list<string> $allowed */
    private function assertEnum(string $value, array $allowed, string $context): void
    {
        if (! in_array($value, $allowed, true)) {
            throw new RuntimeException("Unknown {$context}: {$value}");
        }
    }

    private function parseBoolean(string $value, string $context): bool
    {
        return match ($value) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw new RuntimeException("Invalid boolean {$context}: {$value}"),
        };
    }

    /** @param array<string, mixed> $plan */
    private function renderPlan(array $plan, bool $dryRun): void
    {
        $this->components->info($dryRun ? 'GeoCore dry-run plan validated.' : 'GeoCore import plan validated.');
        $this->table(
            ['Countries', 'States', 'Districts', 'Municipalities', 'Safe city links', 'Manual review', 'Facilities'],
            [[1, 1, count($plan['districts']), count($plan['municipalities']), $plan['safe_matches'], $plan['manual_review'], $plan['facility_total']]],
        );
    }

    /** @param array<string, mixed> $plan */
    private function importPlan(array $plan): void
    {
        $country = GeoCountry::query()->updateOrCreate(
            ['iso2' => 'DE'],
            ['iso3' => 'DEU', 'name' => 'Deutschland', 'slug' => 'deutschland'],
        );
        $state = GeoState::query()->updateOrCreate(
            ['country_id' => $country->id, 'ags' => '12'],
            ['name' => 'Brandenburg', 'slug' => 'brandenburg'],
        );

        $districtIds = [];

        foreach ($plan['districts'] as $row) {
            $district = GeoDistrict::query()->updateOrCreate(
                ['state_id' => $state->id, 'ags' => $row['ags']],
                [
                    'name' => $row['name'],
                    'slug' => $this->districtSlug($row['name'], $row['ags']),
                    'type' => $row['type'],
                ],
            );
            $districtIds[$row['ags']] = $district->id;
        }

        $municipalityIds = [];

        foreach ($plan['municipalities'] as $row) {
            $districtId = $districtIds[$row['district_ags']] ?? null;

            if (! is_int($districtId)) {
                throw new RuntimeException("District was not imported for municipality AGS {$row['municipality_ags']}");
            }

            $municipality = GeoMunicipality::query()->updateOrCreate(
                ['ags' => $row['municipality_ags']],
                [
                    'district_id' => $districtId,
                    'name' => $row['municipality_name_official'],
                    'normalized_name' => $row['municipality_name_normalized'],
                    'slug' => $row['municipality_slug'],
                    'municipality_type' => $row['municipality_type'] ?: null,
                    'postal_code_official' => $row['postal_code_official'] ?: null,
                    'source_name' => $row['source_name'],
                    'source_date' => $row['source_date'] ?: null,
                    'source_url' => $row['source_url'] ?: null,
                ],
            );
            $municipalityIds[$row['municipality_ags']] = $municipality->id;
        }

        foreach ($plan['mappings'] as $row) {
            $city = City::query()->findOrFail((int) $row['current_city_id']);
            $municipalityId = $row['_manual']
                ? null
                : ($municipalityIds[$row['official_municipality_ags']] ?? null);

            if (! $row['_manual'] && ! is_int($municipalityId)) {
                throw new RuntimeException("Municipality was not imported for city ID {$row['current_city_id']}");
            }

            $city->fill([
                'geo_municipality_id' => $municipalityId,
                'geo_match_status' => $row['match_status'],
                'geo_match_method' => $row['match_method'],
                'geo_match_confidence' => $row['confidence'],
                'geo_requires_manual_review' => $row['_manual'],
            ]);

            if ($city->isDirty()) {
                $city->save();
            }
        }
    }

    private function districtSlug(string $name, string $ags): string
    {
        $base = preg_replace('/,\s*Stadt$/u', '', $name) ?: $name;

        return Str::slug($base, '-', 'de') ?: 'district-'.$ags;
    }

    private function renderDatabaseTotals(): void
    {
        $this->table(
            ['Countries', 'States', 'Districts', 'Municipalities', 'Linked cities', 'Manual review', 'Facilities'],
            [[
                GeoCountry::count(),
                GeoState::count(),
                GeoDistrict::count(),
                GeoMunicipality::count(),
                City::query()->whereNotNull('geo_municipality_id')->count(),
                City::query()->where('geo_requires_manual_review', true)->count(),
                DB::table('facilities')->count(),
            ]],
        );
    }
}

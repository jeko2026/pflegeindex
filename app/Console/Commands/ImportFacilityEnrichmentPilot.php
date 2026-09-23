<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Services\FacilityEnrichment\FacilityAttributeUpserter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ImportFacilityEnrichmentPilot extends Command
{
    protected $signature = 'pflegeindex:enrichment-import-pilot {path=reports/facility-enrichment-pilot-100.json}';

    protected $description = 'Import existing HIGH pilot facts into local facility_attributes idempotently.';

    private const MAP = [
        'Barrierefreier Zugang' => 'accessibility.barrier_free', 'Rollstuhlgerecht' => 'accessibility.wheelchair_accessible', 'Aufzug' => 'accessibility.elevator', 'Barrierefreies Bad' => 'accessibility.accessible_bathroom',
        'Garten' => 'outdoor.garden', 'Balkon/Terrasse' => 'outdoor.balcony_terrace', 'Einzelzimmer' => 'room.single_room', 'Doppelzimmer' => 'room.double_room', 'Eigenes Bad' => 'room.private_bathroom', 'Eigene Möbel möglich' => 'room.own_furniture_allowed', 'Eigene Küche' => 'room.own_kitchen', 'WLAN' => 'room.wifi',
        'Friseur' => 'services.hairdresser', 'Fußpflege' => 'services.foot_care', 'Physiotherapie' => 'services.physiotherapy', 'Ergotherapie' => 'services.occupational_therapy', 'Logopädie' => 'services.speech_therapy', 'Diät/Sonderkost' => 'services.special_diet', 'Hausarztkooperation' => 'services.doctor_cooperation',
        'Demenz' => 'care.dementia', 'Palliativpflege' => 'care.palliative', 'Intensivpflege' => 'care.intensive', 'Junge Pflege' => 'care.young_care', 'Gerontopsychiatrie' => 'care.gerontopsychiatric', 'Wachkoma' => 'care.wachkoma',
        'Veranstaltungen' => 'activities.events', 'Ausflüge' => 'activities.excursions', 'Gymnastik' => 'activities.gymnastics', 'Gedächtnistraining' => 'activities.memory_training', 'Musik' => 'activities.music', 'Kreativangebote' => 'activities.creative', 'Gottesdienste' => 'activities.religious_services',
        'Hausnotruf' => 'safety.emergency_call', '24h-Erreichbarkeit' => 'contact.availability_24h', 'Kontaktformular' => 'contact.contact_form', 'Besuchszeiten' => 'contact.visiting_hours', 'Bildergalerie' => 'media.gallery', 'YouTube' => 'media.youtube', 'Haustiere' => 'living.pets_allowed',
    ];

    public function handle(FacilityAttributeUpserter $upserter): int
    {
        if (app()->environment('production')) {
            $this->error('Pilot import is blocked in production.');

            return self::FAILURE;
        }
        $path = base_path((string) $this->argument('path'));
        $rows = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $imported = 0;
        $missing = [];
        foreach ($rows as $row) {
            foreach (($row['facts'] ?? []) as $fact) {
                if (($fact['confidence'] ?? null) !== 'HIGH') {
                    continue;
                }
                $key = self::MAP[$fact['field'] ?? ''] ?? null;
                if ($key === null) {
                    $missing[] = $fact['field'] ?? '(missing)';

                    continue;
                }
                $facility = Facility::query()->with('city')->find($fact['facility_id']);
                if (! $facility) {
                    $missing[] = 'facility:'.($fact['facility_id'] ?? '?');

                    continue;
                }
                $attribute = $upserter->store($facility, ['attribute_key' => $key, 'attribute_value' => (string) $fact['value'], 'normalized_value' => Str::slug((string) $fact['field']), 'confidence' => 'HIGH', 'source_url' => (string) $fact['source_url'], 'source_type' => (string) $fact['source_type'], 'source_title' => parse_url((string) $fact['source_url'], PHP_URL_HOST), 'source_excerpt' => sprintf('Pilot-Beleg: %s für %s in %s.', $fact['field'], $facility->name, $facility->city->name), 'source_context' => 'Aus dem bestehenden HIGH-Pilot mit offizieller Standort- oder Betreiberquelle.', 'relation_reason' => 'pilot_high_official_source; facility_name; city', 'source_id' => null]);
                if (str_starts_with($key, 'care.')) {
                    $attribute->update(['review_status' => 'needs_review']);
                }
                $imported += $attribute->wasRecentlyCreated ? 1 : 0;
            }
        }
        $this->info("{$imported} new pilot facts imported; ".count($missing).' skipped.');

        return $missing === [] ? self::SUCCESS : self::FAILURE;
    }
}

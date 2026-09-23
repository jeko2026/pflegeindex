<?php

namespace App\Services\FacilityEnrichment;

use App\Models\Facility;
use App\Models\FacilityAttribute;
use Illuminate\Support\Collection;

final class FacilityAttributePresenter
{
    private const GROUPS = [
        'Ausstattung' => [
            'accessibility.barrier_free' => 'Barrierefreier Zugang', 'accessibility.elevator' => 'Aufzug',
            'outdoor.garden' => 'Garten', 'outdoor.balcony_terrace' => 'Balkon / Terrasse',
            'room.single_room' => 'Einzelzimmer', 'room.double_room' => 'Doppelzimmer',
            'room.private_bathroom' => 'Eigenes Bad', 'room.own_furniture_allowed' => 'Eigene Möbel möglich',
        ],
        'Service' => [
            'services.hairdresser' => 'Friseur', 'services.foot_care' => 'Fußpflege',
            'services.physiotherapy' => 'Physiotherapie', 'services.occupational_therapy' => 'Ergotherapie',
            'services.speech_therapy' => 'Logopädie', 'safety.emergency_call' => 'Hausnotruf',
        ],
        'Alltag & Aktivitäten' => [
            'activities.events' => 'Veranstaltungen', 'activities.excursions' => 'Ausflüge',
            'activities.gymnastics' => 'Gymnastik', 'activities.memory_training' => 'Gedächtnistraining',
            'activities.music' => 'Musikangebote', 'activities.creative' => 'Kreativangebote',
        ],
        'Kontakt & Medien' => [
            'contact.contact_form' => 'Kontaktformular', 'media.youtube' => 'YouTube',
            'media.gallery' => 'Bildergalerie', 'media.virtual_tour' => 'Virtueller Rundgang',
        ],
    ];

    public function for(Facility $facility): Collection
    {
        return $facility->enrichmentAttributes()
            ->whereIn('review_status', [FacilityAttribute::REVIEW_APPROVED, FacilityAttribute::REVIEW_AUTO_APPROVED])
            ->orderBy('attribute_key')->get();
    }

    public function publicGroups(Facility $facility): array
    {
        $allowed = $this->for($facility)->keyBy('attribute_key');

        return collect(self::GROUPS)->map(function (array $labels, string $heading) use ($allowed): array {
            $items = collect($labels)->map(fn (string $label, string $key) => $allowed->has($key) ? ['key' => $key, 'label' => $label] : null)->filter()->values()->all();

            return ['heading' => $heading, 'items' => $items];
        })->filter(fn (array $group) => $group['items'] !== [])->values()->all();
    }
}

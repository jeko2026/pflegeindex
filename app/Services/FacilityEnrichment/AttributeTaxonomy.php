<?php

namespace App\Services\FacilityEnrichment;

final class AttributeTaxonomy
{
    /** @var array<string, array{medical: bool}> */
    private const ATTRIBUTES = [
        'accessibility.barrier_free' => ['medical' => false], 'accessibility.elevator' => ['medical' => false], 'accessibility.wheelchair_accessible' => ['medical' => false], 'accessibility.accessible_bathroom' => ['medical' => false],
        'outdoor.garden' => ['medical' => false], 'outdoor.balcony_terrace' => ['medical' => false],
        'room.single_room' => ['medical' => false], 'room.double_room' => ['medical' => false],
        'room.private_bathroom' => ['medical' => false], 'room.own_furniture_allowed' => ['medical' => false], 'room.own_kitchen' => ['medical' => false], 'room.wifi' => ['medical' => false],
        'services.hairdresser' => ['medical' => false], 'services.foot_care' => ['medical' => false],
        'services.physiotherapy' => ['medical' => false], 'services.occupational_therapy' => ['medical' => false],
        'services.speech_therapy' => ['medical' => false], 'services.special_diet' => ['medical' => false], 'services.doctor_cooperation' => ['medical' => false], 'care.dementia' => ['medical' => true],
        'care.palliative' => ['medical' => true], 'care.intensive' => ['medical' => true],
        'care.young_care' => ['medical' => true], 'care.gerontopsychiatric' => ['medical' => true], 'care.wachkoma' => ['medical' => true],
        'activities.events' => ['medical' => false], 'activities.excursions' => ['medical' => false],
        'activities.gymnastics' => ['medical' => false], 'activities.memory_training' => ['medical' => false],
        'activities.music' => ['medical' => false], 'activities.creative' => ['medical' => false], 'activities.religious_services' => ['medical' => false],
        'safety.emergency_call' => ['medical' => false], 'contact.contact_form' => ['medical' => false], 'contact.availability_24h' => ['medical' => false], 'contact.visiting_hours' => ['medical' => false], 'living.pets_allowed' => ['medical' => false],
        'media.youtube' => ['medical' => false], 'media.gallery' => ['medical' => false],
        'media.virtual_tour' => ['medical' => false],
    ];

    public static function known(string $key): bool
    {
        return isset(self::ATTRIBUTES[$key]);
    }

    public static function medical(string $key): bool
    {
        return self::ATTRIBUTES[$key]['medical'] ?? false;
    }

    public static function keys(): array
    {
        return array_keys(self::ATTRIBUTES);
    }
}

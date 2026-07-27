<?php

namespace Tests\Unit;

use App\Models\City;
use App\Models\Facility;
use App\Services\QualityScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QualityScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_the_documented_score_and_label(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam']);
        $facility = Facility::create([
            'source_id' => 'quality-1',
            'name' => 'Qualitätszentrum',
            'slug' => 'qualitaetszentrum',
            'type' => 'Ambulante Pflege',
            'city_id' => $city->id,
            'address' => 'Musterstraße 1',
            'postal_code' => '14467',
            'phone' => '+49 331 123456',
            'email' => 'kontakt@example.de',
            'website' => 'https://example.de',
            'contact_source' => 'https://example.de/kontakt',
            'contact_status' => 'verified',
            'contact_checked_at' => now(),
        ]);

        $score = app(QualityScoreService::class)->evaluate($facility);

        $this->assertSame(100, $score['score']);
        $this->assertSame('Sehr hoch', $score['quality_label']);
        $this->assertSame('green', $score['quality_color']);
        $this->assertSame(100, $score['progress_percentage']);
    }

    public function test_it_aggregates_scores_without_reimplementing_the_rules(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam']);
        $facilities = collect([
            Facility::create(['source_id' => 'quality-1', 'city_id' => $city->id, 'name' => 'Eins', 'slug' => 'eins', 'type' => 'Pflege', 'address' => 'Straße 1', 'postal_code' => '14467', 'contact_status' => 'verified']),
            Facility::create(['source_id' => 'quality-2', 'city_id' => $city->id, 'name' => 'Zwei', 'slug' => 'zwei', 'type' => 'Pflege', 'address' => 'Straße 2', 'postal_code' => '14467']),
        ]);

        $aggregate = app(QualityScoreService::class)->aggregate($facilities);

        $this->assertSame(2, $aggregate['total_facilities']);
        $this->assertSame(1, $aggregate['verified_count']);
        $this->assertSame(1, $aggregate['unverified_count']);
        $this->assertSame(50.0, $aggregate['verified_percentage']);
    }
}

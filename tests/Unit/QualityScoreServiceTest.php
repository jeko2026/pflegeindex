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
            Facility::create(['source_id' => 'quality-1', 'city_id' => $city->id, 'name' => 'Eins', 'slug' => 'eins', 'type' => 'Pflege', 'address' => 'Straße 1', 'postal_code' => '14467', 'phone' => '+49 331 123456', 'contact_source' => 'https://example.de/kontakt', 'contact_status' => 'verified', 'contact_checked_at' => now()]),
            Facility::create(['source_id' => 'quality-2', 'city_id' => $city->id, 'name' => 'Zwei', 'slug' => 'zwei', 'type' => 'Pflege', 'address' => 'Straße 2', 'postal_code' => '14467']),
        ]);

        $aggregate = app(QualityScoreService::class)->aggregate($facilities);

        $this->assertSame(2, $aggregate['total_facilities']);
        $this->assertSame(1, $aggregate['verified_count']);
        $this->assertSame(1, $aggregate['unverified_count']);
        $this->assertSame(50.0, $aggregate['verified_percentage']);
    }

    public function test_officially_absent_contacts_do_not_receive_quality_score_points(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam']);
        $facility = Facility::create([
            'source_id' => 'quality-officially-absent',
            'city_id' => $city->id,
            'name' => 'Ohne öffentliche Kontakte',
            'slug' => 'ohne-oeffentliche-kontakte',
            'type' => 'Pflege',
            'address' => 'Straße 1',
            'postal_code' => '14467',
            'official_email_absent' => true,
            'official_website_absent' => true,
        ]);

        $score = app(QualityScoreService::class)->evaluate($facility);

        $this->assertSame(0, $score['score']);
        $this->assertFalse($score['criteria']['email']);
        $this->assertFalse($score['criteria']['website']);
        $this->assertSame('officially_absent', $score['field_statuses']['email']['status']);
        $this->assertSame('officially_absent', $score['field_statuses']['website']['status']);
    }

    public function test_verified_status_alone_is_not_a_documented_review(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam']);
        $facility = Facility::create([
            'source_id' => 'quality-undocumented-review',
            'city_id' => $city->id,
            'name' => 'Nicht dokumentierte Prüfung',
            'slug' => 'nicht-dokumentierte-pruefung',
            'type' => 'Pflege',
            'address' => 'Straße 1',
            'postal_code' => '14467',
            'contact_status' => 'verified',
        ]);

        $score = app(QualityScoreService::class)->evaluate($facility);

        $this->assertFalse($score['review_documented']);
        $this->assertSame('unverified', $score['trust']['status']);
    }

    public function test_verified_contact_without_a_valid_source_remains_partially_verified(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam']);
        $facility = Facility::create([
            'source_id' => 'quality-review-without-source',
            'city_id' => $city->id,
            'name' => 'Teilweise dokumentierte Prüfung',
            'slug' => 'teilweise-dokumentierte-pruefung',
            'type' => 'Pflege',
            'address' => 'Straße 1',
            'postal_code' => '14467',
            'phone' => '+49 331 123456',
            'contact_status' => 'verified',
            'contact_checked_at' => now(),
        ]);

        $score = app(QualityScoreService::class)->evaluate($facility);

        $this->assertFalse($score['review_documented']);
        $this->assertSame('partial', $score['trust']['status']);
    }
}

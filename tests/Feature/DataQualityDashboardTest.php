<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Models\User;
use App\Services\DataQualityDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DataQualityDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_denied_and_admin_can_open_dashboard(): void
    {
        $this->get(route('admin.data-quality'))->assertRedirect(route('login'));

        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::create(['name' => 'Cottbus', 'slug' => 'cottbus', 'state_slug' => 'brandenburg']);
        $this->facility($city, 'Alpha', 'verified');
        $this->facility($city, 'Beta', null);

        $this->actingAs($admin)->get(route('admin.data-quality'))
            ->assertOk()
            ->assertSee('Data Quality Dashboard')
            ->assertSee('Cottbus')
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Übersicht')
            ->assertSee('Geprüft')
            ->assertSee('Offen')
            ->assertSee('Fortschritt')
            ->assertSee('Offene Aufgaben')
            ->assertSee('Nächster Schritt')
            ->assertSee('Städte mit Verbesserungsbedarf')
            ->assertDontSee('Verified-Anteil');
    }

    public function test_filters_are_validated_and_preserved_in_the_url(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $this->facility($city, 'Ohne Telefon', null);

        $response = $this->actingAs($admin)->get(route('admin.data-quality', [
            'city' => $city->id,
            'missing_phone' => '1',
            'max_score' => '40',
            'status' => 'invalid',
        ]));

        $response->assertOk()->assertSee('name="missing_phone"', false)->assertSee('Ohne Telefon')
            ->assertSee('value="'.$city->id.'" selected', false);
    }

    public function test_dashboard_exposes_action_links_and_selects_the_largest_open_task(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $this->facility($city, 'Ohne Telefon', null);
        $emailMissing = $this->facility($city, 'Ohne E-Mail', null);
        $emailMissing->update(['phone' => '+4930123456']);
        $websiteMissing = $this->facility($city, 'Ohne Website', null);
        $websiteMissing->update(['phone' => '+4930123456', 'email' => 'kontakt@example.de']);

        $response = $this->actingAs($admin)->get(route('admin.data-quality'))->assertOk();

        $response
            ->assertSee('Telefon prüfen')
            ->assertSee('E-Mail prüfen')
            ->assertSee('Website prüfen')
            ->assertSee('missing=phone', false)
            ->assertSee('missing=email', false)
            ->assertSee('missing=website', false)
            ->assertSee('Prüfung starten')
            ->assertSee('status=unverified', false);
        $this->assertSame(1, substr_count($response->getContent(), 'E-Mail-Prüfung starten'));
    }

    public function test_city_priority_uses_verified_percentage_then_open_count(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $high = City::create(['name' => 'Alpha', 'slug' => 'alpha', 'state_slug' => 'brandenburg']);
        $medium = City::create(['name' => 'Beta', 'slug' => 'beta', 'state_slug' => 'brandenburg']);
        $low = City::create(['name' => 'Gamma', 'slug' => 'gamma', 'state_slug' => 'brandenburg']);

        foreach (['A1', 'A2', 'A3', 'A4'] as $name) {
            $this->facility($high, $name, null);
        }
        $this->facility($medium, 'B1', 'verified');
        $this->facility($medium, 'B2', null);
        $this->facility($medium, 'B3', null);
        foreach (['C1', 'C2', 'C3'] as $name) {
            $this->facility($low, $name, 'verified');
        }

        $content = $this->actingAs($admin)->get(route('admin.data-quality'))->assertOk()->getContent();
        $this->assertLessThan(strpos($content, '>Mittel<'), strpos($content, '>Hoch<'));
        $this->assertLessThan(strpos($content, '>Niedrig<'), strpos($content, '>Mittel<'));
    }

    public function test_dashboard_has_neutral_next_step_when_all_contact_fields_are_present(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $facility = $this->facility($city, 'Vollständig', 'verified');
        $facility->update([
            'phone' => '+4930123456',
            'email' => 'kontakt@example.de',
            'website' => 'https://example.de',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.data-quality'))->assertOk();

        $response
            ->assertSee('Für Telefon, E-Mail und Website sind aktuell keine offenen Aufgaben vorhanden.')
            ->assertDontSee('E-Mail-Prüfung starten');
    }

    public function test_unverified_facilities_with_partial_contact_data_have_a_positive_city_score(): void
    {
        $city = City::create(['name' => 'Teilweise', 'slug' => 'teilweise', 'state_slug' => 'brandenburg']);
        $phoneOnly = $this->facility($city, 'Telefon vorhanden', null);
        $phoneOnly->update(['phone' => '+4930123456']);
        $emailOnly = $this->facility($city, 'E-Mail vorhanden', null);
        $emailOnly->update(['email' => 'kontakt@example.de']);

        $dashboard = app(DataQualityDashboardService::class)->dashboard();
        $cityRow = collect($dashboard['cities'])->firstWhere('city_id', $city->id);

        $this->assertIsArray($cityRow);
        $this->assertSame(0, $cityRow['verified_count']);
        $this->assertSame(2, $cityRow['unverified_count']);
        $this->assertSame(17.5, $cityRow['average_score']);
        $this->assertGreaterThan(0, $cityRow['average_score']);
    }

    public function test_city_average_uses_each_facility_quality_score(): void
    {
        $city = City::create(['name' => 'Gemischt', 'slug' => 'gemischt', 'state_slug' => 'brandenburg']);
        $complete = $this->facility($city, 'Vollständig', 'verified');
        $complete->update([
            'phone' => '+4930123456',
            'email' => 'kontakt@example.de',
            'website' => 'https://example.de',
            'contact_source' => 'https://example.de/kontakt',
        ]);
        $phoneOnly = $this->facility($city, 'Nur Telefon', null);
        $phoneOnly->update(['phone' => '+4930654321']);

        $dashboard = app(DataQualityDashboardService::class)->dashboard();
        $cityRow = collect($dashboard['cities'])->firstWhere('city_id', $city->id);

        $this->assertIsArray($cityRow);
        $this->assertSame(1, $cityRow['verified_count']);
        $this->assertSame(1, $cityRow['unverified_count']);
        $this->assertSame(60.0, $cityRow['average_score']);
    }

    private function facility(City $city, string $name, ?string $status): Facility
    {
        return Facility::create([
            'source_id' => 'dashboard-'.strtolower(str_replace(' ', '-', $name)),
            'city_id' => $city->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)),
            'type' => 'Pflege',
            'address' => 'Straße 1',
            'postal_code' => '14467',
            'contact_status' => $status,
        ]);
    }
}

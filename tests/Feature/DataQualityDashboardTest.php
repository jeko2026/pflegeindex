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
            ->assertSee('Beta')
            ->assertDontSee('Alpha')
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
            'task' => 'missing_phone',
            'max_score' => '40',
            'status' => 'invalid',
        ]));

        $response->assertOk()->assertSee('name="task"', false)->assertSee('value="missing_phone" selected', false)
            ->assertSee('value="'.$city->id.'" selected', false)
            ->assertSee('<strong>1</strong> von <strong>1</strong> Einrichtungen angezeigt', false)
            ->assertSee('Filter zurücksetzen')
            ->assertSee('Quality Score bis (%)')
            ->assertSee('placeholder="z. B. 30"', false)
            ->assertSee('Einrichtungen prüfen')
            ->assertSee('— Potsdam')
            ->assertSee('← Alle Städte anzeigen')
            ->assertSee('Prüfen →');
    }

    public function test_default_task_contains_only_open_facilities_and_is_selected_in_dropdown(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $this->facility($city, 'Offen', null);
        $this->facility($city, 'In Prüfung', 'pending');
        $this->facility($city, 'Geprüft', 'verified');

        $response = $this->actingAs($admin)->get(route('admin.data-quality'))->assertOk();

        $response
            ->assertSee('value="open" selected', false)
            ->assertSee('Aktive Aufgabe')
            ->assertDontSee('Aufgabe:</span>', false)
            ->assertSee('Offen')
            ->assertDontSee('<td>In Prüfung</td>', false)
            ->assertDontSee('<td>Geprüft</td>', false)
            ->assertDontSee('type="checkbox" name="missing_', false)
            ->assertDontSee('type="radio" name="task"', false);
        $this->assertSame(1, substr_count($response->getContent(), '<select name="task">'));
        $this->assertSame(1, substr_count($response->getContent(), 'value="open" selected'));
    }

    public function test_missing_phone_task_contains_only_facilities_without_phone(): void
    {
        [$phoneMissing, $emailMissing] = $this->taskFacilities();

        $dashboard = app(DataQualityDashboardService::class)->dashboard(['task' => 'missing_phone']);

        $this->assertSame([$phoneMissing->id], collect($dashboard['facilities'])->pluck('id')->all());
        $this->assertNotContains($emailMissing->id, collect($dashboard['facilities'])->pluck('id')->all());
    }

    public function test_missing_email_task_contains_only_facilities_without_email(): void
    {
        [, $emailMissing] = $this->taskFacilities();

        $dashboard = app(DataQualityDashboardService::class)->dashboard(['task' => 'missing_email']);

        $this->assertSame([$emailMissing->id], collect($dashboard['facilities'])->pluck('id')->all());
    }

    public function test_missing_website_task_contains_only_facilities_without_website(): void
    {
        [, , $websiteMissing] = $this->taskFacilities();

        $dashboard = app(DataQualityDashboardService::class)->dashboard(['task' => 'missing_website']);

        $this->assertSame([$websiteMissing->id], collect($dashboard['facilities'])->pluck('id')->all());
    }

    public function test_additional_filters_are_applied_on_top_of_the_selected_task(): void
    {
        $potsdam = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $cottbus = City::create(['name' => 'Cottbus', 'slug' => 'cottbus', 'state_slug' => 'brandenburg']);
        $match = $this->facility($potsdam, 'Potsdam geprüft', 'verified');
        $match->update(['phone' => '+4930123456', 'website' => 'https://example.de']);
        $otherStatus = $this->facility($potsdam, 'Potsdam offen', null);
        $otherStatus->update(['phone' => '+4930123457', 'website' => 'https://example.de']);
        $otherCity = $this->facility($cottbus, 'Cottbus geprüft', 'verified');
        $otherCity->update(['phone' => '+4930123458', 'website' => 'https://example.de']);

        $dashboard = app(DataQualityDashboardService::class)->dashboard([
            'task' => 'missing_email',
            'city' => $potsdam->id,
            'status' => 'verified',
            'max_score' => 100,
        ]);

        $this->assertSame([$match->id], collect($dashboard['facilities'])->pluck('id')->all());
    }

    public function test_legacy_missing_parameter_maps_to_one_task(): void
    {
        $this->taskFacilities();

        $dashboard = app(DataQualityDashboardService::class)->dashboard(['missing_email' => '1']);

        $this->assertSame('missing_email', $dashboard['filters']['task']);
    }

    public function test_quality_score_sorting_defaults_to_ascending_and_uses_name_as_tie_breaker(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $phone = $this->facility($city, 'Zulu Telefon', null);
        $phone->update(['phone' => '+4930123456']);
        $this->facility($city, 'Beta Leer', null);
        $this->facility($city, 'Alpha Leer', null);

        $dashboard = app(DataQualityDashboardService::class)->dashboard();

        $this->assertSame(['Alpha Leer', 'Beta Leer', 'Zulu Telefon'], collect($dashboard['facilities'])->pluck('name')->all());
        $this->assertSame('quality_score', $dashboard['filters']['sort']);
        $this->assertSame('asc', $dashboard['filters']['direction']);
    }

    public function test_quality_score_descending_changes_the_order(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $phone = $this->facility($city, 'Telefon', null);
        $phone->update(['phone' => '+4930123456']);
        $this->facility($city, 'Leer', null);

        $dashboard = app(DataQualityDashboardService::class)->dashboard(['sort' => 'quality_score_desc']);

        $this->assertSame(['Telefon', 'Leer'], collect($dashboard['facilities'])->pluck('name')->all());
    }

    public function test_city_sorting_uses_city_then_facility_name(): void
    {
        $zossen = City::create(['name' => 'Zossen', 'slug' => 'zossen', 'state_slug' => 'brandenburg']);
        $cottbus = City::create(['name' => 'Cottbus', 'slug' => 'cottbus', 'state_slug' => 'brandenburg']);
        $this->facility($zossen, 'Alpha Zossen', null);
        $this->facility($cottbus, 'Zulu Cottbus', null);

        $dashboard = app(DataQualityDashboardService::class)->dashboard(['sort' => 'city', 'direction' => 'asc']);

        $this->assertSame(['Zulu Cottbus', 'Alpha Zossen'], collect($dashboard['facilities'])->pluck('name')->all());
    }

    public function test_updated_at_sorting_uses_newest_facility_first(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $older = $this->facility($city, 'Früher aktualisiert', null);
        $newer = $this->facility($city, 'Später aktualisiert', null);
        $older->timestamps = false;
        $older->updated_at = now()->subDay();
        $older->save();
        $newer->timestamps = false;
        $newer->updated_at = now();
        $newer->save();

        $dashboard = app(DataQualityDashboardService::class)->dashboard(['sort' => 'updated_at']);

        $this->assertSame([$newer->id, $older->id], collect($dashboard['facilities'])->pluck('id')->all());
        $this->assertSame('updated_at', $dashboard['filters']['sort']);
        $this->assertSame('desc', $dashboard['filters']['direction']);
    }

    public function test_invalid_sort_options_are_replaced_by_whitelisted_defaults(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $this->facility($city, 'Einrichtung', null);

        $dashboard = app(DataQualityDashboardService::class)->dashboard([
            'task' => 'anything from the query string',
            'sort' => 'facilities; drop table facilities',
            'direction' => 'sideways',
        ]);

        $this->assertSame('open', $dashboard['filters']['task']);
        $this->assertSame('quality_score', $dashboard['filters']['sort']);
        $this->assertSame('asc', $dashboard['filters']['direction']);
    }

    public function test_sort_links_preserve_active_filters_and_expose_accessible_state(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $this->facility($city, 'Ohne Telefon', null);

        $response = $this->actingAs($admin)->get(route('admin.data-quality', [
            'task' => 'missing_phone',
            'city' => $city->id,
            'status' => 'unverified',
        ]));

        $response->assertOk()
            ->assertSee('task=missing_phone&amp;city='.$city->id.'&amp;status=unverified', false)
            ->assertSee('status=unverified&amp;sort=city', false)
            ->assertSee('href="'.e(route('admin.data-quality', [
                'task' => 'missing_phone',
                'status' => 'unverified',
                'sort' => 'quality_score',
                'direction' => 'asc',
            ])).'"', false)
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('aria-label="Nach Datenqualität absteigend sortieren"', false);
    }

    public function test_summary_shows_removable_additional_filter_chips_and_sort_select(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $facility = $this->facility($city, 'Ohne E-Mail', 'verified');
        $facility->update(['phone' => '+4930123456', 'website' => 'https://example.de']);

        $response = $this->actingAs($admin)->get(route('admin.data-quality', [
            'task' => 'missing_email',
            'city' => $city->id,
            'status' => 'verified',
            'max_score' => 100,
        ]));

        $response->assertOk()
            ->assertSee('Stadt: Potsdam')
            ->assertSee('Status: Geprüft')
            ->assertSee('Score ≤ 100 %')
            ->assertSee('Aktive weitere Filter')
            ->assertSee('Filter Stadt: Potsdam entfernen')
            ->assertSee('id="queue-sort"', false)
            ->assertSee('onchange="this.form.submit()"', false)
            ->assertSee('Zuletzt aktualisiert');
    }

    public function test_result_summary_distinguishes_displayed_rows_from_filtered_total(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        foreach (range(1, 51) as $number) {
            $this->facility($city, 'Einrichtung '.$number, null);
        }

        $this->actingAs($admin)->get(route('admin.data-quality'))
            ->assertOk()
            ->assertSee('<strong>50</strong> von <strong>51</strong> Einrichtungen angezeigt', false);
    }

    public function test_result_summary_reports_when_no_facilities_match(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $facility = $this->facility($city, 'Mit Telefon', null);
        $facility->update(['phone' => '+4930123456']);

        $this->actingAs($admin)->get(route('admin.data-quality', ['task' => 'missing_phone']))
            ->assertOk()
            ->assertSee('<strong>0</strong> Einrichtungen gefunden', false);
    }

    public function test_quality_score_and_missing_data_have_accessible_visual_indicators(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $this->facility($city, 'Unvollständig', null);

        $response = $this->actingAs($admin)->get(route('admin.data-quality'))->assertOk();

        $response
            ->assertSee('role="progressbar"', false)
            ->assertSee('aria-valuemin="0"', false)
            ->assertSee('aria-valuemax="100"', false)
            ->assertSee('aria-valuenow="0"', false)
            ->assertSee('admin-missing-chip--phone', false)
            ->assertSee('admin-missing-chip--email', false)
            ->assertSee('admin-missing-chip--website', false)
            ->assertSee('<details class="admin-missing-more">', false)
            ->assertSee('+3 weitere')
            ->assertSee('admin-missing-chip--source', false)
            ->assertSee('admin-missing-chip--address', false)
            ->assertSee('admin-missing-chip--verified', false);
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
        $this->assertSame(1, substr_count($response->getContent(), 'Website-Prüfung starten'));
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

    /** @return array{Facility, Facility, Facility, Facility} */
    private function taskFacilities(): array
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam', 'state_slug' => 'brandenburg']);
        $phoneMissing = $this->facility($city, 'Telefon fehlt', null);
        $phoneMissing->update(['email' => 'telefon@example.de', 'website' => 'https://telefon.example.de']);
        $emailMissing = $this->facility($city, 'E-Mail fehlt', null);
        $emailMissing->update(['phone' => '+4930123456', 'website' => 'https://email.example.de']);
        $websiteMissing = $this->facility($city, 'Website fehlt', null);
        $websiteMissing->update(['phone' => '+4930123457', 'email' => 'website@example.de']);
        $complete = $this->facility($city, 'Vollständig', null);
        $complete->update(['phone' => '+4930123458', 'email' => 'vollstaendig@example.de', 'website' => 'https://vollstaendig.example.de']);

        return [$phoneMissing, $emailMissing, $websiteMissing, $complete];
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

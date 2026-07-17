<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\ContactSuggestion;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_administrator_can_log_in(): void
    {
        $admin = User::factory()->create([
            'email' => 'info@pflegeindex.com',
            'password' => 'very-secure-password',
            'is_admin' => true,
        ]);

        $this->post(route('admin.login'), [
            'email' => $admin->email,
            'password' => 'very-secure-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admin_user_cannot_open_the_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_administrator_can_update_and_lock_facility_contacts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $facility = $this->createFacility();

        $this->actingAs($admin)
            ->put(route('admin.facilities.update', $facility), [
                'description' => 'Individuelle Beschreibung der Einrichtung.',
                'phone' => '+493311234567',
                'email' => 'kontakt@example.de',
                'website' => 'https://example.de',
                'contact_source' => 'https://example.de/kontakt',
                'contact_status' => 'verified',
                'contact_locked' => true,
            ])
            ->assertRedirect(route('admin.facilities.edit', $facility));

        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'description' => 'Individuelle Beschreibung der Einrichtung.',
            'phone' => '+493311234567',
            'contact_status' => 'verified',
            'contact_locked' => true,
        ]);
    }

    public function test_administrator_can_open_contact_review_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $facility = $this->createFacility();
        ContactSuggestion::create([
            'facility_id' => $facility->id,
            'fingerprint' => str_repeat('a', 64),
            'parser_status' => 'not_found',
            'decision' => 'pending',
            'checked_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.suggestions.index'))
            ->assertOk()
            ->assertSee('Website suchen');
    }

    public function test_verified_status_requires_at_least_one_contact(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $facility = $this->createFacility();

        $this->actingAs($admin)
            ->from(route('admin.facilities.edit', $facility))
            ->put(route('admin.facilities.update', $facility), [
                'phone' => '',
                'email' => '',
                'website' => '',
                'contact_source' => '',
                'contact_status' => 'verified',
                'contact_locked' => true,
            ])
            ->assertRedirect(route('admin.facilities.edit', $facility))
            ->assertSessionHasErrors('contact_status');

        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'contact_status' => null,
        ]);
    }

    public function test_manual_contact_edit_returns_to_pending_suggestion(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $facility = $this->createFacility();
        $suggestion = ContactSuggestion::create([
            'facility_id' => $facility->id,
            'fingerprint' => str_repeat('b', 64),
            'parser_status' => 'not_found',
            'decision' => 'pending',
            'checked_at' => now(),
            'raw_payload' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.facilities.edit', [
                'facility' => $facility,
                'suggestion' => $suggestion->id,
            ]))
            ->assertOk()
            ->assertSee('Zur Kontaktprüfung');

        $this->actingAs($admin)
            ->put(route('admin.facilities.update', $facility), [
                'phone' => '+493311234567',
                'email' => 'KONTAKT@EXAMPLE.DE',
                'website' => 'https://example.de',
                'contact_source' => 'https://example.de/kontakt',
                'contact_status' => 'verified',
                'contact_locked' => true,
                'suggestion_id' => $suggestion->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.suggestions.show', $suggestion));

        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'phone' => '+493311234567',
            'email' => 'kontakt@example.de',
            'contact_status' => 'verified',
            'contact_locked' => true,
        ]);
        $this->assertDatabaseHas('contact_suggestions', [
            'id' => $suggestion->id,
            'decision' => 'pending',
        ]);
    }

    public function test_administrator_can_change_their_password(): void
    {
        $admin = User::factory()->create([
            'password' => 'old-secure-password',
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.password.update'), [
                'current_password' => 'old-secure-password',
                'password' => 'New-secure-password-2026',
                'password_confirmation' => 'New-secure-password-2026',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('New-secure-password-2026', $admin->fresh()->password));
    }

    public function test_import_preserves_locked_contact_changes(): void
    {
        $facility = $this->createFacility();
        $facility->update([
            'description' => 'Manuell geprüfte Beschreibung.',
            'phone' => '+493319999999',
            'contact_status' => 'verified',
            'contact_locked' => true,
        ]);
        $path = storage_path('framework/testing/locked-contact-import.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([[
            'id' => $facility->source_id,
            'name' => $facility->name,
            'city' => $facility->city->name,
            'citySlug' => $facility->city->slug,
            'slug' => $facility->slug,
            'postalCode' => $facility->postal_code,
            'address' => $facility->address,
            'type' => $facility->type,
            'description' => 'Beschreibung aus dem Import.',
            'phone' => '+493310000000',
            'contactStatus' => 'verified',
            'careTypes' => ['Ambulante Pflege'],
            'features' => [],
        ]], JSON_THROW_ON_ERROR));

        try {
            $this->artisan('pflegeindex:import', ['path' => $path])
                ->assertSuccessful();
        } finally {
            File::delete($path);
        }

        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'description' => 'Manuell geprüfte Beschreibung.',
            'phone' => '+493319999999',
            'contact_locked' => true,
        ]);
    }

    public function test_administrator_can_filter_facilities_by_available_contact_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $complete = $this->createFacility();
        $complete->update([
            'name' => 'Vollständiger Kontakt',
            'phone' => '+493311111111',
            'email' => 'vollstaendig@example.de',
            'website' => 'https://example.de',
            'contact_source' => 'https://example.de/kontakt',
            'contact_status' => 'verified',
        ]);

        $missingEmail = $complete->replicate();
        $missingEmail->fill([
            'source_id' => 'missing-email-14467',
            'name' => 'Einrichtung ohne E-Mail',
            'slug' => 'einrichtung-ohne-email-14467',
            'email' => null,
        ])->save();

        $missingWebsite = $complete->replicate();
        $missingWebsite->fill([
            'source_id' => 'missing-website-14467',
            'name' => 'Einrichtung ohne Website',
            'slug' => 'einrichtung-ohne-website-14467',
            'website' => null,
        ])->save();

        $missingPhone = $complete->replicate();
        $missingPhone->fill([
            'source_id' => 'missing-phone-14467',
            'name' => 'Einrichtung ohne Telefon',
            'slug' => 'einrichtung-ohne-telefon-14467',
            'phone' => null,
        ])->save();

        $missingSource = $complete->replicate();
        $missingSource->fill([
            'source_id' => 'missing-source-14467',
            'name' => 'Einrichtung ohne Kontaktquelle',
            'slug' => 'einrichtung-ohne-kontaktquelle-14467',
            'contact_source' => null,
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.facilities.index', ['email' => 'without']))
            ->assertOk()
            ->assertSee('Einrichtung ohne E-Mail')
            ->assertDontSee('Vollständiger Kontakt');

        $this->actingAs($admin)
            ->get(route('admin.facilities.index', ['website' => 'without']))
            ->assertOk()
            ->assertSee('Einrichtung ohne Website')
            ->assertDontSee('Vollständiger Kontakt');

        $this->actingAs($admin)
            ->get(route('admin.facilities.index', ['phone' => 'without']))
            ->assertOk()
            ->assertSee('Einrichtung ohne Telefon')
            ->assertDontSee('Vollständiger Kontakt');

        $this->actingAs($admin)
            ->get(route('admin.facilities.index', ['source' => 'without']))
            ->assertOk()
            ->assertSee('Einrichtung ohne Kontaktquelle')
            ->assertDontSee('Vollständiger Kontakt');
    }

    private function createFacility(): Facility
    {
        $city = City::create([
            'name' => 'Potsdam',
            'slug' => 'potsdam',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);

        return Facility::create([
            'source_id' => 'admin-example-14467',
            'city_id' => $city->id,
            'name' => 'Admin Pflegezentrum',
            'slug' => 'admin-pflegezentrum-14467',
            'postal_code' => '14467',
            'address' => 'Musterstraße 1',
            'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'],
            'features' => [],
        ]);
    }
}

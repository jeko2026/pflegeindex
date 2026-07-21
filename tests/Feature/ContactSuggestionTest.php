<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\ContactSuggestion;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ContactSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_results_are_imported_idempotently(): void
    {
        $facility = $this->createFacility([
            'phone' => '+493311234567',
            'website' => 'https://example.de',
            'contact_status' => 'verified',
        ]);
        $path = $this->writeParserResult($facility, [
            'status' => 'verified',
            'phone' => '+493311234567',
            'website' => 'https://example.de',
            'phoneSource' => 'https://example.de/kontakt',
            'confidence' => 100,
        ]);

        try {
            $this->artisan('pflegeindex:import-suggestions', ['path' => $path])->assertSuccessful();
            $this->artisan('pflegeindex:import-suggestions', ['path' => $path])->assertSuccessful();
        } finally {
            File::delete($path);
        }

        $this->assertDatabaseCount('contact_suggestions', 1);
        $this->assertDatabaseHas('contact_suggestions', [
            'facility_id' => $facility->id,
            'decision' => 'accepted',
        ]);
    }

    public function test_administrator_can_upload_parser_results_in_the_browser(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $facility = $this->createFacility();
        $payload = json_encode([
            'city' => 'Potsdam',
            'results' => [[
                'facilityId' => $facility->source_id,
                'name' => $facility->name,
                'city' => 'Potsdam',
                'status' => 'verified',
                'phone' => '+493319999999',
                'phoneSource' => 'https://example.de/kontakt',
                'confidence' => 100,
                'checkedAt' => now()->toAtomString(),
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->actingAs($admin)
            ->post(route('admin.suggestions.upload'), [
                'results_file' => UploadedFile::fake()->createWithContent('potsdam.json', $payload),
            ])
            ->assertRedirect()
            ->assertSessionHas('import_summary');

        $this->assertDatabaseHas('contact_suggestions', [
            'facility_id' => $facility->id,
            'phone' => '+493319999999',
            'decision' => 'pending',
        ]);
    }

    public function test_parser_import_discards_invalid_normalized_urls_but_retains_raw_payload(): void
    {
        $facility = $this->createFacility();
        $path = $this->writeParserResult($facility, [
            'status' => 'verified',
            'phone' => '+493319999999',
            'website' => 'javascript:alert(1)',
            'phoneSource' => ' https://example.de/kontakt ',
            'emailSource' => ['https://example.de/email'],
            'pagesChecked' => ['https://example.de/kontakt', 'data:text/html,unsafe'],
        ]);

        try {
            $this->artisan('pflegeindex:import-suggestions', ['path' => $path])
                ->expectsOutputToContain('Rejected URLs')
                ->assertSuccessful();
        } finally {
            File::delete($path);
        }

        $suggestion = ContactSuggestion::query()->firstOrFail();
        $this->assertNull($suggestion->website);
        $this->assertSame('https://example.de/kontakt', $suggestion->phone_source);
        $this->assertNull($suggestion->email_source);
        $this->assertSame('javascript:alert(1)', $suggestion->raw_payload['website']);
        $this->assertSame(['https://example.de/kontakt'], $suggestion->safePagesChecked());
    }

    public function test_administrator_can_accept_a_verified_contact(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $facility = $this->createFacility();
        $suggestion = $this->createSuggestion($facility);

        $this->actingAs($admin)
            ->post(route('admin.suggestions.accept', $suggestion))
            ->assertRedirect(route('admin.suggestions.index'));

        $this->assertDatabaseHas('facilities', [
            'id' => $facility->id,
            'phone' => '+493319999999',
            'email' => 'kontakt@example.de',
            'contact_status' => 'verified',
            'contact_locked' => true,
        ]);
        $this->assertDatabaseHas('contact_suggestions', [
            'id' => $suggestion->id,
            'decision' => 'accepted',
            'reviewed_by' => $admin->id,
        ]);
    }

    public function test_invalid_historical_suggestion_cannot_be_accepted_or_partially_saved(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $facility = $this->createFacility();
        $suggestion = $this->createSuggestion($facility);
        $suggestion->update(['website' => 'file:///etc/passwd']);

        $this->actingAs($admin)
            ->post(route('admin.suggestions.accept', $suggestion))
            ->assertSessionHasErrors('suggestion');

        $this->assertNull($facility->fresh()->phone);
        $this->assertSame('pending', $suggestion->fresh()->decision);
        $this->assertNull($suggestion->reviewed_by);
    }

    public function test_rejected_contact_does_not_change_the_facility(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $facility = $this->createFacility();
        $suggestion = $this->createSuggestion($facility);

        $this->actingAs($admin)
            ->post(route('admin.suggestions.reject', $suggestion))
            ->assertRedirect(route('admin.suggestions.index'));

        $this->assertNull($facility->fresh()->phone);
        $this->assertDatabaseHas('contact_suggestions', [
            'id' => $suggestion->id,
            'decision' => 'rejected',
            'reviewed_by' => $admin->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createFacility(array $attributes = []): Facility
    {
        $city = City::create([
            'name' => 'Potsdam',
            'slug' => 'potsdam',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);

        return Facility::create(array_merge([
            'source_id' => 'suggestion-example-14467',
            'city_id' => $city->id,
            'name' => 'Vorschlag Pflegezentrum',
            'slug' => 'vorschlag-pflegezentrum-14467',
            'postal_code' => '14467',
            'address' => 'Musterstraße 1',
            'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'],
            'features' => [],
        ], $attributes));
    }

    private function createSuggestion(Facility $facility): ContactSuggestion
    {
        return ContactSuggestion::create([
            'facility_id' => $facility->id,
            'fingerprint' => str_repeat('a', 64),
            'parser_status' => 'verified',
            'phone' => '+493319999999',
            'email' => 'kontakt@example.de',
            'website' => 'https://example.de',
            'phone_source' => 'https://example.de/kontakt',
            'confidence' => 100,
            'checked_at' => now(),
            'decision' => 'pending',
            'raw_payload' => [],
        ]);
    }

    /** @param array<string, mixed> $result */
    private function writeParserResult(Facility $facility, array $result): string
    {
        $path = storage_path('framework/testing/contact-suggestion.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'city' => $facility->city->name,
            'updatedAt' => now()->toAtomString(),
            'results' => [[
                'facilityId' => $facility->source_id,
                'name' => $facility->name,
                'city' => $facility->city->name,
                'checkedAt' => now()->toAtomString(),
                ...$result,
            ]],
        ], JSON_THROW_ON_ERROR));

        return $path;
    }
}

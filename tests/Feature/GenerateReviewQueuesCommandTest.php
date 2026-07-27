<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class GenerateReviewQueuesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_specialized_sorted_queues_without_database_changes(): void
    {
        $city = City::create(['name' => 'Potsdam', 'slug' => 'potsdam']);
        $defaults = ['city_id' => $city->id, 'type' => 'Ambulante Pflege', 'postal_code' => '14467', 'address' => 'Teststraße 1', 'street' => 'Teststraße', 'house_number' => '1', 'slug' => 'alpha'];
        $first = Facility::create(array_merge($defaults, ['source_id' => 'queue-1', 'name' => 'Alpha', 'email' => null, 'website' => null, 'phone' => null]));
        Facility::create(array_merge($defaults, ['source_id' => 'queue-2', 'slug' => 'beta', 'name' => 'Beta', 'email' => 'beta@example.org', 'website' => 'https://beta.example.org', 'phone' => '+49 331 123456']));
        Facility::create(array_merge($defaults, ['source_id' => 'queue-3', 'slug' => 'gamma', 'name' => 'Gamma', 'email' => 'gamma@example.org', 'website' => null, 'phone' => '+49 331 654321', 'official_website_absent' => true]));
        $before = Facility::query()->orderBy('id')->get()->toJson();

        $this->artisan('data-quality:generate-review-queues')->assertExitCode(0);

        $directory = storage_path('app/data-quality/review-queues');
        foreach (['missing-email.csv', 'missing-website.csv', 'missing-phone.csv', 'address-review.csv', 'name-review.csv', 'type-review.csv', 'queue-summary.json', 'README.md'] as $file) {
            $this->assertFileExists($directory.'/'.$file);
        }
        $this->assertStringContainsString('facility_id,name,type,city,postcode,address,phone,website,reviewed_email,reviewed_source_url,review_result', File::get($directory.'/missing-email.csv'));
        $summary = json_decode(File::get($directory.'/queue-summary.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $summary['missing_email']);
        $this->assertSame(1, $summary['missing_website']);
        $this->assertSame(1, $summary['missing_phone']);
        $this->assertSame($before, Facility::query()->orderBy('id')->get()->toJson());
        $this->assertNotEmpty($first->fresh());
    }
}

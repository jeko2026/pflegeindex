<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use App\Models\User;
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
            ->assertSee('Verified-Anteil');
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

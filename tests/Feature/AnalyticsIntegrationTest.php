<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_scripts_are_absent_without_configuration(): void
    {
        config([
            'services.analytics.ga4_measurement_id' => null,
            'services.analytics.clarity_project_id' => null,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('www.googletagmanager.com', false)
            ->assertDontSee('www.clarity.ms', false)
            ->assertDontSee('window.dataLayer', false);
    }

    public function test_analytics_scripts_are_rendered_with_configuration(): void
    {
        config([
            'services.analytics.ga4_measurement_id' => 'G-TEST123456',
            'services.analytics.clarity_project_id' => 'clarity123',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123456', false)
            ->assertSee("gtag('config', \"G-TEST123456\");", false)
            ->assertSee('https://www.clarity.ms/tag/', false)
            ->assertSee('"clarity123"', false);
    }
}

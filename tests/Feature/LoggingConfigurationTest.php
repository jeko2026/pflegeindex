<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoggingConfigurationTest extends TestCase
{
    public function test_daily_channel_has_thirty_day_retention_without_changing_the_log_format(): void
    {
        $daily = config('logging.channels.daily');

        $this->assertSame('daily', $daily['driver']);
        $this->assertSame(storage_path('logs/laravel.log'), $daily['path']);
        $this->assertSame(30, $daily['days']);
        $this->assertTrue($daily['replace_placeholders']);
        $this->assertArrayNotHasKey('formatter', $daily);
    }

    public function test_production_templates_select_the_daily_channel(): void
    {
        foreach ([base_path('.env.production.example'), base_path('deployment/.env.production.template')] as $path) {
            $template = file_get_contents($path);

            $this->assertIsString($template);
            $this->assertMatchesRegularExpression('/^LOG_CHANNEL=stack$/m', $template);
            $this->assertMatchesRegularExpression('/^LOG_STACK=daily$/m', $template);
            $this->assertMatchesRegularExpression('/^LOG_DAILY_DAYS=30$/m', $template);
            $this->assertMatchesRegularExpression('/^LOG_LEVEL=warning$/m', $template);
        }
    }
}

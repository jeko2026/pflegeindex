<?php

namespace Tests\Feature;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_check_is_a_minimal_sessionless_liveness_response(): void
    {
        DB::listen(static function (): never {
            throw new LogicException('The health check must not query the database.');
        });

        $response = $this->get('/up');

        $response
            ->assertOk()
            ->assertContent('OK')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeaderMissing('Set-Cookie');

        $body = $response->getContent();

        foreach (['<html', '<script', '<link', '<style', '<img', 'fonts.bunny.net', 'cdn.jsdelivr.net', 'Laravel', 'PHP', base_path()] as $unexpected) {
            $this->assertStringNotContainsString($unexpected, $body);
        }
    }

    public function test_health_check_supports_head_and_rejects_post(): void
    {
        $this->call('HEAD', '/up')
            ->assertOk()
            ->assertContent('')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeaderMissing('Set-Cookie');

        $this->post('/up')->assertMethodNotAllowed();
    }

    public function test_health_failure_returns_no_technical_details(): void
    {
        Event::listen(DiagnosingHealth::class, static function (): never {
            throw new RuntimeException('Sensitive health-check detail.');
        });

        $this->get('/up')
            ->assertStatus(500)
            ->assertContent('ERROR')
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeaderMissing('Set-Cookie')
            ->assertDontSee('Sensitive health-check detail.');
    }
}

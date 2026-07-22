<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_layout_uses_versioned_assets_and_sized_footer_logo(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.asset('assets/styles.css').'?v=20260722-1"', false)
            ->assertSee('src="'.asset('assets/app.js').'?v=20260722-1"', false)
            ->assertSee('src="'.asset('logo-light.svg').'" alt="PflegeIndex" width="476" height="104"', false);
    }

    public function test_public_styles_provide_global_focus_and_wcag_aa_type_badge_contrast(): void
    {
        $stylesheet = file_get_contents(public_path('assets/styles.css'));

        $this->assertIsString($stylesheet);
        $this->assertStringContainsString(
            ':where(a, button, input, select, textarea, summary):focus-visible',
            $stylesheet,
        );
        $this->assertStringContainsString('outline: 3px solid #fff;', $stylesheet);
        $this->assertStringContainsString('box-shadow: 0 0 0 6px var(--navy);', $stylesheet);
        $this->assertStringContainsString('.type-badge { color: #276f2e;', $stylesheet);
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio('#276f2e', '#edf7ef'));
    }

    public function test_apache_and_nginx_static_asset_cache_policies_are_present(): void
    {
        foreach ([public_path('.htaccess'), base_path('deployment/public.production.htaccess')] as $path) {
            $configuration = file_get_contents($path);

            $this->assertIsString($configuration);
            $this->assertStringContainsString('max-age=31536000, immutable', $configuration);
            $this->assertStringContainsString('max-age=2592000', $configuration);
        }

        $nginxConfiguration = file_get_contents(base_path('deployment/nginx-static-assets.conf'));

        $this->assertIsString($nginxConfiguration);
        $this->assertStringContainsString('location ~* \.(?:css|js)$', $nginxConfiguration);
        $this->assertStringContainsString('location ~* \.(?:svg|png)$', $nginxConfiguration);
        $this->assertStringContainsString('try_files $uri =404;', $nginxConfiguration);
    }

    private function contrastRatio(string $foreground, string $background): float
    {
        $lighter = max($this->relativeLuminance($foreground), $this->relativeLuminance($background));
        $darker = min($this->relativeLuminance($foreground), $this->relativeLuminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $channels = array_map(
            static fn (string $channel): float => hexdec($channel) / 255,
            str_split(ltrim($hex, '#'), 2),
        );
        $channels = array_map(
            static fn (float $channel): float => $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_legal_routes_exist_and_are_available_to_guests(): void
    {
        $pages = [
            'pages.imprint' => ['/impressum.html', 'Impressum – PflegeIndex', 'Impressum von PflegeIndex.com.'],
            'pages.privacy' => ['/datenschutz.html', 'Datenschutz – PflegeIndex', 'Datenschutzerklärung von PflegeIndex.com.'],
            'pages.about' => ['/ueber-das-projekt.html', 'Über das Projekt – PflegeIndex', 'Informationen über das unabhängige Informationsverzeichnis PflegeIndex.'],
        ];

        foreach ($pages as $routeName => [$path, $title, $description]) {
            $this->assertTrue(Route::has($routeName));

            $response = $this->get('https://pflegeindex.com'.$path);

            $response
                ->assertOk()
                ->assertSee('<title>'.$title.'</title>', false)
                ->assertSee('<meta name="description" content="'.$description.'">', false)
                ->assertSee('<link rel="canonical" href="https://pflegeindex.com'.$path.'">', false);
        }
    }

    public function test_imprint_contains_required_operator_blocks_without_repeating_personal_data_in_the_test(): void
    {
        $this->get('https://pflegeindex.com/impressum.html')
            ->assertOk()
            ->assertSee('Angaben gemäß § 5 DDG')
            ->assertSee('Inhaber:')
            ->assertSee('Kontakt')
            ->assertSee('Verantwortlich für den Inhalt')
            ->assertSee('Art des Angebots')
            ->assertSee('info@pflegeindex.com')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    public function test_privacy_page_matches_the_current_technical_features(): void
    {
        $this->get('https://pflegeindex.com/datenschutz.html')
            ->assertOk()
            ->assertSee('Verantwortlicher:')
            ->assertSee('Hosting')
            ->assertSee('Server-Logfiles und Anwendungsprotokolle')
            ->assertSee('Rechtsgrundlagen')
            ->assertSee('Suche und Filter')
            ->assertSee('als URL-Parameter')
            ->assertSee('Kontaktaufnahme per E-Mail')
            ->assertSee('Cookies und Sitzungen')
            ->assertSee('pflegeindex-session')
            ->assertSee('XSRF-TOKEN')
            ->assertSee('Analyse-, Marketing- oder Third-Party-Cookies')
            ->assertSee('Rechte der betroffenen Personen')
            ->assertSee('Beschwerderecht')
            ->assertSee('SSL-/TLS-Verschlüsselung')
            ->assertSee('Änderungen dieser Datenschutzerklärung')
            ->assertDontSee('Bunny Fonts')
            ->assertDontSee('jsDelivr')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    public function test_public_pages_are_sessionless_and_admin_cookies_match_the_documented_configuration(): void
    {
        config([
            'session.driver' => 'array',
            'session.cookie' => 'pflegeindex-session',
            'session.lifetime' => 120,
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        $this->get('https://pflegeindex.com/datenschutz.html')
            ->assertOk()
            ->assertHeaderMissing('Set-Cookie');

        $cookies = collect($this->get('https://pflegeindex.com/admin/login')->headers->getCookies());
        $sessionCookie = $cookies->firstWhere(fn ($cookie): bool => $cookie->getName() === 'pflegeindex-session');
        $csrfCookie = $cookies->firstWhere(fn ($cookie): bool => $cookie->getName() === 'XSRF-TOKEN');

        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isSecure());
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertSame('lax', $sessionCookie->getSameSite());
        $this->assertNotNull($csrfCookie);
        $this->assertTrue($csrfCookie->isSecure());
        $this->assertFalse($csrfCookie->isHttpOnly());
        $this->assertSame('lax', $csrfCookie->getSameSite());
    }

    public function test_project_page_describes_scope_and_limitations(): void
    {
        $this->get('https://pflegeindex.com/ueber-das-projekt.html')
            ->assertOk()
            ->assertSee('Über das Projekt')
            ->assertSee('unabhängiges Informationsverzeichnis')
            ->assertSee('keine kommerziellen Ziele')
            ->assertSee('kein amtliches Register')
            ->assertSee('unvollständig oder veraltet')
            ->assertSee('direkt bei der jeweiligen Einrichtung')
            ->assertSee('keine Qualitätsbewertung')
            ->assertDontSee('<meta name="robots" content="noindex', false);
    }

    public function test_footer_links_to_all_legal_pages(): void
    {
        $this->get('https://pflegeindex.com/impressum.html')
            ->assertOk()
            ->assertSee(route('pages.imprint'), false)
            ->assertSee(route('pages.privacy'), false)
            ->assertSee(route('pages.about'), false)
            ->assertSee('Über das Projekt');
    }

    public function test_legal_pages_do_not_contain_known_placeholders_or_old_claims(): void
    {
        $forbiddenText = [
            'Entwurf',
            'TODO',
            'Name ergänzen',
            'Anschrift ergänzen',
            'Hosting-Anbieter ergänzen',
            '[Vor- und Nachname',
            '[Straße und Hausnummer',
            '[Postleitzahl und Ort',
            'garantiert vollständig',
            'offizielles staatliches Register',
            'manuell geprüft',
        ];

        foreach (['/impressum.html', '/datenschutz.html', '/ueber-das-projekt.html'] as $path) {
            $response = $this->get('https://pflegeindex.com'.$path)->assertOk();

            foreach ($forbiddenText as $text) {
                $response->assertDontSee($text);
            }
        }
    }

    public function test_old_about_url_redirects_permanently(): void
    {
        $this->get('/ueber-uns.html')
            ->assertRedirect('/ueber-das-projekt.html')
            ->assertStatus(301);
    }
}

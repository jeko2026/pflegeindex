<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertHeaderMissing('Set-Cookie');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_public_domain_variants_redirect_to_the_canonical_https_domain(): void
    {
        $this->get('http://pflegeindex.com/pflegeheime.html?q=Potsdam')
            ->assertRedirect('https://pflegeindex.com/pflegeheime.html?q=Potsdam')
            ->assertStatus(301);

        $this->get('http://www.pflegeindex.com/pflegeheime.html?q=Potsdam')
            ->assertRedirect('https://pflegeindex.com/pflegeheime.html?q=Potsdam')
            ->assertStatus(301);

        $this->get('https://www.pflegeindex.com/pflegeheime.html?q=Potsdam')
            ->assertRedirect('https://pflegeindex.com/pflegeheime.html?q=Potsdam')
            ->assertStatus(301);

        $this->get('https://pflegeindex.com/')
            ->assertOk()
            ->assertHeaderMissing('Location');
    }

    public function test_health_endpoint_is_protected_from_indexing(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_corrected_facility_urls_redirect_permanently(): void
    {
        $this->get('/pflegeeinrichtungen/brandenburg/beeskow/medi-care-gmbh-haus-barbara-15485')
            ->assertRedirect('/pflegeeinrichtungen/brandenburg/beeskow/medi-care-gmbh-haus-barbara-15848')
            ->assertStatus(301);

        $this->get('/pflegeeinrichtungen/brandenburg/brandenburg-an-der-havel/vamed-klinik-hohenstuecken-gmbh-14472')
            ->assertRedirect('/pflegeeinrichtungen/brandenburg/brandenburg-an-der-havel/vitrea-klinik-brandenburg-an-der-havel-gmbh-14772')
            ->assertStatus(301);
    }

    public function test_the_lexicon_index_lists_core_terms(): void
    {
        $this->assertCount(62, config('lexicon.terms'));

        $this->get('/pflegelexikon.html')
            ->assertOk()
            ->assertSee('Pflegelexikon')
            ->assertSee('Pflegegrad')
            ->assertSee('Ambulante Pflege')
            ->assertSee('Wohngruppenzuschlag')
            ->assertSee('Außerklinische Intensivpflege')
            ->assertSee(route('lexicon.show', 'pflegegrad'), false)
            ->assertSee(route('lexicon.show', 'wohngruppenzuschlag'), false)
            ->assertDontSee('/pflegelexikon/0.html', false);
    }

    public function test_a_lexicon_term_has_sources_and_related_terms(): void
    {
        $this->assertCount(62, config('lexicon_details.terms'));

        $this->get('/pflegelexikon/pflegegrad.html')
            ->assertOk()
            ->assertSee('Was bedeutet Pflegegrad?')
            ->assertSee('Auf einen Blick')
            ->assertSee('Beispiel aus dem Alltag')
            ->assertSee('Rechtlicher Hinweis')
            ->assertSee('Pflegeversicherung nach dem SGB XI')
            ->assertSee('Diese Informationen bieten eine erste Orientierung')
            ->assertSee('Offizielle Quellen')
            ->assertSee('Verwandte Begriffe');
    }

    public function test_an_unknown_lexicon_term_returns_not_found(): void
    {
        $this->get('/pflegelexikon/unbekannt.html')->assertNotFound();
    }
}

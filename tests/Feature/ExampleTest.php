<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
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

    public function test_home_has_open_graph_website_and_organization_metadata(): void
    {
        $title = 'PflegeIndex – Pflege einfach finden';
        $description = 'Pflegeheime, Pflegedienste, Tagespflege und Krankenhäuser in Brandenburg finden.';
        $pageUrl = route('home');
        $imageUrl = asset('assets/og-image.png');
        $response = $this->get($pageUrl)->assertOk();

        $response
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:title" content="'.$title.'">', false)
            ->assertSee('<meta property="og:description" content="'.$description.'">', false)
            ->assertSee('<meta property="og:url" content="'.$pageUrl.'">', false)
            ->assertSee('<meta property="og:site_name" content="PflegeIndex">', false)
            ->assertSee('<meta property="og:locale" content="de_DE">', false)
            ->assertSee('<meta property="og:image" content="'.$imageUrl.'">', false);

        $website = $this->jsonLdOfType($response->getContent(), 'WebSite');
        $organization = $this->jsonLdOfType($response->getContent(), 'Organization');

        $this->assertSame('PflegeIndex', $website['name']);
        $this->assertSame($pageUrl, $website['url']);
        $this->assertSame('de-DE', $website['inLanguage']);
        $this->assertSame(['@id' => $pageUrl.'#organization'], $website['publisher']);
        $this->assertSame('PflegeIndex', $organization['name']);
        $this->assertSame($pageUrl, $organization['url']);
        $this->assertSame(asset('logo.svg'), $organization['logo']);
        $this->assertArrayNotHasKey('sameAs', $organization);
        $this->assertFileExists(public_path('assets/og-image.png'));
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

    public function test_homepage_shows_popular_searches_and_top_cities(): void
    {
        $city = City::create([
            'name' => 'Potsdam',
            'slug' => 'potsdam',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);
        Facility::create([
            'source_id' => 'homepage-potsdam-1',
            'city_id' => $city->id,
            'name' => 'Pflege Potsdam',
            'slug' => 'pflege-potsdam',
            'postal_code' => '14467',
            'address' => 'Musterstraße 1',
            'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'],
            'features' => [],
        ]);

        $response = $this->get('/')->assertOk();

        $response
            ->assertSee('PflegeIndex startet in Brandenburg')
            ->assertSee('Pflegeangebote in Brandenburg übersichtlich zu entdecken')
            ->assertSee('href="'.route('cities.show', $city).'">Potsdam</a>', false)
            ->assertSee('Beliebte Suchen')
            ->assertSee('Pflegedienst Potsdam')
            ->assertSee('Pflegeheim Cottbus')
            ->assertSee('Pflegeheim Brandenburg an der Havel')
            ->assertSee('Pflegedienst Frankfurt (Oder)')
            ->assertSee('Pflegeheim Oranienburg')
            ->assertSee('Pflegedienst Eberswalde')
            ->assertSee('href="'.e(route('directory.index', ['q' => 'Potsdam', 'type' => 'Ambulante Pflege'])).'"', false);
    }

    /** @return array<string, mixed> */
    private function jsonLdOfType(string $content, string $type): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches);

        foreach ($matches[1] ?? [] as $json) {
            $schema = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (($schema['@type'] ?? null) === $type) {
                return $schema;
            }
        }

        $this->fail("JSON-LD type {$type} was not found.");
    }
}

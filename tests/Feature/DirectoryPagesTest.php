<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DirectoryPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_page_lists_its_facilities(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();

        $this->get(route('cities.show', $city))
            ->assertOk()
            ->assertSee($city->name)
            ->assertSee($facility->name);
    }

    public function test_facility_page_uses_the_pretty_nested_url(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee($facility->name)
            ->assertSee($facility->address);
    }

    public function test_facility_page_shows_safe_custom_description(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update(['description' => "Persönliche Beratung vor Ort.\n<script>alert('x')</script>"]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('Persönliche Beratung vor Ort.')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertDontSee('im offiziellen Einrichtungsverzeichnis');
    }

    public function test_facility_page_has_unique_and_escaped_open_graph_metadata(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update(['name' => 'Pflege & Wohnen "Am Park"']);

        $expectedTitle = "{$facility->name} in {$city->name} – PflegeIndex";
        $canonicalUrl = route('facilities.show', [$city, $facility]);

        $response = $this->get($canonicalUrl)->assertOk();
        $content = $response->getContent();
        $head = $this->headHtml($response);

        // Parse meta tag description from HTML
        $matched = preg_match('/<meta name="description" content="(.*?)">/', $head, $matches);
        $this->assertSame(1, $matched);
        $actualDescription = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertStringStartsWith('Pflege & Wohnen "Am Park" in Potsdam', $actualDescription);
        $this->assertStringContainsString('im amtlichen Einrichtungsverzeichnis des Landes Brandenburg geführt', $actualDescription);
        $this->assertLessThanOrEqual(158, mb_strlen($actualDescription));

        $this->assertStringContainsString('<meta property="og:type" content="website">', $head);
        $this->assertStringContainsString('<meta property="og:title" content="'.e($expectedTitle).'">', $head);
        $this->assertStringContainsString('<meta name="description" content="'.e($actualDescription).'">', $head);
        $this->assertStringContainsString('<meta property="og:description" content="'.e($actualDescription).'">', $head);
        $this->assertStringContainsString('<link rel="canonical" href="'.$canonicalUrl.'">', $head);
        $this->assertStringContainsString('<meta property="og:url" content="'.$canonicalUrl.'">', $head);
        $this->assertStringContainsString('<meta property="og:site_name" content="PflegeIndex">', $head);
        $this->assertStringContainsString('<meta property="og:locale" content="de_DE">', $head);
        $this->assertStringContainsString('<meta property="og:image" content="https://pflegeindex.com/assets/og-image.png">', $head);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $head);
        $this->assertStringContainsString('<meta name="twitter:image" content="https://pflegeindex.com/assets/og-image.png">', $head);
        $this->assertStringNotContainsString('content="Pflege & Wohnen "Am Park"', $content);

        foreach (['og:type', 'og:title', 'og:description', 'og:url', 'og:site_name', 'og:locale', 'og:image'] as $property) {
            $this->assertSame(1, substr_count($content, 'property="'.$property.'"'));
        }
    }

    public function test_facility_page_has_valid_local_business_structured_data(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'name' => 'Pflege & Wohnen </script> "Am Park"',
            'phone' => '+49331234567',
            'email' => 'kontakt@example.de',
        ]);

        $canonicalUrl = route('facilities.show', [$city, $facility]);
        $response = $this->get($canonicalUrl)->assertOk();
        $schema = $this->structuredData($response);
        $localBusiness = $this->schemaNode($schema, 'LocalBusiness');

        // Extract description from meta to compare
        $matched = preg_match('/<meta name="description" content="(.*?)">/', $this->headHtml($response), $matches);
        $this->assertSame(1, $matched);
        $expectedDescription = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame($facility->name, $localBusiness['name']);
        $this->assertSame($expectedDescription, $localBusiness['description']);
        $this->assertSame($canonicalUrl, $localBusiness['url']);
        $this->assertSame($facility->phone, $localBusiness['telephone']);
        $this->assertSame($facility->email, $localBusiness['email']);
        $this->assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => $facility->address,
            'postalCode' => $facility->postal_code,
            'addressLocality' => $city->name,
            'addressRegion' => 'Brandenburg',
            'addressCountry' => 'DE',
        ], $localBusiness['address']);
        $this->assertSame(1, substr_count($this->headHtml($response), '<script type="application/ld+json">'));
        $this->assertStringNotContainsString($facility->name, $this->headHtml($response));
    }

    public function test_facility_page_sprint4_improvements(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();

        // 1. Check fallback description does not have source_sector if null
        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $response->assertSee('ist als „Ambulante Pflege“ im amtlichen Einrichtungsverzeichnis des Landes Brandenburg geführt.');
        $response->assertDontSee('ist eine ambulante Pflegeeinrichtung im amtlichen Einrichtungsverzeichnis');

        // 2. Check source_sector mapping when set to 'Ambulante Pflegeeinrichtung'
        $facility->update(['source_sector' => 'Ambulante Pflegeeinrichtung']);
        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $response->assertSee('ist eine ambulante Pflegeeinrichtung im amtlichen Einrichtungsverzeichnis des Landes Brandenburg.');
        $response->assertDontSee('ist als „Ambulante Pflege“ im amtlichen');

        // 3. Duplicate Adresse section is removed
        $response->assertDontSee('<h2>Adresse</h2>', false);

        // 4. Address is visible under H1
        $response->assertSee('class="detail-address"', false);
        $response->assertSee($facility->address);

        // 5. Empty contacts state shows the prefilled mailto link
        $facility->update(['phone' => null, 'email' => null, 'website' => null]);
        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $response->assertSee('Für diese Einrichtung liegen derzeit keine direkten Kontaktdaten vor.');
        $response->assertSee('Kontaktdaten ergänzen');
        $response->assertSee('mailto:info@pflegeindex.com?subject=' . rawurlencode("Kontaktdaten ergänzen: {$facility->name}"), false);

        // 6. Editorial description is used when description is present
        $facility->update(['description' => 'Dies ist eine sehr schöne, ruhige und freundliche Pflegeeinrichtung mit tollem Garten, in der ältere Menschen professionelle Hilfe bekommen.']);
        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $head = $this->headHtml($response);
        $matched = preg_match('/<meta name="description" content="(.*?)">/', $head, $matches);
        $this->assertSame(1, $matched);
        $metaDesc = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringStartsWith('Dies ist eine sehr schöne, ruhige und freundliche Pflegeeinrichtung mit tollem Garten, in der ältere Menschen professionelle Hilfe bekommen.', $metaDesc);

        // 7. FAQ uses details/summary
        $response->assertSee('<details class="faq-item">', false);
        $response->assertSee('<summary class="faq-question">', false);

        // 8. Quality widget has aria-label on summary
        $response->assertSee('<summary aria-label="Erläuterung zur PflegeIndex Qualität">?</summary>', false);
    }

    public function test_facility_structured_data_omits_missing_contact_fields(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();

        $localBusiness = $this->schemaNode($this->structuredData(
            $this->get(route('facilities.show', [$city, $facility]))->assertOk(),
        ), 'LocalBusiness');

        $this->assertArrayNotHasKey('telephone', $localBusiness);
        $this->assertArrayNotHasKey('email', $localBusiness);
    }

    public function test_facility_page_has_accessible_visible_breadcrumbs(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();

        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $breadcrumbs = $this->breadcrumbHtml($response);

        $this->assertStringContainsString('<nav aria-label="Breadcrumb">', $breadcrumbs);
        $this->assertStringContainsString('<ol class="breadcrumbs"', $breadcrumbs);
        $this->assertStringContainsString('href="'.route('home').'">Startseite</a>', $breadcrumbs);
        $this->assertStringContainsString('href="'.route('region.show').'">'.$city->state.'</a>', $breadcrumbs);
        $this->assertStringContainsString('href="'.route('cities.show', $city).'">'.$city->name.'</a>', $breadcrumbs);
        $this->assertStringContainsString('aria-current="page"', $breadcrumbs);
        $this->assertStringContainsString($facility->name, $breadcrumbs);
        $this->assertStringNotContainsString('href="'.route('facilities.show', [$city, $facility]).'"', $breadcrumbs);
        $this->get(route('region.show'))->assertOk();
        $this->get(route('cities.show', $city))->assertOk();
    }

    public function test_facility_page_has_valid_breadcrumb_list_structured_data(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $canonicalUrl = route('facilities.show', [$city, $facility]);

        $schema = $this->structuredData($this->get($canonicalUrl)->assertOk());
        $breadcrumbList = $this->schemaNode($schema, 'BreadcrumbList');

        $this->assertSame([
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $city->state, 'item' => route('region.show')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $city->name, 'item' => route('cities.show', $city)],
            ['@type' => 'ListItem', 'position' => 4, 'name' => $facility->name, 'item' => $canonicalUrl],
        ], $breadcrumbList['itemListElement']);
    }

    public function test_facility_page_shows_email_without_requiring_a_phone(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'email' => 'kontakt@example.de',
            'contact_status' => 'verified',
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('mailto:kontakt@example.de', false)
            ->assertSee('E-Mail senden')
            ->assertSee('aria-label="Schnellkontakt"', false)
            ->assertSee('>E-Mail</a>', false)
            ->assertDontSee('>Anrufen</a>', false)
            ->assertDontSee('>Website</a>', false)
            ->assertSee('In Google Maps öffnen')
            ->assertSee('google.com/maps/search/?api=1&amp;query=', false);
    }

    public function test_facility_page_shows_mobile_quick_actions_only_for_available_contacts(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'phone' => '+49331234567',
            'email' => null,
            'website' => 'https://example.de/pflege',
        ]);

        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $quickActions = $this->mobileContactActionsHtml($response);

        $this->assertStringContainsString('aria-label="Schnellkontakt"', $quickActions);
        $this->assertStringContainsString('href="tel:+49331234567">Anrufen</a>', $quickActions);
        $this->assertStringContainsString('href="https://example.de/pflege"', $quickActions);
        $this->assertStringContainsString('>Website</a>', $quickActions);
        $this->assertStringNotContainsString('>E-Mail</a>', $quickActions);
        $this->assertTrue(
            strpos($quickActions, '>Anrufen</a>') < strpos($quickActions, '>Website</a>'),
        );

        $facility->update(['phone' => null, 'website' => null]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertDontSee('aria-label="Schnellkontakt"', false);
    }

    public function test_facility_page_has_prefilled_data_correction_mailto(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $canonicalUrl = route('facilities.show', [$city, $facility]);
        $mailto = 'mailto:info@pflegeindex.com?subject='.rawurlencode('Datenfehler PflegeIndex')
            .'&body='.rawurlencode("Einrichtung: {$facility->name}\nSeite: {$canonicalUrl}\n\nHinweis:\n");

        $this->get($canonicalUrl)
            ->assertOk()
            ->assertSee('Datenfehler melden')
            ->assertSee(e($mailto), false);
    }

    public function test_facility_page_displays_the_content_guidance_faq_and_contact_prompt(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update(['phone' => '+49 331 234567']);
        $relatedFacility = $this->createFacility(
            $city,
            'content-related-14467-test',
            'Pflegehaus am See',
            'pflegehaus-am-see-14467',
            'Seestraße 4',
        );

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('Was Sie wissen sollten')
            ->assertSee('Vor einem Vertragsabschluss sollte möglichst eine Besichtigung erfolgen.')
            ->assertSee('Häufige Fragen')
            ->assertSee('Welche Unterlagen werden benötigt?')
            ->assertSee('Kann ich einen Besichtigungstermin vereinbaren?')
            ->assertSee('Übernimmt die Pflegeversicherung Kosten?')
            ->assertSee('Wie finde ich einen Pflegeplatz?')
            ->assertSee('Weitere Informationen')
            ->assertSee('Fragen?')
            ->assertSee('href="tel:+49 331 234567"', false)
            ->assertSee("Weitere Pflegeeinrichtungen in {$city->name}")
            ->assertSee($relatedFacility->name);
    }

    public function test_facility_content_links_only_target_existing_lexicon_pages(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $desiredTerms = [
            'pflegegrad',
            'kurzzeitpflege',
            'pflegeversicherung',
            'ambulante-pflege',
            'stationaere-pflege',
        ];
        $availableTerms = config('lexicon.terms', []);

        foreach ($desiredTerms as $slug) {
            $url = route('lexicon.show', $slug);

            if (isset($availableTerms[$slug])) {
                $response->assertSee('href="'.$url.'"', false);
                $this->get($url)->assertOk();
            } else {
                $response->assertDontSee('href="'.$url.'"', false);
            }
        }
    }

    public function test_facility_content_layer_does_not_increase_queries_with_related_entries(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $this->createFacility(
            $city,
            'query-related-1-14467-test',
            'Pflegeeinrichtung 1',
            'pflegeeinrichtung-1-14467',
            'Testweg 1',
        );

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $queriesWithOneRelatedEntry = count(DB::getQueryLog());
        DB::disableQueryLog();

        foreach (range(2, 5) as $index) {
            $this->createFacility(
                $city,
                "query-related-{$index}-14467-test",
                "Pflegeeinrichtung {$index}",
                "pflegeeinrichtung-{$index}-14467",
                "Testweg {$index}",
            );
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $queriesWithRelatedEntries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queriesWithOneRelatedEntry, $queriesWithRelatedEntries);
    }

    public function test_facility_pages_distinguish_official_base_data_from_editorial_additions(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('Amtliche Grunddaten')
            ->assertSee('Kontaktdaten und Beschreibungen können redaktionell ergänzt sein.')
            ->assertDontSee('Offizieller Datensatz');

        $this->get(route('cities.show', $city))
            ->assertOk()
            ->assertSee('Amtliche Grunddaten')
            ->assertDontSee('Offizieller Datensatz');
    }

    public function test_facility_page_shows_other_facilities_from_the_same_city_and_links_to_the_city(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $relatedFacility = $this->createFacility(
            $city,
            'related-14467-test',
            'Pflege am Park',
            'pflege-am-park-14467',
            'Parkstraße 2',
        );
        $otherCity = City::create([
            'name' => 'Calau',
            'slug' => 'calau',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);
        $otherCityFacility = $this->createFacility(
            $otherCity,
            'other-city-03205-test',
            'Pflege in Calau',
            'pflege-in-calau-03205',
            'Calauer Weg 3',
        );

        $response = $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee("Weitere Pflegeeinrichtungen in {$city->name}")
            ->assertSee($relatedFacility->name)
            ->assertSee("Alle Pflegeeinrichtungen in {$city->name} ansehen")
            ->assertSee('href="'.route('cities.show', $city).'"', false);

        $relatedHtml = $this->relatedFacilitiesHtml($response);

        $this->assertStringNotContainsString($facility->name, $relatedHtml);
        $this->assertStringNotContainsString($otherCityFacility->name, $relatedHtml);
    }

    public function test_related_facilities_are_limited_to_three_and_stably_sorted(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $firstById = $this->createFacility(
            $city,
            'alpha-first-14467-test',
            'Alpha Pflege',
            'alpha-pflege-erster-eintrag-14467',
            'Sortierung 1',
        );
        $secondById = $this->createFacility(
            $city,
            'alpha-second-14467-test',
            'Alpha Pflege',
            'alpha-pflege-zweiter-eintrag-14467',
            'Sortierung 2',
        );
        $afterAlpha = $this->createFacility(
            $city,
            'beta-14467-test',
            'Beta Pflege',
            'beta-pflege-14467',
            'Sortierung 3',
        );
        $excludedByLimit = $this->createFacility(
            $city,
            'stationary-14467-test',
            'A Pflegeheim',
            'a-pflegeheim-14467',
            'Sortierung 4',
            'Stationäre Pflege',
        );

        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $relatedHtml = $this->relatedFacilitiesHtml($response);

        $this->assertSame(3, substr_count($relatedHtml, '<article class="result-card">'));
        $this->assertStringContainsString($firstById->address, $relatedHtml);
        $this->assertStringContainsString($secondById->address, $relatedHtml);
        $this->assertStringContainsString($afterAlpha->address, $relatedHtml);
        $this->assertStringNotContainsString($excludedByLimit->address, $relatedHtml);
        $this->assertTrue(
            strpos($relatedHtml, $firstById->address) < strpos($relatedHtml, $secondById->address)
            && strpos($relatedHtml, $secondById->address) < strpos($relatedHtml, $afterAlpha->address),
        );
    }

    public function test_facility_page_hides_related_block_when_the_facility_is_alone_in_its_city(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertDontSee('id="related-facilities"', false)
            ->assertDontSee("Weitere Pflegeeinrichtungen in {$city->name}");
    }

    public function test_short_german_phone_does_not_leave_a_two_digit_group(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'phone' => '+4933188700',
            'contact_status' => 'verified',
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('+49 331 88700');
    }

    public function test_directory_can_filter_by_query_and_type(): void
    {
        [, $matchingFacility] = $this->createDirectoryEntry();
        $otherCity = City::create([
            'name' => 'Calau',
            'slug' => 'calau',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);
        $otherFacility = Facility::create([
            'source_id' => 'other-03205-test',
            'city_id' => $otherCity->id,
            'name' => 'Anderes Krankenhaus',
            'slug' => 'anderes-krankenhaus-03205',
            'postal_code' => '03205',
            'address' => 'Testweg 2',
            'type' => 'Krankenhaus',
            'care_types' => ['Krankenhaus'],
            'features' => [],
        ]);

        $this->get(route('directory.index', ['q' => 'Potsdam', 'type' => 'Ambulante Pflege']))
            ->assertOk()
            ->assertSee($matchingFacility->name)
            ->assertDontSee($otherFacility->name);
    }

    public function test_facility_quality_panel_calculates_a_normalized_score(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'phone' => '+49 331 234567',
            'website' => 'https://example.org/einrichtung',
            'email' => 'kontakt@example.org',
            'description' => 'Eine redaktionell geprüfte Beschreibung.',
            'contact_status' => 'verified',
            'contact_checked_at' => '2026-07-20 12:00:00',
            'contact_source' => 'https://example.org/einrichtung',
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('data-quality-score="91"', false)
            ->assertSee('9 von 10 Qualitätsmerkmalen erfüllt')
            ->assertSee('data-quality-badge="official"', false)
            ->assertSee('data-quality-badge="contact"', false)
            ->assertSee('data-quality-badge="description"', false)
            ->assertSee('data-quality-badge="location"', false)
            ->assertSee('data-quality-badge="website"', false)
            ->assertDontSee('data-quality-criterion="coordinates"', false);
    }

    public function test_facility_quality_panel_hides_badges_for_missing_information(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('data-quality-score="32"', false)
            ->assertSee('4 von 10 Qualitätsmerkmalen erfüllt')
            ->assertSee('data-quality-badge="official"', false)
            ->assertSee('data-quality-badge="location"', false)
            ->assertDontSee('data-quality-badge="contact"', false)
            ->assertDontSee('data-quality-badge="description"', false)
            ->assertDontSee('data-quality-badge="website"', false);
    }

    public function test_facility_quality_panel_does_not_confirm_invalid_contact_data(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'phone' => '12',
            'website' => 'not-a-url',
            'email' => 'not-an-email',
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('data-quality-score="27"', false)
            ->assertDontSee('data-quality-criterion="phone"', false)
            ->assertDontSee('data-quality-criterion="website"', false)
            ->assertDontSee('data-quality-criterion="email"', false)
            ->assertDontSee('data-quality-criterion="errors"', false)
            ->assertDontSee('data-quality-badge="contact"', false)
            ->assertDontSee('data-quality-badge="website"', false);
    }

    public function test_facility_quality_panel_is_accessible_and_follows_mobile_actions(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update(['phone' => '+49 331 234567']);

        $response = $this->get(route('facilities.show', [$city, $facility]))->assertOk();
        $content = $response->getContent();
        $actionsPosition = strpos($content, '<nav class="mobile-contact-actions"');
        $qualityPosition = strpos($content, '<section class="quality-panel"');
        $addressPosition = strpos($content, '<p class="detail-address">');

        $response
            ->assertSee('role="progressbar"', false)
            ->assertSee('aria-label="Erläuterung zur PflegeIndex Qualität"', false)
            ->assertSee('Diese Bewertung beschreibt ausschließlich die Vollständigkeit und Qualität der vorliegenden Informationen. Sie ist keine Bewertung der Einrichtung.')
            ->assertSee('Stand der Bewertung:');
        $this->assertIsInt($actionsPosition);
        $this->assertIsInt($qualityPosition);
        $this->assertIsInt($addressPosition);
        $this->assertLessThan($qualityPosition, $actionsPosition);
        $this->assertLessThan($addressPosition, $qualityPosition);
        $this->assertSame(1, substr_count($content, '<section class="quality-panel"'));

        $stylesheet = file_get_contents(public_path('assets/styles.css'));
        $this->assertIsString($stylesheet);
        $this->assertStringContainsString('.mobile-contact-actions { display: none; }', $stylesheet);
        $this->assertStringContainsString('@media (max-width: 760px)', $stylesheet);
    }

    /** @return array{City, Facility} */
    private function createDirectoryEntry(): array
    {
        $city = City::create([
            'name' => 'Potsdam',
            'slug' => 'potsdam',
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);

        $facility = Facility::create([
            'source_id' => 'example-14467-test',
            'city_id' => $city->id,
            'name' => 'Beispiel Pflegezentrum',
            'slug' => 'beispiel-pflegezentrum-14467',
            'postal_code' => '14467',
            'address' => 'Musterstraße 1',
            'type' => 'Ambulante Pflege',
            'care_types' => ['Ambulante Pflege'],
            'features' => [],
        ]);

        return [$city, $facility];
    }

    private function createFacility(
        City $city,
        string $sourceId,
        string $name,
        string $slug,
        string $address,
        string $type = 'Ambulante Pflege',
    ): Facility {
        return Facility::create([
            'source_id' => $sourceId,
            'city_id' => $city->id,
            'name' => $name,
            'slug' => $slug,
            'postal_code' => '14467',
            'address' => $address,
            'type' => $type,
            'care_types' => [$type],
            'features' => [],
        ]);
    }

    public function test_trust_layer_shows_when_verified_with_date_source_and_contact(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'contact_status' => 'verified',
            'contact_checked_at' => '2026-07-20 12:00:00',
            'contact_source' => 'https://example.com/impressum',
            'phone' => '+4930123456',
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('Kontaktdaten geprüft am 20.07.2026')
            ->assertSee('Quelle:')
            ->assertSee('Website des Anbieters')
            ->assertSee('href="https://example.com/impressum"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="nofollow noopener noreferrer"', false);
    }

    public function test_trust_layer_hides_when_verified_with_date_and_contact_but_no_source(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'contact_status' => 'verified',
            'contact_checked_at' => '2026-07-20 12:00:00',
            'contact_source' => null,
            'phone' => '+4930123456',
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertDontSee('Kontaktdaten geprüft')
            ->assertDontSee('Website des Anbieters');
    }

    public function test_trust_layer_hides_when_status_is_null_with_contact(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'contact_status' => null,
            'contact_checked_at' => '2026-07-20 12:00:00',
            'contact_source' => 'https://example.com/impressum',
            'phone' => '+4930123456',
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertDontSee('Kontaktdaten geprüft')
            ->assertDontSee('Website des Anbieters');
    }

    public function test_trust_layer_hides_when_verified_but_no_contacts(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'contact_status' => 'verified',
            'contact_checked_at' => '2026-07-20 12:00:00',
            'contact_source' => 'https://example.com/impressum',
            'phone' => null,
            'email' => null,
            'website' => null,
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertDontSee('Kontaktdaten geprüft')
            ->assertDontSee('Website des Anbieters');
    }

    public function test_trust_layer_hides_when_verified_without_checked_at(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'contact_status' => 'verified',
            'contact_checked_at' => null,
            'contact_source' => 'https://example.com/impressum',
            'phone' => '+4930123456',
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertDontSee('Kontaktdaten geprüft')
            ->assertDontSee('Website des Anbieters');
    }

    public function test_trust_layer_shows_date_without_link_when_source_is_invalid_url(): void
    {
        [$city, $facility] = $this->createDirectoryEntry();
        $facility->update([
            'contact_status' => 'verified',
            'contact_checked_at' => '2026-07-20 12:00:00',
            'contact_source' => 'invalid-url',
            'phone' => '+4930123456',
        ]);

        $this->get(route('facilities.show', [$city, $facility]))
            ->assertOk()
            ->assertSee('Kontaktdaten geprüft am 20.07.2026')
            ->assertSee('Quelle:')
            ->assertSee('Website des Anbieters')
            ->assertDontSee('href="invalid-url"', false);
    }

    private function relatedFacilitiesHtml(TestResponse $response): string
    {
        $content = $response->getContent();
        $start = strpos($content, '<section class="section section--white" id="related-facilities"');

        if ($start === false) {
            return '';
        }

        $end = strpos($content, '</section>', $start);

        return substr($content, $start, $end - $start + strlen('</section>'));
    }

    private function mobileContactActionsHtml(TestResponse $response): string
    {
        $content = $response->getContent();
        $start = strpos($content, '<nav class="mobile-contact-actions"');

        if ($start === false) {
            return '';
        }

        $end = strpos($content, '</nav>', $start);

        return substr($content, $start, $end - $start + strlen('</nav>'));
    }

    private function headHtml(TestResponse $response): string
    {
        $content = $response->getContent();
        $start = strpos($content, '<head>');
        $end = strpos($content, '</head>');

        return substr($content, $start, $end - $start + strlen('</head>'));
    }

    private function breadcrumbHtml(TestResponse $response): string
    {
        $content = $response->getContent();
        $start = strpos($content, '<nav aria-label="Breadcrumb">');
        $end = strpos($content, '</nav>', $start);

        return substr($content, $start, $end - $start + strlen('</nav>'));
    }

    /** @return array<string, mixed> */
    private function structuredData(TestResponse $response): array
    {
        $matched = preg_match(
            '/<script type="application\/ld\+json">(.*?)<\/script>/s',
            $this->headHtml($response),
            $matches,
        );

        $this->assertSame(1, $matched);

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $schema */
    private function schemaNode(array $schema, string $type): array
    {
        $node = collect($schema['@graph'])->firstWhere('@type', $type);

        $this->assertIsArray($node);

        return $node;
    }
}

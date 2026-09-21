<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the SEO Growth improvements applied to /pflegeheime.html
 * (the central PflegeIndex directory page), per the SEO Growth Content Audit
 * of 2026-08-19. These tests cover only the directory page; other audited
 * pages (facility, city, Landkreis, lexikon) are intentionally out of scope.
 */
class DirectorySeoGrowthTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    public function test_directory_page_returns_http_200(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $this->get(route('directory.index'))->assertOk();
    }

    public function test_directory_page_has_the_new_title_using_the_dynamic_facility_count(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $expectedCount = number_format(Facility::count(), 0, ',', '.');

        $this->get(route('directory.index'))
            ->assertOk()
            ->assertSee(
                "<title>Pflegeheime &amp; Pflegedienste in Brandenburg finden – {$expectedCount} Einrichtungen | PflegeIndex</title>",
                false,
            );
    }

    public function test_directory_page_has_the_new_meta_description_using_the_dynamic_facility_count(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $expectedCount = number_format(Facility::count(), 0, ',', '.');

        $this->get(route('directory.index'))
            ->assertOk()
            ->assertSee(
                '<meta name="description" content="Über '.$expectedCount.' Pflegeheime, Pflegedienste und Krankenhäuser in Brandenburg. Nach Ort, PLZ, Name und Einrichtungsart filtern – mit amtlichen Basisdaten des LASV.">',
                false,
            );
    }

    public function test_directory_page_keeps_the_existing_lead_and_adds_the_new_intro_paragraph_once(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $response = $this->get(route('directory.index'))->assertOk();

        $response->assertSee('Durchsuchen Sie', false);
        $response->assertSee(
            'PflegeIndex führt drei Arten von Einrichtungen: ambulante Pflegedienste, die Pflege im eigenen Zuhause übernehmen, stationäre und teilstationäre Einrichtungen wie Pflegeheime und Tagespflegen sowie Krankenhäuser mit pflegerischem Bezug. Nutzen Sie die Filter, um nach Ort, Postleitzahl, Name oder Einrichtungsart einzugrenzen.',
        );

        $occurrences = substr_count(
            $response->getContent(),
            'PflegeIndex führt drei Arten von Einrichtungen',
        );
        $this->assertSame(1, $occurrences, 'The new intro paragraph must not be duplicated.');
    }

    public function test_directory_page_has_the_einrichtungsarten_ueberblick_heading_and_type_links(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $response = $this->get(route('directory.index'))->assertOk();

        $response->assertSee('Einrichtungsarten im Überblick');
        $response->assertSee('<h2 id="einrichtungsarten-title">Einrichtungsarten im Überblick</h2>', false);

        foreach (['Ambulante Pflege', 'Stationäre/teilstationäre Pflege', 'Krankenhaus'] as $type) {
            $response->assertSee(
                'href="'.route('directory.index', ['type' => $type]).'"',
                false,
            );
        }
    }

    public function test_directory_page_links_to_the_minimum_required_selected_cities(): void
    {
        [$potsdam, $cottbus, $oranienburg] = $this->seedFeaturedCitiesWithFacilities();

        $response = $this->get(route('directory.index'))->assertOk();

        $response->assertSee('Pflegeangebote in ausgewählten Städten');

        foreach ([$potsdam, $cottbus, $oranienburg] as $city) {
            $response->assertSee('href="'.route('cities.show', $city).'"', false);
            $response->assertSee($city->name);
        }
    }

    public function test_directory_page_has_exactly_three_faq_entries_with_the_expected_text(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $response = $this->get(route('directory.index'))->assertOk();
        $content = $response->getContent();

        $response->assertSee('Häufig gestellte Fragen');
        $this->assertSame(3, substr_count($content, 'class="faq-question"'));

        $response->assertSee('Was ist der Unterschied zwischen einem Pflegeheim und einem Pflegedienst?');
        $response->assertSee('Wie aktuell sind die Daten auf PflegeIndex?');
        $response->assertSee('Sind alle Pflegeeinrichtungen in Brandenburg erfasst?');

        // The data-freshness answer must not rely on a fixed, potentially
        // stale date that is not centrally maintained elsewhere.
        $this->assertStringNotContainsString('31.12.2025', $this->faqAnswerFor($content, 'Wie aktuell sind die Daten auf PflegeIndex?'));
    }

    public function test_directory_page_has_valid_collection_page_structured_data(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $canonical = route('directory.index');
        $schema = $this->jsonLdSchemas($this->get(route('directory.index'))->assertOk()->getContent());
        $collectionPage = $this->findSchema($schema, 'CollectionPage');

        $this->assertNotNull($collectionPage, 'CollectionPage structured data must be present.');
        $this->assertSame($canonical, $collectionPage['url']);
        $this->assertArrayHasKey('name', $collectionPage);
        $this->assertArrayHasKey('description', $collectionPage);
    }

    public function test_directory_page_has_item_list_structured_data_scoped_to_the_visible_page(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $response = $this->get(route('directory.index'))->assertOk();
        $paginator = $response->viewData('facilities');
        $schema = $this->jsonLdSchemas($response->getContent());
        $itemList = $this->findSchema($schema, 'ItemList');

        $this->assertNotNull($itemList, 'ItemList structured data must be present.');
        $this->assertIsArray($itemList['itemListElement']);
        $this->assertCount($paginator->count(), $itemList['itemListElement']);

        foreach ($itemList['itemListElement'] as $item) {
            $this->assertSame('ListItem', $item['@type']);
            $this->assertArrayHasKey('position', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('url', $item);
            $this->assertArrayNotHasKey('review', $item);
            $this->assertArrayNotHasKey('aggregateRating', $item);
            $this->assertArrayNotHasKey('offers', $item);
        }
    }

    public function test_directory_page_has_valid_breadcrumb_list_structured_data(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $canonical = route('directory.index');
        $schema = $this->jsonLdSchemas($this->get(route('directory.index'))->assertOk()->getContent());
        $breadcrumbList = $this->findSchema($schema, 'BreadcrumbList');

        $this->assertNotNull($breadcrumbList, 'BreadcrumbList structured data must be present.');
        $items = $breadcrumbList['itemListElement'];
        $this->assertSame('Startseite', $items[0]['name']);
        $this->assertSame(route('home'), $items[0]['item']);
        $this->assertSame($canonical, end($items)['item']);
    }

    public function test_directory_page_has_no_duplicate_json_ld_blocks(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $content = $this->get(route('directory.index'))->assertOk()->getContent();
        $schemas = $this->jsonLdSchemas($content);
        $types = array_map(static fn (array $schema): string => $schema['@type'], $schemas);

        $this->assertSame(['CollectionPage', 'ItemList', 'BreadcrumbList'], $types);
        $this->assertSame(3, substr_count($content, 'application/ld+json'));
    }

    public function test_filtered_directory_url_remains_noindex_follow(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $this->get(route('directory.index', ['q' => 'Potsdam']))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_unfiltered_directory_url_stays_indexable_with_correct_canonical(): void
    {
        $this->seedFeaturedCitiesWithFacilities();

        $canonical = route('directory.index');

        $this->get($canonical)
            ->assertOk()
            ->assertDontSee('<meta name="robots" content="noindex,follow">', false)
            ->assertSee('<link rel="canonical" href="'.$canonical.'">', false);
    }

    public function test_paginated_directory_canonical_matches_the_current_page(): void
    {
        $city = $this->createCity('Potsdam', 'potsdam');
        foreach (range(1, 25) as $number) {
            $this->createFacility($city, sprintf('Pflege Potsdam %02d', $number), 'Ambulante Pflege');
        }

        $canonicalPage2 = route('directory.index', ['page' => 2]);

        $this->get($canonicalPage2)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$canonicalPage2.'">', false)
            ->assertDontSee('<meta name="robots" content="noindex,follow">', false);
    }

    /**
     * @return list<City>
     */
    private function seedFeaturedCitiesWithFacilities(): array
    {
        $potsdam = $this->createCity('Potsdam', 'potsdam');
        $cottbus = $this->createCity('Cottbus', 'cottbus');
        $oranienburg = $this->createCity('Oranienburg', 'oranienburg');

        $this->createFacility($potsdam, 'Pflegezentrum Potsdam', 'Ambulante Pflege');
        $this->createFacility($cottbus, 'Pflegeheim Cottbus', 'Stationäre/teilstationäre Pflege');
        $this->createFacility($oranienburg, 'Klinikum Oranienburg', 'Krankenhaus');

        return [$potsdam, $cottbus, $oranienburg];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonLdSchemas(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        return array_map(
            static fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
            $matches[1],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $schemas
     * @return array<string, mixed>|null
     */
    private function findSchema(array $schemas, string $type): ?array
    {
        foreach ($schemas as $schema) {
            if (($schema['@type'] ?? null) === $type) {
                return $schema;
            }
        }

        return null;
    }

    private function faqAnswerFor(string $html, string $question): string
    {
        $questionPosition = mb_strpos($html, $question);
        $this->assertNotFalse($questionPosition, "FAQ question not found: {$question}");

        $answerStart = mb_strpos($html, 'faq-answer', $questionPosition);
        $answerEnd = mb_strpos($html, '</details>', $answerStart);

        return mb_substr($html, $answerStart, $answerEnd - $answerStart);
    }

    private function createCity(string $name, string $slug): City
    {
        return City::create([
            'name' => $name,
            'slug' => $slug,
            'state' => 'Brandenburg',
            'state_slug' => 'brandenburg',
        ]);
    }

    private function createFacility(
        City $city,
        string $name,
        string $type,
        string $address = 'Musterstraße 1',
        string $postalCode = '14467',
    ): Facility {
        $this->sequence++;

        return Facility::create([
            'source_id' => "seo-growth-{$this->sequence}",
            'city_id' => $city->id,
            'name' => $name,
            'slug' => "seo-growth-{$this->sequence}",
            'postal_code' => $postalCode,
            'address' => $address,
            'type' => $type,
            'care_types' => [$type],
            'features' => [],
        ]);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

final class PublicErrorPageTest extends TestCase
{
    public function test_unknown_public_url_uses_the_german_pflegeindex_404_page(): void
    {
        $this->get('/diese-seite-existiert-nicht')
            ->assertNotFound()
            ->assertSee('<title>Seite nicht gefunden – PflegeIndex</title>', false)
            ->assertSee('Fehler 404')
            ->assertSee('Seite nicht gefunden')
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('Zur Startseite')
            ->assertSee('href="'.route('directory.index').'"', false)
            ->assertSee('Pflegeeinrichtungen suchen')
            ->assertDontSee('Not Found');
    }
}

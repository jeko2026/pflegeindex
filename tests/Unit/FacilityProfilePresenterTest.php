<?php

namespace Tests\Unit;

use App\Models\Facility;
use App\Models\FacilitySocialLink;
use App\Services\FacilityProfilePresenter;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class FacilityProfilePresenterTest extends TestCase
{
    public function test_it_normalizes_weekly_hours(): void
    {
        $rows = (new FacilityProfilePresenter)->openingHours('Sunday(2026-09-20): [6 AM–10 PM], Monday(2026-09-21): [Open 24 hours], Tuesday(2026-09-22): [Closed]');

        $this->assertSame('Sonntag', $rows[0]['day']);
        $this->assertSame('06:00–22:00', $rows[0]['value']);
        $this->assertSame('24 Stunden geöffnet', $rows[1]['value']);
        $this->assertSame('Geschlossen', $rows[2]['value']);
    }

    public function test_it_filters_malformed_and_root_social_urls(): void
    {
        $facility = new Facility;
        $facility->setRelation('socialLinks', new Collection([
            new FacilitySocialLink(['platform' => 'facebook', 'url' => 'https://www.facebook.com/example?utm_source=test']),
            new FacilitySocialLink(['platform' => 'instagram', 'url' => 'instagram.com']),
            new FacilitySocialLink(['platform' => 'youtube', 'url' => 'https://www.youtube.com/']),
        ]));

        $links = (new FacilityProfilePresenter)->socialLinks($facility);

        $this->assertCount(1, $links);
        $this->assertSame('https://www.facebook.com/example', $links[0]['url']);
    }
}

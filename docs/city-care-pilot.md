# Pilot: Stadt × Pflegeart

Five combinations are published through `config/care_pages.php`. The route is
`/brandenburg/{city:slug}/{careSlug}.html` (`cities.care.show`), matching the
existing city-page `.html` convention. Existing city and facility URLs are unchanged.

## Selection and limits

`CarePageService` is the single selection rule for rendering, city links,
facility backlinks and sitemap inclusion. Every query is scoped by `city_id`,
requires a Brandenburg city and a configured pilot combination. Unknown,
unpublished and empty combinations return 404. No database changes or imports
are necessary. Nothing is inferred from descriptions or contact quality.

The current database contains only three coarse types: `Ambulante Pflege`,
`Stationäre/teilstationäre Pflege` and `Krankenhaus`. Its `care_types` currently
repeat those values; `source_sector` does not distinguish day and residential care.

- Ambulante Pflegedienste: exact `Ambulante Pflege` type.
- Tagespflege: exact specific type/care type when available; otherwise the broad
  stationary/partial stationary type plus `Tagespflege` or `Tages- und Nachtpflege`
  in the official facility name.
- Pflegeheime: exact specific type/care type when available; otherwise the broad
  type and an explicit residential name marker from the configuration. Day/night
  care, short-term care, residential groups and assisted living names are excluded
  from this fallback. A record is never a nursing home just because it is not day care.

This is a conservative derived presentation taxonomy, not a change to official
classification. Names such as `DeFalia Daycare`, `Tagesbetreuung`, `LUISE Wohlfühlen`
and `Seniorenhaus` remain unclassified without a precise type or a separately
verified mapping. Counts therefore describe the matching records, not a complete
inventory of all services in the city. Review ambiguous records and classification
rules before expanding publication. Existing cards retain the original type.

The five pilot lists currently have at most 30 entries, so all cards are rendered
server-side on one page with eager-loaded cities. Feature tests assert query counts
do not grow with card counts. For substantially larger future lists, reuse the
existing paginator before expanding the pilot.

## SEO and navigation

Each page has its own title, description, H1, canonical, `index, follow`, visible
breadcrumbs, BreadcrumbList and CollectionPage with an ItemList linking to the
existing facility detail pages. There is no aggregate LocalBusiness or hreflang.
URLs follow Laravel's existing request-host conventions: localhost during preview,
https://pflegeindex.com on production.

Cities show only published categories with matches. Matching facility profiles
link back to the category. Category pages link to their city and facility profiles.
The existing XML sitemap adds only nonempty published categories; its cache header
remains one hour. `robots.txt` already allows public pages.

## Validation

Run with PHP 8.2 and SQLite:

```text
php artisan test --filter=CityCarePageTest
php artisan test
php artisan route:list --path=brandenburg
php artisan view:cache
php vendor/bin/pint --test app/Services/CarePageService.php app/Http/Controllers/CityCareController.php app/Http/Controllers/CityController.php app/Http/Controllers/FacilityController.php app/Http/Controllers/SitemapController.php config/care_pages.php tests/Feature/CityCarePageTest.php
 git diff --check
```

Tests use the isolated in-memory SQLite configuration from phpunit.xml.
No commit, push or production deployment is part of this change.

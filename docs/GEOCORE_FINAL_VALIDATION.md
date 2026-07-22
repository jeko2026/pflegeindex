# GeoCore Final Validation

## 1. Executive Summary

**Result: PASS**

Audit date: 2026-07-22

This was a read-only post-application validation of the local PflegeIndex SQLite database and current Laravel application. No migration, import, mapping, schema, city, facility, municipality, district, or production change was performed.

The approved mapping is internally consistent: 255 of 257 cities are mapped, 1,552 of 1,557 facilities are available through the official district hierarchy, all 18 district pages are populated, and the remaining 2 review cities account for exactly 5 unresolved facilities. Database integrity, hierarchy, public routes, pagination, SEO URL inventory, internal links, and architecture checks passed.

## 2. Audit Scope and Safety

- The audit used the current local SQLite database only.
- The diagnostic Laravel connection was switched to `PRAGMA query_only=ON` before data queries.
- Public pages were rendered through the Laravel HTTP kernel without a web-server deployment.
- External websites were not requested; the broken-link audit covers internal navigation.
- The database SHA-256 at the start of the audit was `5f37fb8a12743def3a41c96a55d415d164a91e009de6a20d5e33423ad34a4423`.
- Production was not accessed.

## 3. Database Integrity

| Check | Result |
| --- | ---: |
| SQLite `integrity_check` | `ok` |
| Foreign keys enabled on the Laravel connection | Yes (`1`) |
| `foreign_key_check` violations | 0 |
| GeoCore tables accessible | PASS |
| Countries | 1 |
| States | 1 |
| Districts | 18 |
| Municipalities | 413 |
| Cities | 257 |
| Facilities | 1,557 |

### Referential integrity

| Reference | Orphans |
| --- | ---: |
| State → country | 0 |
| District → state | 0 |
| Municipality → district | 0 |
| Mapped city → municipality | 0 |
| Facility → city | 0 |

### Identifier uniqueness

| Identifier | Duplicate groups |
| --- | ---: |
| Country ISO2 | 0 |
| State AGS per country | 0 |
| District AGS per state | 0 |
| Municipality AGS | 0 |

## 4. Mapping Validation

| Metric | Expected | Actual | Result |
| --- | ---: | ---: | --- |
| Cities | 257 | 257 | PASS |
| Mapped cities | 255 | 255 | PASS |
| Unresolved cities | 2 | 2 | PASS |
| Unknown municipality references | 0 | 0 | PASS |
| Facilities | 1,557 | 1,557 | PASS |
| Facilities with municipality/district coverage | 1,552 | 1,552 | PASS |
| Facilities without municipality/district coverage | 5 | 5 | PASS |
| Facilities returned by district joins | 1,552 | 1,552 | PASS |
| Distinct facilities returned by district joins | 1,552 | 1,552 | PASS |

The 75 approved assignments match their approved municipality relations. The two `REVIEW` city IDs remain unresolved.

### Remaining unresolved cities

| City ID | City | Slug | Facilities |
| ---: | --- | --- | ---: |
| 99 | Hennickendorf | `hennickendorf` | 4 |
| 190 | Reichenberg, Märkische Heide | `reichenberg-maerkische-heide` | 1 |

### Remaining unresolved facilities

| Facility ID | Facility | City | Slug |
| ---: | --- | --- | --- |
| 619 | Ambulanter Pflegedienst Martin Altrock GmbH | Hennickendorf | `ambulanter-pflegedienst-martin-altrock-gmbh-15378` |
| 620 | Der Pflegedienst Kathleen Welsch GmbH | Hennickendorf | `der-pflegedienst-kathleen-welsch-gmbh-15378` |
| 621 | Pflege im Kiez Wachner GmbH | Hennickendorf | `pflege-im-kiez-wachner-gmbh-15378` |
| 622 | Tagespflege "Zum alten Bahnhof" | Hennickendorf | `tagespflege-zum-alten-bahnhof-15379` |
| 1173 | Tagespflege "Thomas Müntzer" Diakonisches Werk Oderland - Spree e.V. | Reichenberg, Märkische Heide | `tagespflege-thomas-muentzer-diakonisches-werk-oderland-spree-e-v-15377` |

## 5. Geo Hierarchy Validation

The validated hierarchy is:

```text
Deutschland
└── Brandenburg
    └── District
        └── Municipality
            └── City
                └── Facility
```

| Check | Result |
| --- | ---: |
| Mapped cities with exactly one municipality parent | 255 |
| Municipalities with exactly one district parent | 413 |
| Districts belonging to Brandenburg / Germany | 18 of 18 |
| Districts outside Brandenburg | 0 |
| Broken hierarchy paths | 0 |
| Multiple municipality parents | 0 |
| Multiple city parents | 0 |
| Cycles | 0 |

The schema uses directional foreign keys between distinct hierarchy levels and contains no self-referential GeoCore relationship. Together with zero orphan and multiple-parent results, no hierarchy cycle is possible in the stored graph.

## 6. Geo Coverage

### Summary

| Metric | Value |
| --- | ---: |
| Districts | 18 |
| Cities | 257 |
| Mapped cities | 255 (99.22%) |
| Review cities | 2 (0.78%) |
| Facilities | 1,557 |
| Facilities with district coverage | 1,552 (99.68%) |
| Facilities without district coverage | 5 (0.32%) |
| Empty district pages | 0 |

### Coverage by district

| AGS | District | Mapped cities | Facilities |
| --- | --- | ---: | ---: |
| 12060 | Barnim | 16 | 124 |
| 12065 | Oberhavel | 17 | 112 |
| 12064 | Märkisch-Oderland | 22 | 107 |
| 12069 | Potsdam-Mittelmark | 27 | 107 |
| 12073 | Uckermark | 18 | 105 |
| 12063 | Havelland | 15 | 97 |
| 12067 | Oder-Spree | 20 | 97 |
| 12061 | Dahme-Spreewald | 19 | 96 |
| 12072 | Teltow-Fläming | 16 | 87 |
| 12068 | Ostprignitz-Ruppin | 21 | 84 |
| 12066 | Oberspreewald-Lausitz | 17 | 82 |
| 12071 | Spree-Neiße | 12 | 79 |
| 12062 | Elbe-Elster | 18 | 78 |
| 12070 | Prignitz | 13 | 74 |
| 12052 | Cottbus, Stadt | 1 | 72 |
| 12054 | Potsdam, Stadt | 1 | 63 |
| 12051 | Brandenburg an der Havel, Stadt | 1 | 49 |
| 12053 | Frankfurt (Oder), Stadt | 1 | 39 |

The largest district by facility coverage is Barnim with 124 facilities. The smallest is Frankfurt (Oder), Stadt with 39. The sum across all districts is 1,552, equal to the distinct facility count.

## 7. Public Pages Audit

All 1,899 sitemap pages were rendered through the current Laravel HTTP kernel.

| Public type | URLs audited | HTTP 200 | Title | Meta description | Canonical | Required breadcrumbs | Required JSON-LD |
| --- | ---: | ---: | --- | --- | --- | --- | --- |
| Homepage | 1 | 1 | PASS | PASS | PASS | N/A | PASS |
| Directory | 1 | 1 | PASS | PASS | PASS | N/A | Not required by current contract |
| Brandenburg region | 1 | 1 | PASS | PASS | PASS | N/A | PASS |
| District | 18 | 18 | PASS | PASS | PASS | PASS | PASS |
| City | 257 | 257 | PASS | PASS | PASS | PASS | PASS |
| Facility | 1,557 | 1,557 | PASS | PASS | PASS | PASS | PASS |
| Lexicon | 63 | 63 | PASS | PASS | PASS | Per template | Per template |
| Project/static sitemap page | 1 | 1 | PASS | PASS | PASS | Per template | Per template |

Results:

- sitemap-page HTTP failures: 0;
- missing title/description/canonical: 0;
- required breadcrumb failures: 0;
- required JSON-LD failures: 0;
- unique internal navigation targets discovered: 1,789;
- internal links checked: 1,789;
- broken internal links: 0;
- unexpected internal redirects: 0.

The crawl included city-to-district, district-to-city, facility-to-city, regional navigation, footer/navigation, lexicon, and pagination links. External destinations were not fetched because this audit was restricted to local read-only validation.

## 8. Pagination Validation

| Metric | Result |
| --- | ---: |
| Listing scopes audited | 277 |
| Pagination URLs rendered | 475 |
| HTTP/metadata/canonical failures | 0 |
| Duplicate pagination canonicals | 0 |
| Directory pages | 65 |
| Brandenburg pages | 65 |
| Maximum district pages | 6 |
| Maximum city pages | 3 |

Page 1 canonicals omit `page`; page 2 and later use self-canonical URLs and page-specific title and meta description containing the correct page number. Existing ordering tests remain successful, and district joins return no duplicate facility cards.

## 9. SEO Consistency

### Sitemap inventory

| URL type | Count |
| --- | ---: |
| Homepage | 1 |
| Directory | 1 |
| Brandenburg region | 1 |
| District | 18 |
| City | 257 |
| Facility | 1,557 |
| Lexicon | 63 |
| Project/static | 1 |
| **Total** | **1,899** |

Checks:

- sitemap HTTP status: 200;
- sitemap URLs: 1,899;
- unique sitemap URLs: 1,899;
- duplicate URLs: 0;
- sitemap URL failures: 0;
- duplicate canonicals: 0;
- `/up` in sitemap: no;
- `robots.txt`: HTTP 200 and contains the sitemap directive;
- `/up`: HTTP 200 with `X-Robots-Tag: noindex, nofollow, noarchive`;
- district, city, and facility URL sets are complete and unique.

### Canonical host and HTTPS

| Request | Result |
| --- | --- |
| `http://pflegeindex.com/` | 301 → `https://pflegeindex.com/` |
| `http://www.pflegeindex.com/` | 301 → `https://pflegeindex.com/` |
| `https://www.pflegeindex.com/` | 301 → `https://pflegeindex.com/` |
| `https://pflegeindex.com/` | HTTP 200, canonical `https://pflegeindex.com` |

The local sitemap crawl used the local application host, while the explicit production-host matrix confirms the single HTTPS/non-www canonical host. No canonical, route, sitemap, or SEO template was changed by the GeoCore application.

## 10. Architecture Validation

| Check | Result |
| --- | --- |
| DirectoryCore changed by mapping application | No |
| DirectoryCore project-independent dependency test | PASS |
| GeoCore schema or hierarchy model changed | No |
| PflegeEntryRepository changed | No |
| ListEntries changed | No |
| Public routes/API changed | No |
| Controllers or page-query logic changed | No |
| Platform dependency violations | 0 |
| Second-project adapter compatibility | PASS |

The application commit changed only the dedicated GeoCore command, its tests, and application evidence. A repository diff confirms that DirectoryCore, `EntryRepository`, `PflegeEntryRepository`, `ListEntries`, routes, controllers, and public presentation code remained unchanged.

## 11. Tests and Quality Checks

| Check | Result |
| --- | --- |
| `php artisan test` | PASS — 202 tests, 3,070 assertions |
| Architecture tests | PASS |
| `composer validate --strict` | PASS |
| `vendor/bin/pint --test` | Existing non-GeoCore style deviations in 2 files |
| `git diff --check` | PASS |
| `composer audit` | Environment-limited |

`composer audit` could not retrieve security advisories because network access was disabled and a complete writable local advisory cache was unavailable. This is an environmental limitation, not a detected dependency or code failure. No network override was used.

This audit changed no PHP file. The requested full-project Pint check reported existing formatting deviations in `app/Http/Controllers/HomeController.php` and `deployment/server-diagnostics.php`. Both files were already unchanged in the working tree and are outside this audit's scope; they were not reformatted.

## 12. Remaining Limitations

1. Hennickendorf and Reichenberg, Märkische Heide remain intentionally unresolved pending separate evidence and approval.
2. Five facilities therefore remain absent from district pages, although their city and facility pages remain public.
3. This audit validates the local applied SQLite; it does not prove that production has received or validated the same data state.
4. External links were not fetched. Internal public navigation was exhaustively checked from the current sitemap pages.
5. A current network-backed Composer advisory audit remains outstanding.
6. The two pre-existing Pint deviations should be handled separately if full-project formatting compliance is required.

## 13. Recommendation

The local GeoCore state is ready for the next separately controlled production-validation and rollout-planning stage. Do not treat this report as authorization to modify production SQLite: production requires its own verified backup, fingerprint, dry run, controlled application, and post-application validation.

The two remaining review cases should remain unresolved until confirmed; they do not justify delaying validation of the already approved 75 mappings.

## 14. Overall GeoCore Readiness

**99%**

Rationale:

- database integrity and hierarchy: complete;
- approved mapping correctness: complete;
- district-page coverage: 99.68% of facilities;
- city mapping coverage: 99.22%;
- public pages, internal links, pagination, sitemap, canonical host, and architecture: validated without failures;
- remaining deductions: 2 review cities / 5 facilities, production state not yet validated, and Composer advisories unavailable offline.

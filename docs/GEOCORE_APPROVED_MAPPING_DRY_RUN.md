# GeoCore Approved Mapping Dry Run

## Status

**PASS**

The approved mapping was validated and applied only to an isolated copy of the local SQLite database on 2026-07-22. The working database, source mapping CSV, routes, slugs, sitemap logic, migrations, and GeoCore source data were not changed.

## Inputs and isolation

| Input | SHA-256 before | SHA-256 after | Result |
| --- | --- | --- | --- |
| Working SQLite (`database/database.sqlite`) | `d5e5c029669fd091d1a3cd99c598f9afcce48ecfe376744c2d201f1365c781c8` | `d5e5c029669fd091d1a3cd99c598f9afcce48ecfe376744c2d201f1365c781c8` | Unchanged |
| Approved mapping (`docs/GEOCORE_APPROVED_MAPPING.csv`) | `37b6ae58d2e731a28beabf88b98a4466ed9c369dc3fe38a7d805eb981dedeb5d` | `37b6ae58d2e731a28beabf88b98a4466ed9c369dc3fe38a7d805eb981dedeb5d` | Unchanged |

The command created a uniquely named directory below the operating system's temporary directory, copied SQLite to `database.sqlite` inside it, switched the Laravel database connection to that copy, ran all mutations and checks there, disconnected, and removed the copy. No private absolute path is recorded in this report. The command rejects an active SQLite WAL or journal rather than risk an inconsistent file copy.

## Approved CSV validation

| Check | Result |
| --- | --- |
| Required columns and order | PASS |
| Data rows | 77 |
| Unique `city_id` values | 77 |
| `APPROVED` | 75 |
| `REVIEW` | 2 |
| `SKIP` | 0 |
| Approved municipality AGS present | PASS |
| City IDs and names exist and agree | PASS |
| Stored facility counts agree | PASS |
| Municipality AGS and names exist and agree | PASS |
| Municipality-to-district relations agree | PASS |
| Duplicate city mappings | 0 |
| Conflicting existing mappings | 0 |

Structural errors, missing records, wrong district relations, and conflicting existing mappings stop the command with a non-zero exit code before any mapping is applied. Existing conflicting relations are never overwritten.

## Before and after simulation

| Metric | Before | After | Delta |
| --- | ---: | ---: | ---: |
| Total cities | 257 | 257 | 0 |
| Mapped cities | 180 | 255 | +75 |
| Unresolved cities | 77 | 2 | -75 |
| `unmatched` cities | 0 | 0 | 0 |
| Total facilities | 1,557 | 1,557 | 0 |
| Facilities available on district pages | 1,158 | 1,552 | +394 |
| Facilities without a district | 399 | 5 | -394 |
| Existing district pages | 18 | 18 | 0 |
| Empty district pages | 1 | 0 | -1 |

All 75 approved rows were applied on the first pass. A second pass against the already processed copy made zero updates and reported all 75 rows as already mapped. This confirms idempotency and absence of duplicate assignments.

For the simulated mapping, audit provenance is stored only in the temporary copy as:

- `geo_match_status = manual_approved`;
- `geo_match_method = approved_mapping`;
- `geo_match_confidence = high`;
- `geo_requires_manual_review = false`.

## District impact

| AGS | District | Cities before | Cities after | City delta | Facilities before | Facilities after | Facility delta |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: |
| 12052 | Cottbus, Stadt | 0 | 1 | +1 | 0 | 72 | +72 |
| 12060 | Barnim | 12 | 16 | +4 | 93 | 124 | +31 |
| 12061 | Dahme-Spreewald | 15 | 19 | +4 | 81 | 96 | +15 |
| 12062 | Elbe-Elster | 11 | 18 | +7 | 58 | 78 | +20 |
| 12063 | Havelland | 14 | 15 | +1 | 94 | 97 | +3 |
| 12064 | Märkisch-Oderland | 14 | 22 | +8 | 70 | 107 | +37 |
| 12065 | Oberhavel | 12 | 17 | +5 | 99 | 112 | +13 |
| 12066 | Oberspreewald-Lausitz | 9 | 17 | +8 | 29 | 82 | +53 |
| 12067 | Oder-Spree | 16 | 20 | +4 | 77 | 97 | +20 |
| 12068 | Ostprignitz-Ruppin | 13 | 21 | +8 | 57 | 84 | +27 |
| 12069 | Potsdam-Mittelmark | 20 | 27 | +7 | 87 | 107 | +20 |
| 12070 | Prignitz | 12 | 13 | +1 | 73 | 74 | +1 |
| 12071 | Spree-Neiße | 1 | 12 | +11 | 15 | 79 | +64 |
| 12072 | Teltow-Fläming | 12 | 16 | +4 | 74 | 87 | +13 |
| 12073 | Uckermark | 16 | 18 | +2 | 100 | 105 | +5 |
| **Total affected** | **15 districts** | **—** | **—** | **+75** | **—** | **—** | **+394** |

No district records or district URLs were created. Cottbus, Stadt is the only district that changed from empty to populated in the simulation.

## REVIEW records skipped

| City ID | City | Facilities | Candidate | District | Result |
| ---: | --- | ---: | --- | --- | --- |
| 99 | Hennickendorf | 4 | Rüdersdorf bei Berlin (`12064428`) | Märkisch-Oderland | Not applied; remains manual review |
| 190 | Reichenberg, Märkische Heide | 1 | Märkische Höhe (`12064303`) | Märkisch-Oderland | Not applied; remains manual review |

These two records account for all 5 facilities that remain without a district. The dry run does not attempt to resolve them.

## Integrity checks

| Check | Result |
| --- | --- |
| Every approved city points to its CSV municipality AGS | PASS |
| Every municipality points to the CSV district AGS | PASS |
| One city has no more than one municipality relation | PASS |
| Facilities returned through district joins | 1,552 |
| Distinct facilities returned through district joins | 1,552 |
| Duplicate facilities on district pages | 0 |
| First mapping pass | 75 updated |
| Second mapping pass | 0 updated, 75 already mapped |
| REVIEW rows changed | 0 |
| Transaction used for each mapping pass | Yes |
| Temporary copy removed after normal run | Yes |
| Working SQLite SHA-256 unchanged | PASS |

Negative tests cover a duplicate city, unknown city, unknown municipality, wrong district relation, missing column, invalid status, wrong row count, and a conflicting existing mapping. Each case returns a failure and leaves the source database hash unchanged.

## Public pages, sitemap, and SEO

The isolated-copy integration check used the current routes and controllers after the simulated mapping.

| Check | Result |
| --- | --- |
| Cottbus city page | HTTP 200 |
| Cottbus district page | HTTP 200 with 72 facilities |
| City breadcrumb links to Cottbus district | PASS |
| District page links back to Cottbus city | PASS |
| District JSON geography identifies AGS `12052` | PASS |
| Linked city ordering on an affected district page | PASS |
| Existing city URLs | Unchanged |
| Existing facility URLs | Unchanged |
| Existing district URLs | Unchanged |
| Dynamic sitemap city set | 257 before / 257 after |
| Dynamic sitemap facility set | 1,557 before / 1,557 after |
| Dynamic sitemap district set | 18 before / 18 after |
| Added sitemap URLs | 0 |
| Removed sitemap URLs | 0 |
| Canonical URL inputs (routes and slugs) | Unchanged |

The mapping changes only the city-to-municipality relation on the isolated copy. It does not modify route definitions, slugs, canonical generation, breadcrumbs, JSON-LD templates, sitemap code, or SEO metadata.

## Errors and warnings

### Errors

None in the approved dry run.

### Warnings

- Two `REVIEW` records remain unresolved and must not be imported without a separate approval.
- The result is a simulation against the current local SQLite snapshot. Production must later use its own backup, validation, and explicitly approved import step; this sprint does not authorize production data changes.
- A non-empty SQLite WAL or journal blocks this file-copy dry run until a consistent snapshot is available.

## Reproduction

```text
php artisan geocore:apply-approved-mapping --dry-run
```

The optional `--keep-copy` flag exists only for local diagnostics and leaves the isolated copy below the system temporary directory. It is not used by the normal workflow.

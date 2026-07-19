# Directory Platform — Project Status

## Version

`Architecture Baseline v1.1`

## Current phase

Phase 2 — Platform architecture

## Current iteration

26.0 — Architecture Baseline v1.1

## Current project

PflegeIndex

## Implemented platform foundation

- GeoCore database hierarchy: Country → State → District → Municipality.
- Official Brandenburg GeoCore staging import and verified PflegeIndex city mapping.
- DirectoryCore contracts, immutable value objects and read models.
- PflegeIndex `EntryRepository` adapter and presentation adapter.
- `/pflegeheime.html` uses `ListEntries`.
- `/brandenburg/{city}.html` uses `ListEntries`.
- District and Brandenburg region facility listings use `ListEntries`.
- Typed City, District and State location scopes.
- Allowlist dependency protection for `app/Platform`.
- DirectoryCore is independent of Laravel and ready for validation by a second directory adapter.

## Tests

94 passed, 2351 assertions.

## Known architecture debt

- GeoCore Eloquent models are not physically separated under `app/Platform`.
- `GeoMunicipality::cities()` creates a reverse dependency on the PflegeIndex `City` model.
- Unmapped legacy cities still require a guarded State scope fallback.
- Region city and type aggregates still use legacy `state_slug` queries.
- Shared SEO and presentation abstractions have not been introduced.

## Next goal

Validate DirectoryCore with a second project adapter without changing the Platform API.

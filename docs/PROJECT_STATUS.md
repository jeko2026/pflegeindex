# Directory Platform — Project Status

## Version

`v0.4.0 Architecture Baseline`

## Current phase

Phase 2 — Platform architecture

## Current iteration

Iteration 5 — Architecture Baseline and Dependency Protection

## Current project

PflegeIndex

## Implemented platform foundation

- GeoCore database hierarchy: Country → State → District → Municipality.
- Official Brandenburg GeoCore staging import and verified PflegeIndex city mapping.
- DirectoryCore contracts, immutable value objects and read models.
- PflegeIndex `EntryRepository` adapter and presentation adapter.
- `/pflegeheime.html` uses `ListEntries`.
- `/brandenburg/{city}.html` uses `ListEntries`.
- Automated dependency protection for `app/Platform`.

## Tests

85 passed, 2307 assertions.

## Known architecture debt

- Region and district facility lists still query Eloquent directly.
- GeoCore Eloquent models are not physically separated under `app/Platform`.
- `GeoMunicipality::cities()` creates a reverse dependency on the PflegeIndex `City` model.
- Location criteria currently express a city slug but not explicit district/state scopes.
- Shared SEO and presentation abstractions have not been introduced.

## Next goal

Design a typed, neutral location scope before migrating district or region lists.

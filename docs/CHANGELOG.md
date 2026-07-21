# Changelog

All notable changes to the Directory Platform are documented in this file.

The project follows Semantic Versioning.

---

## [Unreleased]

### Added
- Added finalized minimal legal and project-information pages with public routes and footer navigation.
- Added a reproducible production package builder with split private-core, public-webroot and manifest outputs.

### Changed
- Documented actual public-page, admin-session and `/up` external-resource behavior in the privacy notice.
- Excluded the unused zero-byte ICO file from production packages; the active SVG favicon remains included.

---

## [1.0.0-rc.1] - 2026-07-19

PflegeIndex v1.0 Release Candidate baseline.

### Added
- Added the framework-independent DirectoryCore Platform API.
- Added typed City, District and State location scopes.
- Added official Brandenburg GeoCore staging import and city mapping.
- Added a minimal FuneralIndex adapter as proof of the unchanged multi-project API.
- Added allowlist architecture tests for the Platform dependency boundary.

### Changed
- Migrated directory, Brandenburg State, District and City facility listings to `ListEntries`.
- Standardized project-specific repository and presentation adapters without changing public URLs or HTML.
- Froze the documented Platform API for the v1.0 Release Candidate.

### Public foundation
- State, District, City and facility pages.
- Search, filtering, stable sorting and pagination.
- Canonical metadata, Open Graph, JSON-LD, breadcrumbs, robots and XML Sitemap.
- Administrative contact verification and protected import behavior.

### Tests
- 97 tests passed.
- 2365 assertions.

### Known limitations
- The accepted v1.0 constraints are documented in `KNOWN_LIMITATIONS.md`.

---

## [0.4.0] - 2026-07-19

### Added
- Added the PflegeIndex presentation adapter for DirectoryCore entry cards.
- Added automated dependency protection for PHP files inside `app/Platform`.

### Changed
- Migrated `/pflegeheime.html` to `ListEntries` through `PflegeEntryRepository`.
- Migrated `/brandenburg/{city}.html` to the same DirectoryCore listing flow.
- Rebuilt Laravel pagination outside DirectoryCore while preserving public HTML, URLs, filters and SEO.
- Updated the architecture baseline and roadmap to match the implemented code.

### Architecture
- DirectoryCore remains independent of Laravel, Eloquent and PflegeIndex.
- GeoCore remains implemented through Eloquent models and Brandenburg-specific import tooling.
- The reverse `GeoMunicipality::cities()` dependency is documented as known debt and is not changed in this version.

### Tests
- 85 tests passed.
- 2307 assertions.

---

## [0.3.0] - 2026-07-19

### Added
- Introduced the first Platform module: **DirectoryCore**.
- Added immutable domain objects:
  - EntryIdentifier
  - EntrySort
  - PaginationOptions
- Added read models:
  - ListingCriteria
  - EntrySummary
  - ListingResult
- Added EntryRepository contract.
- Added ListEntries use case.
- Added PflegeEntryRepository project adapter.
- Added unit and integration tests for DirectoryCore.
- Added ADR: Project adapters instead of a universal Entry model.

### Changed
- Established Platform → Project adapter architecture.
- Preserved full backward compatibility with existing production pages.

### Tests
- 74 tests passed.
- 2201 assertions.

---

## [0.2.0] - 2026-07-18

### Added
- Brandenburg region pages.
- City pages.
- XML Sitemap.
- Open Graph metadata.
- Schema.org JSON-LD.
- Breadcrumbs.
- GitHub repository.
- Automated tests for SEO and routing.

---

## [0.1.0] - 2026-07-14

### Added
- Documentation structure.
- Platform vision.
- Development principles.
- Initial architecture.
- Codex development rules.
- Task specification template.
- Initial project roadmap.

---

## Project Status

**Current Version:** 1.0.0-rc.1

**Status:** Release Candidate — Production Preparation

**Last Update:** 2026-07-19

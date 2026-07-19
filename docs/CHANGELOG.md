# Changelog

All notable changes to the Directory Platform are documented in this file.

The project follows Semantic Versioning.

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

**Current Version:** 0.3.0

**Status:** Active Development

**Last Update:** 2026-07-19
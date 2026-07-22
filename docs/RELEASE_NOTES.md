# PflegeIndex v1.0

Status: **Release Candidate (`v1.0.0-rc.2`) — technical remediation complete,
final production verification pending**

This document records the official baseline before final production
preparation. It does not declare the production deployment complete.

## Highlights

- Directory Platform with a framework-independent DirectoryCore.
- Frozen v1.0 Platform API built around contracts, typed value objects, read
  models and the `ListEntries` use case.
- PflegeIndex repository and presentation adapters separated from Platform.
- Multi-project architecture proven by a minimal FuneralIndex adapter without
  changing the Platform API.
- Official Brandenburg GeoCore hierarchy and controlled city mapping.
- Directory, State, District and City facility listings through one
  DirectoryCore flow.
- Public facility detail pages with stable descriptive URLs.
- Search, filters, stable sorting and pagination.
- SEO foundation with canonical metadata, Open Graph, JSON-LD, breadcrumbs,
  robots and XML Sitemap.
- Allowlist architecture tests protecting Platform dependency direction.
- GeoCore coverage of 255 of 257 cities and 1,552 of 1,557 facilities across
  all 18 Brandenburg district pages.
- Hardened pagination, parser URL validation, plain-text `/up`, daily log
  rotation and a shared static Open Graph image.
- Automated application baseline of 202 tests and 3,070 assertions before the
  remediation sprint; the final count is recorded in
  `docs/PRODUCTION_BLOCKER_REMEDIATION.md`.

## Compatibility

The Release Candidate preserves the existing public URLs, HTML presentation,
SEO metadata and PflegeIndex behavior. No production data migration is part of
this documentation baseline.

## Release status

Architecture, DirectoryCore, GeoCore, automated testing and the reproducible
split-package process are ready for final release verification. The 2026-07-22
production certification returned 86% and a conditional NO-GO because the live
host still serves the previous release and operator-controlled legal/hosting
facts are incomplete. Sprint 3.5.1 resolves repository, formatting, packaging
and documentation blockers; it does not perform production deployment.

Before GO, the operator must confirm the facts listed in
`LEGAL_HOSTING_FACTS_CHECKLIST.md`, apply the canonical nginx redirect, create
and verify the production backup, deploy the approved artifact and complete
`RELEASE_VERIFICATION.md`.

Known constraints accepted for v1.0 are documented in
`KNOWN_LIMITATIONS.md`.

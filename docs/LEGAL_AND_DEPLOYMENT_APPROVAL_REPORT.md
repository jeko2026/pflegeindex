# Legal and Deployment Approval Report

**Assessment date:** 2026-07-22

**Decision:** **CONDITIONAL GO**

**Overall readiness:** **88%**

Production deployment was not performed. The application and release package
remain technically ready, but provider, mailbox, backup and operator evidence
is insufficient for unconditional legal/deployment approval.

## Git and Release State

- Branch: `main`.
- Documentation commit
  `658756eaf68d0233b55ecfe86e9f30a6d3b0649b` was pushed by a normal
  fast-forward push: `fd01a71..658756e main -> main`.
- Immediately after push, local `main` and `origin/main` matched and the
  worktree was clean.
- Release candidate: three verified `fd01a71f` archives.
- Application source commit:
  `fd01a71fb5084c22aea83ba6d50bcc00b7769f72`.
- Production deployment and production database changes: not performed.

## Confirmed Application Facts

The 2026-07-22 repository, configuration, asset and Feature-test audit
confirmed:

- public routes are sessionless and do not set Laravel cookies;
- the protected administrator area uses only `pflegeindex-session` and
  `XSRF-TOKEN` under the tested production cookie configuration;
- the administrator session has a configured 120-minute lifetime, encryption,
  Secure, HttpOnly and SameSite=Lax flags;
- the CSRF cookie has a configured 120-minute lifetime, Secure and
  SameSite=Lax flags and is intentionally not HttpOnly;
- no “Angemeldet bleiben” mode is offered, even though the default user schema
  contains a dormant `remember_token` column;
- no public contact form exists; search/filter forms are GET requests;
- contact and correction reporting use mailto links; the correction mail
  pre-fills facility name and current page URL;
- Laravel application logs rotate daily with 30-day retention;
- `/up` is local plain text and does not load Bunny Fonts, jsDelivr or another
  external resource;
- active public layouts use local CSS, JavaScript, logos, fonts/system fonts
  and Open Graph image;
- no application analytics, advertising, tracking, external monitoring or
  public third-party API client is active.

The full fact/source/status/action matrix is in
`docs/LEGAL_HOSTING_FACTS_CONFIRMED.md`.

## External Resource Audit

Blade, active layouts, CSS, JavaScript, public assets, middleware, routes and
representative legal-page responses were reviewed.

| Category | Result | Behavior |
| --- | --- | --- |
| Google Fonts / Bunny Fonts | NOT USED | No active remote font request |
| Google Maps | CLICKED LINK ONLY | Facility page builds a link; no iframe, tile or automatic request |
| OpenStreetMap | NOT USED | No embed, tile or API request |
| CDN | NOT USED BY APPLICATION | No active CDN URL; provider-layer CDN remains UNKNOWN |
| Analytics / tag manager | NOT USED | No package, script or endpoint found |
| Tracking pixels / advertising | NOT USED | No active marker found |
| External scripts/stylesheets | NOT USED | Active layouts load local assets |
| External images | NOT AUTO-LOADED | Logos and OG image are local |
| Third-party APIs | NOT USED BY PUBLIC FRONTEND | No application-initiated request found |
| Facility/provider/source links | USED AFTER CLICK | Browser contacts the selected destination only after user action |

The unused Laravel starter `welcome.blade.php` contains framework links, but no
route references that view and it is not rendered by the current application.
It therefore causes no visitor request or data transfer.

No previously unknown automatically loaded external service was found, so the
application frontend adds no new legal blocker.

## Cookies and Sessions

| Cookie/category | Purpose | Lifetime | Flags | Status / basis |
| --- | --- | --- | --- | --- |
| Public page cookie | None | — | — | NOT USED |
| `pflegeindex-session` | Protected administrator authentication/session | 120 minutes configured | Secure; HttpOnly; SameSite=Lax; encrypted | Strictly necessary; § 25(2)(2) TDDDG and Art. 6(1)(f) GDPR |
| `XSRF-TOKEN` | CSRF protection for administrator forms | 120 minutes configured | Secure; SameSite=Lax; not HttpOnly | Strictly necessary; § 25(2)(2) TDDDG and Art. 6(1)(f) GDPR |
| Remember cookie | None | — | — | NOT USED |
| Analytics cookie | None | — | — | NOT USED |
| Marketing cookie | None | — | — | NOT USED |
| Third-party cookie | None set by application | — | — | NOT USED; live provider injection still requires verification |

The database session record can contain its identifier, administrator user ID,
IP address, user-agent, encrypted session payload and last-activity timestamp. Live
cookie headers must be reconfirmed after production config caching.

## Datenschutzerklärung Changes

`resources/views/pages/privacy.blade.php` was aligned with confirmed technical
behavior:

- removed the obsolete Bunny Fonts/jsDelivr `/up` statement;
- separated Hosting, Server-Logfiles, Rechtsgrundlagen and Speicherdauer;
- documented GET search/filter parameters;
- documented mailto-only contact and correction prefill behavior;
- documented exact configured administrator cookies and session data;
- documented the absence of automatically loaded external resources;
- added data-subject rights, complaint right, SSL/TLS and change notice;
- removed the unsupported promise that e-mail is always deleted immediately
  when an inquiry is completed;
- explicitly identifies material hosting/mailbox/retention facts that remain
  unconfirmed instead of inventing them.

The page is technically aligned but not legally complete while those material
UNKNOWN facts remain. Its existing `noindex,nofollow`, canonical URL, title and
description were preserved.

## Impressum Assessment

The Impressum was reviewed without changing personal information.

Confirmed in the page:

- operator block and postal-address structure;
- `info@pflegeindex.com` contact link;
- responsible-content block;
- independent non-official project description;
- no quality guarantee or unconfirmed commercial-operator claim;
- canonical `.html` route, noindex meta and footer link.

Remaining evidence gap:

- `Yevhenii V.` appears abbreviated rather than a complete legal name;
- the operator address and current legal status were not directly reconfirmed
  in this sprint;
- mailbox availability/provider is not evidenced by repository content.

These details must be confirmed by the owner. No personal data was guessed or
changed.

## Legal Smoke Test

The canonical legal routes are `/impressum.html` and `/datenschutz.html`.
Feature tests confirm HTTP 200, title, description, canonical, noindex meta,
required content and footer links. The extensionless paths `/impressum` and
`/datenschutz` are not project routes; no route change was made because this
sprint prohibits route changes.

The pages use the shared responsive layout and local responsive stylesheet, so
desktop and mobile receive the same legal content. No TODO, known placeholder,
obsolete external-service claim or contradictory public/session statement was
found after the update.

## Material UNKNOWN Facts

Unconditional legal and deployment approval still requires documentary
answers for:

1. hosting provider legal name and address;
2. server/data-centre country and hosting subprocessors;
3. AV-Vertrag availability and signature status;
4. exact server access/error-log fields and retention;
5. mailbox provider, mail-server countries, forwarding and retention;
6. actual backup locations, retention, encryption and restore evidence;
7. provider-level analytics, CDN, reverse proxy, WAF and monitoring;
8. production paths/permissions and SQLite deployment scenario;
9. full operator identity/address/legal-status confirmation;
10. explicit owner legal and deployment approval.

No answer was inferred from a public header or repository default.

## Verification Results

| Check | Result |
| --- | --- |
| Focused `LegalPagesTest` | PASS — 8 tests, 105 assertions |
| `php artisan test` | PASS — 203 tests, 3,088 assertions |
| `composer validate --strict` | PASS |
| `composer audit --locked --no-interaction` | PASS — no security advisories |
| `vendor/bin/pint --test` | PASS |
| `git diff --check` | PASS before final report formatting |

The new regression coverage checks the privacy sections, removal of obsolete
external-service claims, absence of public cookies and the exact Secure,
HttpOnly and SameSite behavior of both administrator cookies.

## Decision

**CONDITIONAL GO — production deployment is not yet authorized.**

Application behavior is technically documented and no new frontend privacy
service was discovered. Readiness is assessed at **88%** because all automated
and application checks pass, while several legally material provider/operator
facts and operational deployment controls remain pending. Convert every
launch-relevant `UNKNOWN`/`PENDING` item in
`docs/LEGAL_HOSTING_FACTS_CONFIRMED.md` and
`docs/DEPLOYMENT_APPROVAL.md` to evidence-backed `CONFIRMED`/`PASS` before
issuing `GO FOR DEPLOYMENT`.

# PflegeIndex Release Verification

Release/commit: ____________________

Environment and date: ____________________

Verified by: ____________________

## Public pages

- [ ] **Главная:** `/` returns HTTP 200, uses HTTPS canonical URL and loads local assets.
- [ ] **Brandenburg:** `/brandenburg.html` lists only Brandenburg content and paginates correctly.
- [ ] **City:** open at least one populated City page and verify its facility count, cards and canonical URL.
- [ ] **Facility:** open at least one Facility page and verify city binding, address, contact visibility and related entries.
- [ ] **Sitemap:** `/sitemap.xml` returns valid XML with production HTTPS URLs and no admin URLs.
- [ ] **Robots:** `/robots.txt` returns text content and references the production sitemap.

## Administration and contact

- [ ] **Login:** valid administrator credentials work over HTTPS; an invalid login is rejected.
- [ ] **Admin:** guests cannot open protected admin routes; the dashboard opens for an administrator.
- [ ] **Contact:** public contact links render correctly and the protected contact-review page opens for an administrator.

## Legal pages

- [ ] **Impressum:** real operator name, complete address, responsible person and contact details are present; no placeholders remain.
- [ ] **Datenschutz:** controller details and applicable hosting, log, session and external-resource processing are described; no draft warning remains.
- [ ] **Über das Projekt:** the directory is identified as independent and non-official, and its data limitations are stated.

## Automated and SEO verification

- [ ] **PHPUnit:** the complete test suite passes for the deployed commit.
- [ ] **SEO:** title, meta description and canonical are correct on Home, Brandenburg, City and Facility pages.
- [ ] **SEO:** expected Open Graph and JSON-LD are valid where implemented.
- [ ] **SEO:** `www` redirects permanently to `https://pflegeindex.com`.

## Operations

- [ ] `/up` returns HTTP 200.
- [ ] The separately documented external resources used by Laravel's `/up` status view are still accurate.
- [ ] `APP_ENV=production`, `APP_DEBUG=false` and the production `APP_URL` are confirmed without exposing secrets.
- [ ] Laravel configuration, route and view caches were rebuilt.
- [ ] No new critical errors appear in Laravel or hosting logs.
- [ ] The pre-deployment SQLite backup and previous commit hash are recorded and protected.
- [ ] Maintenance mode is disabled.

Result: [ ] approved  [ ] rollback required

Notes:

______________________________________________________________________________

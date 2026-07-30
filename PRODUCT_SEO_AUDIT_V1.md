# PflegeIndex Product & SEO Audit v1.0

Дата аудита: 28.07.2026
Ветка: `pi-2`
HEAD: `86fe192 Admin: add data quality dashboard`

## 1. Executive Summary

Текущая версия выглядит готовой для продолжения разработки и ограниченного production rollout, но не для объявления полной SEO-фазы завершённой без двух архитектурных решений: устранения двух параллельных систем оценки качества и планирования масштабирования sitemap/dashboard.

Итоговая рекомендация: **CONDITIONAL GO**.

Обоснование:

- P0: 0.
- P1: 1 подтверждённая проблема — на странице учреждения одновременно отображаются два разных quality-показателя с разными формулами.
- P2: 4 подтверждённых риска — масштабирование расчётов и sitemap, потенциально невалидные URL в Schema/ссылках, отсутствие явной secure-cookie настройки в шаблоне окружения.
- P3: 2 улучшения — CSP и фактические Lighthouse/Search Console измерения.
- Полный тестовый набор проходит: **264 теста, 3483 assertions, failures 0, skipped 0, 75.43 s**.
- Blade cache собирается успешно.

Аудит был read-only. Код, шаблоны, CSS, маршруты, конфигурация и база данных не изменялись.

## 2. Current Product Status

| Область | Состояние |
|---|---|
| Публичный каталог | Рабочий, покрыт feature-тестами |
| Brandenburg/Bundesland | Реализован как основной регион продукта |
| Landkreis и City | Есть отдельные маршруты, breadcrumbs, pagination и Schema.org |
| Facility | Есть контакты, источник, дата проверки, trust layer, Quality Score, related facilities |
| Админка | Сессия, `auth` и `admin` middleware, CSRF для форм |
| Data Quality Dashboard | Доступен только администратору на `/admin/data-quality` |
| Sitemap/robots | Есть `/sitemap.xml` и `/robots.txt` |
| Health endpoint | `/up`, без технических деталей в ответе |
| Миграции | Локально все миграции имеют статус `Ran` |
| Production измерения | Не выполнялись; Lighthouse/PageSpeed/Search Console недоступны в read-only локальном аудите |

## 3. Что уже сделано хорошо

- Чётко разделены публичные маршруты и admin-группа с `auth`/`admin` middleware.
- Админские страницы имеют `noindex,nofollow`; middleware дополнительно ставит `X-Robots-Tag` для `/admin/*`.
- Фильтры каталога получают `noindex,follow`, а обычные страницы и pagination имеют отдельные canonical URL.
- Невалидные номера страниц дают 404; страницы за последней страницей также дают 404, что снижает риск soft 404.
- Public breadcrumbs и BreadcrumbList Schema.org присутствуют на City, Landkreis и Facility страницах.
- Facility Schema.org не выводит валидные contact-поля, если они отсутствуют; e-mail проверяется перед `mailto`.
- Источники контактов валидируются серверными правилами; импорт review pack проверяет source и не применяет неподтверждённые строки.
- Health endpoint возвращает нейтральный `OK`/`ERROR`, не раскрывая exception details.
- QualityScoreService централизует индивидуальный score и агрегаты; City расчёт выполняется чанками по 500.
- Кэш качества города имеет city-specific key и сбрасывается при `saved`/`deleted` Facility.
- Deployment-конфигурации содержат canonical HTTPS redirect и cache headers для статических assets.
- Полный suite подтверждает текущую регрессионную стабильность.

## 4. Findings

### P1-001 — Две параллельные системы качества на Facility page

- Severity: P1
- Confidence: Confirmed
- Impact: Trust, UX, Maintainability, SEO
- Effort: M
- Files: `resources/views/facilities/show.blade.php:91,212`, `app/Projects/PflegeIndex/Trust/FacilityDataQuality.php`, `app/Services/QualityScoreService.php`

`show.blade.php` одновременно вычисляет старый `FacilityDataQuality::evaluate(...)` и новый `QualityScoreService`. На странице рендерятся оба блока. Формулы, веса и набор критериев отличаются: старый блок учитывает описание, координаты, official data, canonical и ошибки; новый Quality Score использует телефон, e-mail, website, адрес, источник и verification status.

Результат — пользователь может увидеть два разных процента качества для одной записи, а Dashboard и City используют только новую формулу. Это уже не теоретический риск, а подтверждённая архитектурная неоднозначность.

Рекомендация: в отдельном спринте выбрать одну публичную систему. Предпочтительно оставить `QualityScoreService` платформенным источником истины, а старый completeness-блок либо переименовать в явно отдельный внутренний показатель, либо удалить после обновления regression tests.

Сейчас исправлять без отдельного задания не требуется: изменение затрагивает публичный UI и существующие тестовые ожидания.

### P2-001 — Dashboard остаётся O(N) на каждый запрос

- Severity: P2
- Confidence: Confirmed
- Impact: Performance, Scalability, Maintainability
- Effort: M
- Files: `app/Services/DataQualityDashboardService.php:14-70`

Dashboard читает все учреждения чанками и для каждого вызывает `QualityScoreService::evaluate`. Chunking предотвращает одновременную загрузку всей выборки, но общий объём работы всё равно линейный для каждого открытия страницы. При текущем масштабе это приемлемо, при десятках тысяч учреждений — заметный admin bottleneck.

Рекомендация: добавить кэш общей Dashboard-статистики с TTL 1–6 часов и инвалидировать его вместе с city quality cache. Для фильтрованной таблицы оставить ограниченную выборку/пагинацию; не переносить формулу score в SQL.

### P2-002 — Sitemap материализует весь каталог в память без application cache

- Severity: P2
- Confidence: Confirmed
- Impact: SEO, Performance, Scalability
- Effort: M
- Files: `app/Http/Controllers/SitemapController.php:15-24,28-35`

Каждый запрос sitemap загружает все Brandenburg cities и eager-load всех facilities, затем строит XML. У ответа есть `Cache-Control: max-age=3600`, но это только HTTP-кэш клиента/proxy; серверная генерация всё равно выполняется при cache miss и не защищена от больших выборок.

Рекомендация: перейти на server-side cache/XML snapshot с TTL около часа или потоковую генерацию с bounded batches. Проверить размер sitemap при расширении за пределы Brandenburg и при необходимости разделить sitemap index.

### P2-003 — Невалидный website может попасть в Schema.org `sameAs`

- Severity: P2
- Confidence: Likely, подтверждено кодом пути вывода
- Impact: SEO, Data correctness
- Effort: S
- Files: `resources/views/facilities/show.blade.php:7,120`

`sameAs` добавляется при любом `filled($displayWebsite)`, тогда как валидность URL проверяется для некоторых contact actions, но не перед формированием Schema.org. Нормальный admin/import flow отбрасывает многие плохие URL, однако старые или вручную загруженные записи могут оставить строку, которая не является абсолютным HTTP(S) URL.

Рекомендация: передавать в `sameAs` только URL, прошедший общий `HttpUrl` policy. Не объявлять фактический production defect без выборочной проверки базы.

### P2-004 — `description_sources` выводятся как ссылки без повторной URL-проверки в Blade

- Severity: P2
- Confidence: Confirmed defense-in-depth gap
- Impact: Security, Trust, Maintainability
- Effort: S
- Files: `resources/views/facilities/show.blade.php:244-251`

Значения экранируются HTML, но `$source` напрямую используется в `href`. Сейчас контроллеры и импорт имеют URL validation, поэтому риск зависит от исторических данных или будущего write path. Для источников, отображаемых публично, безопаснее применять тот же общий HTTP(S) policy при выводе.

Рекомендация: фильтровать источники через `HttpUrl::isValid`/normalization до рендера и не выводить небезопасные схемы.

### P2-005 — Secure session cookie не закреплён в deployment template

- Severity: P2
- Confidence: Confirmed configuration gap, production impact requires measurement
- Impact: Security
- Effort: XS
- Files: `config/session.php:172`, `.env.example`

`SESSION_SECURE_COOKIE` читается из окружения, но в `.env.example` нет явной production-настройки. При ошибочной конфигурации shared hosting админская session cookie может оказаться без `Secure` flag, несмотря на canonical HTTPS redirect.

Рекомендация: в production deployment checklist явно требовать `SESSION_SECURE_COOKIE=true`, HTTPS и проверку `HttpOnly`/`SameSite=Lax`. Это не исправлялось в рамках аудита.

### P2-006 — Региональная модель пока жёстко привязана к Brandenburg

- Severity: P2
- Confidence: Confirmed by architecture
- Impact: SEO, Scalability, Maintainability
- Effort: L
- Files: `routes/web.php`, `app/Http/Controllers/RegionController.php`, `app/Http/Controllers/SitemapController.php`, public Blade templates

Маршруты, metadata, breadcrumbs, sitemap filters и `BRANDENBURG_STATE_IDENTIFIER` явно используют Brandenburg. Это корректно для текущего продукта, но не является готовым механизмом масштабирования на всю Германию.

Рекомендация: до Germany expansion выделить region/state scope в конфигурацию или domain service. Не начинать такую миграцию в текущем sprint без реального product scope.

### P3-001 — CSP отсутствует

- Severity: P3
- Confidence: Confirmed
- Impact: Security, Maintainability
- Effort: M
- Files: `app/Http/Middleware/SecurityHeaders.php`

Есть `nosniff`, frame, referrer и permissions headers, но нет Content-Security-Policy. Добавление CSP потребует проверки inline scripts, analytics, external maps и текущих Blade templates.

Рекомендация: сначала собрать report-only CSP на staging, затем ужесточать после проверки analytics и inline scripts.

### P3-002 — Core Web Vitals не измерены

- Severity: P3
- Confidence: Requires measurement
- Impact: Performance, SEO, Conversion
- Effort: S
- Files: production environment, no code finding established

Локальный аудит не может подтвердить LCP, CLS или INP. По коду видны versioned CSS/JS, dimensions у ключевых logo assets и responsive CSS, но это не заменяет Lighthouse/PageSpeed/Search Console.

Рекомендация: снять mobile/desktop Lighthouse и реальные field metrics после staging/production deployment.

## 5. Page-by-page audit

### Homepage

- Есть title, description, canonical, Organization/WebSite/FAQ Schema.org.
- Есть переходы в каталог, Brandenburg, города и тематические страницы.
- Тесты homepage и metadata проходят.
- Требуется production measurement для performance; P3-002.

### Facility page

- H1 использует название учреждения и title включает город.
- Есть адрес, тип, contact actions, source/date trust layer, correction mailto и related facilities.
- LocalBusiness и BreadcrumbList Schema.org присутствуют.
- Отсутствующие e-mail/phone/website не выводятся как пустые ссылки в tested paths.
- Основной продуктовый риск — P1-001: одновременно отображаются старый completeness score и новый Quality Score.
- Невалидный `sameAs` и source URL требуют defense-in-depth (P2-003/P2-004).

### City

- Есть title/description/H1, breadcrumbs, CollectionPage и BreadcrumbList.
- Есть city Quality Score, pagination, nearby cities и district link.
- Пустой listing обрабатывается тестами; страницы вне диапазона дают 404.
- City quality cache имеет city-specific key и invalidation on Facility save/delete.

### Landkreis/Bundesland

- Есть district/city navigation, counts, breadcrumbs, CollectionPage/ItemList-like data and pagination.
- Brandenburg scope последователен, но hard-coded для текущего географического продукта (P2-006).

### Search/filter/pagination

- GET filters работают через обычные формы.
- Filtered directory pages получают `noindex,follow`.
- Pagination canonical включает page number; malformed/out-of-range pages дают 404.
- Query parameters не меняют canonical на filtered URL.

### Admin

- Админская группа закрыта `admin-session`, `auth`, `admin`.
- CSRF включён для admin session group.
- Admin layout содержит meta noindex; middleware добавляет X-Robots-Tag.
- Data Quality Dashboard не создаёт CRUD и использует только GET-фильтры.

### Sitemap/robots/health/legal

- Sitemap и robots существуют, у обоих есть one-hour cache headers.
- `/up` sessionless и скрыт от роботов через X-Robots-Tag.
- Impressum и Datenschutz существуют и имеют noindex.
- Sitemap memory/scaling risk отмечен как P2-002.

## 6. Mobile UX и Accessibility

Подтверждено кодом и regression tests:

- responsive breakpoints есть для public и admin CSS;
- mobile contact actions имеют отдельную навигацию;
- таблицы обёрнуты в overflow containers в admin;
- формы используют labels и explicit input ids;
- есть focus styles и aria labels для breadcrumbs, progress bars и nav;
- изображения имеют alt или `aria-hidden` для декоративных SVG.

Не подтверждено фактическим браузерным измерением:

- отсутствие горизонтального overflow на всех комбинациях реальных данных;
- keyboard traversal и screen-reader announcement;
- фактический contrast ratio на production palette;
- LCP/CLS/INP.

## 7. E-E-A-T и Trust

Сильные стороны:

- LASV official base data объясняется публично;
- contact source и checked date отображаются при documented review;
- есть correction flow на `info@pflegeindex.com`;
- public copy отделяет official base data от editorial additions;
- verified status ограничен серверной валидацией и не подменяет источник.

Риск: два публичных quality-показателя могут подрывать понятность trust layer (P1-001). Это продуктовый риск, а не доказательство недостоверности данных.

## 8. Performance and scalability

- Public listing использует pagination 24 и repository/presenter без N+1 по карточкам.
- City quality использует chunking по 500 и не загружает весь набор одновременно.
- Dashboard использует chunking, но делает полный линейный проход при каждом открытии (P2-001).
- Sitemap eager-load всего каталога (P2-002).
- Статические CSS/JS имеют immutable cache headers в deployment templates.
- Реальные CWV не измерялись; заявлять числовые LCP/CLS/INP нельзя.

## 9. Security and technical quality

Подтверждено:

- admin auth/authorization и CSRF;
- escaped Blade output для имен и пользовательских значений;
- server-side validation absolute HTTP URLs;
- health endpoint не раскрывает exception details;
- production data не менялась в ходе аудита;
- read-only тесты не применяли review imports.

Риски/улучшения:

- secure session cookie должен быть явно закреплён в production env (P2-005);
- CSP отсутствует (P3-001);
- public source href и Schema sameAs нуждаются в defense-in-depth URL filtering (P2-003/P2-004).

Это не penetration test и не заменяет dependency scanning, Lighthouse или внешнюю security review.

## 10. Data Quality Architecture audit

1. Дублирование логики есть: старый `FacilityDataQuality` и новый `QualityScoreService` одновременно участвуют в Facility UI. Для City и Dashboard новая формула централизована в `QualityScoreService`.
2. Скрытой связи «данные → UI» не обнаружено: Facility controller передаёт `qualityScore`, City controller — cached aggregate, Dashboard controller — service DTO.
3. Решение масштабируется до десятков тысяч записей по памяти благодаря chunking, но Dashboard и sitemap остаются O(N) по CPU/IO; нужны кэш/snapshot перед большим ростом.
4. Bottleneck: Dashboard full scan и sitemap eager-load; вторичный bottleneck — repeated city/district aggregate queries при отсутствии cache на всех страницах.
5. Немедленно исправлять production blocker не требуется. До следующей фазы рекомендуется решить P1-001 и добавить измерения/кэш для P2-001/P2-002.
6. Phase Data Quality нельзя считать полностью завершённой из-за P1-001 и отсутствия production measurements. Её можно считать функционально реализованной и тестируемой.

Trust Index, Freshness Score и новые веса не рекомендуются до накопления дат проверок и распределения score.

## 11. Recommended roadmap

### Immediate fixes

1. **P1-001 (M):** выбрать публичный источник quality score и убрать/переименовать второй показатель.
2. **P2-005 (XS):** закрепить `SESSION_SECURE_COOKIE=true` в production deployment checklist.
3. **P2-003/P2-004 (S):** использовать единый URL policy перед Schema `sameAs` и public source links.

### Sprint 10

- Добавить кэш общей Dashboard-статистики на 1–6 часов.
- Пересмотреть sitemap generation: cache/snapshot или streamed batches.
- Добавить automated tests для invalid URL defense-in-depth.

### Sprint 11

- Провести Lighthouse mobile/desktop и Search Console crawl review.
- Проверить Germany expansion scope и решить, нужна ли region abstraction.

### Later backlog

- CSP report-only → enforcing.
- Системный accessibility review с keyboard/screen reader.
- Sitemap index при росте каталога.
- Trust/Freshness только после подтверждённой потребности и достаточного массива review dates.

## 12. Top 10 improvements by impact

1. Устранить два quality score на Facility page.
2. Включить server-side cache Dashboard.
3. Кэшировать/стримить sitemap.
4. Защитить Schema `sameAs` общим URL validator.
5. Валидировать public description source URLs при выводе.
6. Зафиксировать secure session cookie в production.
7. Провести Lighthouse и Search Console crawl audit.
8. Провести клавиатурный и screen-reader review.
9. Подготовить CSP report-only.
10. Зафиксировать план region abstraction до расширения за Brandenburg.

## 13. Suggested Sprint 10

**Sprint 10 — Quality Signals Consolidation & Production Measurement**

- убрать неоднозначность между completeness и Quality Score;
- добавить Dashboard cache/invalidation;
- harden URL output paths;
- измерить Lighthouse/CWV/Search Console;
- не менять веса score и не вводить Trust/Freshness Index.

## 14. Items requiring production measurement

- Lighthouse: mobile и desktop для homepage, directory, city, facility;
- Core Web Vitals: LCP, CLS, INP, TTFB;
- Search Console: indexed URLs, crawl errors, duplicate/canonical warnings, soft 404;
- sitemap response time/size и cache hit ratio;
- Dashboard response time при текущем production dataset;
- session cookie flags после HTTPS deployment;
- keyboard/screen-reader usability на реальных браузерах.

## 15. Test Results

Команда: `php artisan test`
Результат: **264 passed, 3483 assertions, 0 failures, 0 skipped**
Время: **75.43 s**

Дополнительно:

- `php artisan route:list --except-vendor` — 34 маршрута, включая `/admin/data-quality`;
- `php artisan migrate:status` — все локальные миграции `Ran`;
- `php artisan view:cache` — успешно;
- `php artisan about` — локальное окружение, SQLite, `APP_DEBUG=enabled`, views cached;
- production database/import commands не запускались.

## 16. Git state and scope

Состояние до и после аудита одинаково по смысловым файлам:

- ветка: `pi-2`;
- HEAD: `86fe192`;
- staging не использовался;
- commit не создавался;
- push не выполнялся;
- production code/database не изменялись.

Незакоммиченные файлы, оставленные без изменений и вне staging:

- `config/lexicon.php`;
- `config/lexicon-additional.php`;
- `app/Console/Commands/DataQualityCompleteBatchCommand.php`;
- `app/Console/Commands/DataQualityFullAuditCommand.php`;
- `app/Console/Commands/DataQualityImportReviewPackCommand.php`;
- `app/Console/Commands/DataQualityNextBatchCommand.php`;
- `app/Console/Commands/DataQualityPrepareReviewPacksCommand.php`;
- `app/Services/DataQuality/BatchManager.php`;
- `app/Services/DataQuality/DuplicateCandidateTriage.php`;
- `app/Services/DataQuality/FullDataAudit.php`;
- `app/Services/DataQuality/ReviewPackImportService.php`;
- `app/Services/DataQuality/ReviewPackPreparer.php`;
- соответствующие незакоммиченные Data Quality tests.

## 17. Final Verdict

**CONDITIONAL GO** — текущая версия функционально стабильна и тестируемая, но перед завершением фазы необходимо убрать двусмысленность публичного Quality Score и запланировать кэширование/sitemap scalability. Новые Trust Index/Freshness Score и расширение на всю Германию сейчас не рекомендуются.

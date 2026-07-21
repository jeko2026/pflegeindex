# Privacy & Data Processing Audit

Audit date: 2026-07-21

Repository state reviewed: `main` at `519bcc3`

Scope: Laravel application, tracked configuration templates, migrations, Blade views, application code, tests, deployment and operations documentation.

Method: static, read-only technical review. The real `.env`, production database contents, hosting control panel, mailboxes and provider dashboards were not inspected. No secret values were read or reproduced.

This is a technical data-processing inventory, not a final legal assessment and not a replacement for advice on the GDPR, TTDSG or applicable Ukrainian/German law.

## 1. Executive Summary

PflegeIndex currently has a comparatively small public data-collection surface:

- public visitors can use only GET-based search and filters;
- public searches are processed for the response and are not explicitly persisted by application code;
- ordinary public pages do not start a Laravel session and are covered by a regression test asserting that the home page does not set a cookie;
- there is no analytics, advertising, tracking pixel, captcha, embedded map, public account, review, owner-claim or correction-submission backend;
- `Datenfehler melden` is a `mailto:` link, not a server-side form;
- authentication and all database-writing HTML forms are limited to the protected admin area.

Personal or linkable technical data can nevertheless be processed in four places:

1. the hosting/web-server layer may receive IP address, request time, URL including query parameters, referrer and user-agent;
2. admin authentication uses a database session containing `user_id`, IP address, user-agent, payload and last activity, plus a necessary session cookie;
3. the SQLite database contains administrator account data and may contain personal business contact data in `facilities` and `contact_suggestions`;
4. email contact and clicked external links transfer data to the relevant mail, map, source or provider website outside PflegeIndex.

The current privacy page is **partially accurate**. It correctly describes the principal public search, server-log, admin-session and external-link behavior, including the special `/up` endpoint. It is not yet sufficient as a final declaration because the real hosting provider/location, processor arrangements, actual production log retention, actual mail provider and mailbox retention, data-subject rights detail, legal bases and international-transfer facts are not established in the repository.

The principal launch blocker is factual rather than architectural: the operator must confirm the production hosting and email-processing facts before the privacy notice can be finalized. The main technical P1 findings are the automatically loaded third-party assets on `/up`, indefinite application-log retention under the intended `single` log channel, missing deletion rules for contact-review data, and unrestricted retention/content scope of parser `raw_payload`.

## 2. Data Collection Inventory

| Processing event | Data involved | Source | Destination/storage | Current purpose | Public UI? |
|---|---|---|---|---|---|
| Ordinary HTTP request | IP address, time, requested path, possible query string, user-agent, referrer and protocol metadata | Browser/web client | Hosting and web-server layer; exact log configuration unknown | Delivery, security and troubleshooting | Yes |
| Public directory search | `q`, `type`, `city`, `page` in URL | Visitor | Request memory and generated response; no explicit application DB write | Find facilities | Yes |
| Homepage search | `q`, `type` in URL after submission | Visitor | Same as directory search | Find facilities | Yes |
| Admin login | Email, password, IP, user-agent, session ID, CSRF token | Administrator | `users`, `sessions`, session cookie; throttle cache | Authentication and abuse prevention | Login route is publicly reachable; account access is restricted |
| Admin facility maintenance | Description, phone, email, website, source URL, review state and timestamps | Administrator | `facilities` | Maintain public directory data | No, authenticated admin only |
| Parser-result upload/review | Uploaded JSON, facility identifier, phone, email, URLs, confidence, source pages, timestamps, full `raw_payload`, reviewer user ID | Administrator/parser output | `contact_suggestions`, and accepted values in `facilities` | Contact verification | No, authenticated admin only |
| Admin password change | Current password, new password and confirmation | Administrator | New password hash in `users`; transient request/session error handling | Account security | No, authenticated admin only |
| Correction request | Facility name and current facility URL are prefilled; sender address and free message arise only if the user sends the email | Visitor's mail client | User's mail provider and operator mailbox; not PflegeIndex HTTP backend/SQLite | Correct directory data | Yes, via `mailto:` |
| Facility contact actions | Facility phone/email/website and ordinary client metadata | Visitor/browser/device | Telephone carrier, mail client/provider or destination website after user action | Contact facility | Yes |
| External source/map links | Destination URL, IP, user-agent and limited referrer after click; map query contains facility name/address | Visitor/browser | External destination | Map, source or provider information | Yes |

Search fields are not designed to request personal data, but `q` is free text. A visitor can enter personal data voluntarily; it would then appear in the URL and could consequently appear in browser history, same-origin referrers and hosting access logs.

No code was found that profiles visitors, combines public requests into user profiles, uses geolocation, or stores public search history in the application database.

## 3. Forms and User Input

### Form inventory

| Page/form | Route and HTTP method | Controller method | Submitted fields | DB write / email | CSRF and validation | Personal or sensitive data |
|---|---|---|---|---|---|---|
| Homepage search | `GET /pflegeheime.html` (`directory.index`) | `DirectoryController@index` | `q`, `type` | Read-only queries; no email | CSRF not applicable to GET. Values are normalized by typed criteria, but there is no request validation rule/length limit for `q` | Free-text `q` could contain volunteered personal data |
| Directory filter | `GET /pflegeheime.html` | `DirectoryController@index` | `q`, `type`, `city`, implicit `page` | Read-only queries; no email | CSRF not applicable. Unknown values produce filtered/empty results; page is clamped to at least 1 | Same free-text risk; city/type are directory metadata |
| Admin login | `POST /admin/login` | `Admin\AuthController@store` | `_token`, `email`, `password` | Reads `users`; creates/regenerates DB session; no email | `@csrf`; email required/valid, password required/string; `throttle:5,1` | Administrator email, secret password, IP/user-agent in session and throttle metadata |
| Admin logout | `POST /admin/logout` | `Admin\AuthController@destroy` | `_token` | Invalidates/deletes session state; no email | `@csrf`; no field validation needed | Session identifier and authenticated user context |
| Admin facility filters | `GET /admin/einrichtungen` | `Admin\FacilityController@index` | `q`, `status`, `content`, `phone`, `email`, `website`, `source`, `page` | Read-only | Auth/admin middleware; CSRF not applicable. Enumerated filters are allow-listed in controller logic | Mostly facility/business metadata; query remains in URL/admin logs |
| Bulk description publication | `POST /admin/einrichtungen/beschreibungen-veroeffentlichen` | `Admin\FacilityController@publishDescriptionDrafts` | `_token`, `facility_ids[]` | Updates selected `facilities` | `@csrf`; required array, 1–30 IDs, integer/distinct/existing | No visitor data; descriptions could incidentally contain personal information |
| Description draft review | `POST /admin/einrichtungen/{facility}/beschreibung-entwurf` | `Admin\FacilityController@reviewDescriptionDraft` | `_token`, `action`, `description_draft`, `description_draft_sources`, `description_draft_checked_at` | Saves, publishes or discards facility draft fields | `@csrf`; action allow-list; text/date/length rules; every source is additionally checked as HTTP(S) URL. Discard intentionally bypasses browser validation but is handled server-side | Free text and source URLs may contain names or contact data |
| Facility update | `PUT /admin/einrichtungen/{facility}` | `Admin\FacilityController@update` | `_token`, `_method`, optional `suggestion_id`, `description`, `phone`, `email`, `website`, `contact_source`, `contact_status`, `contact_locked` | Updates `facilities`; can return to a related suggestion | `@csrf`; comprehensive type, length, email, URL, boolean and state-consistency validation | Phone/email may identify an individual, especially sole traders or named contacts |
| Parser-result upload | `POST /admin/kontaktpruefung/importieren` | `Admin\ContactSuggestionController@upload` | `_token`, `results_file` | Reads temporary upload; importer writes `contact_suggestions`; no application email | `@csrf`; required file, max 10 MiB. Importer requires JSON with a results list, but does not use a MIME rule or a strict per-field schema/size allow-list | Uploaded email/phone/source data and unrestricted `raw_payload` can contain personal data |
| Contact-review filters | `GET /admin/kontaktpruefung` | `Admin\ContactSuggestionController@index` | `decision`, `parser_status`, `page` | Read-only | Auth/admin middleware; CSRF not applicable; values are allow-listed before query filtering | No additional visitor data |
| Accept parser result | `POST /admin/kontaktpruefung/{suggestion}/annehmen` | `Admin\ContactSuggestionController@accept` | `_token` | Updates suggestion decision/reviewer and potentially facility contacts | `@csrf`; protected route/model binding; aborts unless decision is pending; no additional request fields | Links administrator ID to review action; may publish contact data |
| Reject parser result | `POST /admin/kontaktpruefung/{suggestion}/ablehnen` | `Admin\ContactSuggestionController@reject` | `_token` | Updates suggestion decision/reviewer | `@csrf`; protected route/model binding; aborts unless pending | Links administrator ID to review action |
| Admin password change | `PUT /admin/passwort` | `Admin\PasswordController@update` | `_token`, `_method`, `current_password`, `password`, `password_confirmation` | Replaces password hash and regenerates session; no email | `@csrf`; current password check; confirmation; minimum 12 characters with letters, mixed case and numbers | Authentication secrets. Laravel's exception handler excludes these three password fields from flashed validation input |

### Form conclusions

- All state-changing HTML forms are inside routes protected by `auth` and `admin`, except the login form, which must be public and is throttled.
- The `admin-session` middleware group explicitly starts the session, shares validation errors and enables CSRF validation.
- Public middleware removes session start, shared session errors and CSRF validation. This is currently safe because public HTML forms use GET only and there is no public state-changing endpoint.
- No application form sends email. The only user-facing correction mechanism is a `mailto:` URI.
- Laravel validation redirects can flash non-password form input into the encrypted production session. This can temporarily include admin-entered descriptions, phone numbers, email addresses and source URLs. Password fields are excluded by framework defaults.
- The parser upload is processed from PHP's temporary upload path; no application code copies the uploaded file into permanent storage. Parsed records and the full `raw_payload` are persisted.

Evidence: `routes/web.php`, `bootstrap/app.php`, public and admin Blade forms, admin controllers, `ContactSuggestionImporter`, and Laravel exception-handler defaults in the locked framework version.

## 4. Cookies and Sessions

### Confirmed implementation

- `StartSession`, `ShareErrorsFromSession` and `ValidateCsrfToken` are removed from the normal public web group in `bootstrap/app.php`.
- They are reintroduced only through the `admin-session` group wrapping `/admin/login` and all admin routes.
- The homepage test explicitly asserts that the response has no `Set-Cookie` header.
- No explicit application calls to `Cookie`, `cookie()`, `withCookie()` or `withoutCookie()` were found.
- No cookie-consent component or consent storage was found.
- No analytics, advertising or marketing cookie code was found.

### Intended production session settings

Both `.env.production.example` and `deployment/.env.production.template` specify:

- database session driver;
- 120-minute idle lifetime;
- encrypted session payload;
- secure HTTPS-only cookie;
- HTTP-only cookie;
- `SameSite=Lax`;
- cookie path `/`.

The real production `.env` was intentionally not inspected, so deployment conformity remains an operator verification item.

With the database driver, `sessions` can contain session ID, administrator `user_id`, IP address, user-agent, encrypted payload and `last_activity`. Laravel's database session handler populates IP and user-agent. Expired sessions are eligible for deletion through the configured 2-in-100 garbage-collection lottery on requests that start a session.

The cookie is technically necessary for administrator authentication. Because its configured path is `/`, a browser that has logged into admin will send it on public paths too, although those public routes do not start/read a Laravel session. Scoping it more narrowly could reduce exposure but requires deployment/login-path testing.

### Remember tokens and consent

- `users.remember_token` exists and is hidden by the model.
- The login form has no “remember me” field and `Auth::attempt()` is called without enabling remembrance. No persistent remember-login cookie is intentionally used by the current UI.
- A consent banner is not technically indicated by the code inventory because no optional cookies were found. This conclusion depends on the actual hosting layer and `/up` third-party behavior not introducing additional cookies.
- Login throttling uses Laravel's rate limiter. With the intended database cache, short-lived cache records can be keyed using request/route/IP information. The repository does not define a separate retention statement for those records; their functional TTL is one minute for this route.

## 5. Logging

### Laravel application logging

The production templates specify `LOG_CHANNEL=stack`, `LOG_STACK=single` and `LOG_LEVEL=warning`. This writes to `storage/logs/laravel.log`. The `single` channel has no automatic age-based deletion. A `daily` channel with a configurable default of 14 days exists, but it is not the intended production setting.

Explicit `report($exception)` calls exist in parser/import/audit commands and in the admin parser-upload controller. Unhandled reportable exceptions are also processed by Laravel.

| Data type | Can Laravel application log it? | Technical finding |
|---|---|---|
| Stack traces | Yes | Reported exceptions include the exception object and stack trace |
| Authenticated user ID | Yes | Laravel's default exception context adds `userId` when authenticated |
| IP address | Not automatically in the configured Laravel exception context | Could appear if an exception/context includes it; admin DB sessions separately store it |
| URL and query parameters | Not automatically added by the application logger configuration | Could appear in an exception message/context; hosting access logs may record them |
| User-agent | Not automatically added to Laravel exception context | Stored in admin sessions; hosting logs may record it |
| Email, phone or form content | Not routinely logged by controllers | Could appear in database/validation/import exception messages or SQL bindings when failures are reported; mail content would be logged if the `log` mailer were used |

Validation exceptions are normally redirected and not reported as errors. Password values are excluded from flashed validation input. The repository has no request-logging middleware and no code that logs `$request->all()`.

### Web-server and hosting logs

The existing privacy page and operations guide acknowledge hosting/server logs. Depending on provider configuration, access/error logs can contain IP address, timestamp, full requested URL and query string, response status, referrer and user-agent. The actual provider fields, storage location, access controls and retention period are **unknown**.

This matters for public free-text search because `q` is in the URL. Query parameters can also appear in browser history and same-origin referrers. The global `Referrer-Policy: strict-origin-when-cross-origin` prevents full path/query disclosure as the HTTP referrer to a different origin, but permits the full URL for same-origin navigation.

### Debug configuration

- `config/app.php` defaults to production environment and `APP_DEBUG=false` when variables are absent.
- `.env.production.example` and the deployment template specify `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://pflegeindex.com` and warning-level logging.
- `.env.example` is correctly a local-development example with `APP_DEBUG=true`.
- Actual production values remain unconfirmed by this repository-only audit.

## 6. External Services and Third Parties

### Automatic network requests

| Domain | Where | Trigger | Data potentially received |
|---|---|---|---|
| `fonts.bunny.net` | Laravel framework's `/up` health view | Automatic when a person/browser opens `/up` | IP, request time, user-agent, referrer; font stylesheet request and subsequent font resources |
| `cdn.jsdelivr.net` | Laravel framework's `/up` health view | Automatic when `/up` opens | IP, request time, user-agent, referrer; JavaScript request |

Regular public and admin layouts use local CSS, JavaScript, logos, icons and system font fallbacks. No Google Fonts import, analytics script, external image, CDN script, iframe, map embed or captcha was found on normal application pages.

### User-initiated external navigation

| Domain/category | Pages | Trigger and transmitted context |
|---|---|---|
| `www.google.com` Maps | Facility pages | Click on “In Google Maps öffnen”; URL query includes facility name and postal address, not visitor-entered search data |
| `www.google.com` Search | Admin facility/contact-review pages | Administrator click; query contains facility name, postal code and city |
| Dynamic facility/provider websites | Cards, facility pages and admin | Click; destination receives ordinary client metadata. Domain comes from reviewed database content |
| Dynamic description/contact source URLs | Facility and admin review pages | Click; domain comes from reviewed/imported source data |
| `www.bundesgesundheitsministerium.de` | Lexicon and one editorial facility page | Click on source/reference |
| `www.gesetze-im-internet.de` | Lexicon source links | Click on legal source |
| `www.bmj.de` | Lexicon source link | Click on legal source |
| `www.medizinischerdienst.de` | Fährmann editorial page | Click on guidance source |
| `faehrmann-pflege.de` | Fährmann facility page | Click on provider/source/contact link |
| User and operator mail providers | Footer/legal contact, facility email and `Datenfehler melden` | Activation of `mailto:` opens the client; actual transfer occurs only if the user sends the message |
| Telephone carrier/device handler | Facility `tel:` links | User activation; the facility number is passed to the device/telephony service |

All inspected `target="_blank"` links include `rel="noopener"`; the Google Maps link also includes `noreferrer`. Most other external links do not include `noreferrer`. Due to the site-wide strict-origin-when-cross-origin policy, a cross-origin destination normally receives only the PflegeIndex origin as referrer, not the facility/search path or query. Adding `noreferrer` consistently would further minimize disclosure but is not required to prevent opener control because `noopener` is already present.

The `Datenfehler melden` link pre-populates only:

- subject `Datenfehler PflegeIndex`;
- facility name;
- current canonical facility URL;
- an empty note area.

It does not automatically include the visitor's name, email, IP or message and does not send anything until the visitor confirms sending in a mail client.

Configuration entries for services such as SQS, SES, Postmark or Resend are framework capabilities, not evidence of active connections. No application API client or outbound API call was found. `schema.org`, `sitemaps.org` and W3C URLs in metadata/XML namespaces do not cause browser connections by themselves.

## 7. Database and Stored Personal Data

The intended production database is SQLite outside the release directory and public webroot.

| Table | Personal-data relevance | Current UI usage | Retention/deletion |
|---|---|---|---|
| `users` | Name, administrator email, password hash, optional email-verification timestamp, remember token, admin flag, timestamps | Admin authentication and reviewer identity | No user deletion UI or documented account-retention rule |
| `sessions` | Administrator user ID, IP, user-agent, encrypted payload, last activity | Active admin login/session and validation state | 120-minute idle lifetime; probabilistic garbage collection |
| `password_reset_tokens` | Email, reset-token hash/value and creation time | No password-reset route or UI exists | Tokens configured to expire after 60 minutes if feature used; no scheduled cleanup found |
| `facilities` | Public business details; phone/email can be personal when tied to an individual/sole trader; descriptions may mention people | Public directory and protected admin editor | Import can delete missing facilities; no data-subject deletion workflow or field-specific retention rule |
| `contact_suggestions` | Proposed phone/email/URLs, source URLs, full parser payload, timestamps, decision, reviewer user ID | Protected contact-review UI and import | No automated pruning or retention period |
| `cache` / `cache_locks` | Usually technical values; login throttle keys may relate to IP/request identity | Framework cache/rate limiting | Entry expiration exists; cleanup enforcement is provider/application dependent |
| `jobs` / `job_batches` / `failed_jobs` | Payloads and exception traces could contain personal data if jobs are introduced | No current UI/job dispatch found; intended production queue is `sync` | No scheduled pruning found |
| `cities`, `geo_countries`, `geo_states`, `geo_districts`, `geo_municipalities` | Geographic/public administrative data | Public directory and admin lookup | No personal-data purpose |
| `migrations` | Migration identifiers and timestamps | Framework operations only | Not personal data in normal use |

### Requested feature-specific checks

- **Contact suggestions:** implemented and actively used by protected admin UI; can contain personal business contact data and an unrestricted `raw_payload`.
- **Facility correction/error reports:** no table, endpoint or submission model. Current implementation is mailto-only; messages exist in mail systems, not SQLite.
- **Owner claims:** no route, model, migration, table or UI found.
- **Reviews:** no route, model, migration, table or UI found.
- **Authentication-related tables:** `users`, `sessions` and `password_reset_tokens` exist. Cache tables support throttling/session-adjacent technical processing. Queue tables exist but no current app job flow was found.

The local database contents were not needed for this audit and were not modified. Production contents were not inspected.

## 8. Authentication

- `GET /admin/login` and throttled `POST /admin/login` are publicly reachable by design.
- Authentication is actively used for the admin dashboard, facility editing, parser result review and password change.
- Every `/admin/*` management route requires both `auth` and custom `admin` middleware.
- Non-admin authenticated users receive HTTP 403 from `EnsureAdmin`.
- Logout invalidates the session and regenerates the CSRF token.
- Successful login and password change regenerate the session identifier.
- Administrator email and name are stored in `users`; passwords are stored through the model's `hashed` cast; timestamps and `is_admin` are stored.
- `remember_token` exists but the current UI does not enable remember-me behavior.
- There is no public registration route or form.
- There is no public forgot-password/password-reset route or form. The password-reset table/configuration are dormant framework scaffolding.
- Password change is available only to an authenticated admin and validates the current password plus a strong minimum policy.
- The admin account is created/updated via the console command `pflegeindex:create-admin`; its default email argument is the public project address. The command prints the administrator email to the operator terminal, not to a public page.

## 9. Hosting and Server Processing

### Confirmed from repository documentation

- Deployment target is **shared hosting**.
- The public domain document root contains only the public front controller/assets.
- Laravel core, `.env`, SQLite, storage, logs and backups are intended to remain outside the public webroot.
- Deployment supports FileZilla-only hosting as well as an SSH/Git path.
- PHP 8.2+ and SQLite/PDO SQLite are required.
- `.htaccess` and production rewrite rules require an Apache-compatible environment, but they do not prove the actual server product.
- The intended production SQLite path is external to both release and webroot.
- Application package documentation specifies database sessions/cache, synchronous queue, `single` Laravel log and `log` mail transport.
- Operations documentation calls for daily and pre-change SQLite backups, protected outside webroot, preferably copied to a second owner-controlled system.

### Unknown

- hosting provider legal name and contract entity;
- physical/data-center country and any subprocessors;
- actual web-server product and version;
- whether a CDN, reverse proxy, WAF, DDoS service or provider-level analytics is enabled;
- exact access/error log fields, retention, operator access and deletion mechanism;
- whether an Article 28 data-processing agreement is in place where required;
- actual backup scheduler/tool, storage locations, encryption-at-rest and successful deletion history;
- actual production `.env` conformity;
- actual mailbox/mail provider, server location, spam filtering, forwarding and retention;
- whether provider backups also include web files, logs, SQLite or mailboxes.

The repository's `MAIL_MAILER=log` production template means the Laravel application is not intended to deliver mail externally. No application mail-sending code was found. This does not describe the separate provider used for `info@pflegeindex.com` or messages sent through users' mail clients.

## 10. Retention and Deletion

| Data/storage | Existing mechanism | Defined period | Gap |
|---|---|---|---|
| Admin sessions | 120-minute idle lifetime; DB session GC lottery 2/100; logout invalidation | 120 minutes idle in production template | Actual production setting and effectiveness of probabilistic cleanup must be verified |
| Login throttle/cache | Cache TTL created by `throttle:5,1` | Approximately one-minute rate-limit window | Exact stored key format and cleanup state not documented operationally |
| Password reset tokens | Framework expiry configuration | 60 minutes | Feature is unused; no scheduled `auth:clear-resets` or cleanup found |
| Laravel log | `single` file in intended production config | None | No rotation/deletion period; operations guide defers to hosting policy |
| Hosting access/error logs | Provider facility, acknowledged in docs/privacy page | Unknown | Provider retention and access controls not documented |
| Contact suggestions/raw payload | No deletion/pruning code | None | Indefinite retention unless manually removed outside current UI |
| Facility contact/descriptions | Import/update workflows; facilities absent from an import may be removed | No privacy-specific period | No correction/deletion case workflow or documented retention basis |
| Admin users | Password update only | None | No account deactivation/deletion procedure in UI/docs |
| Email enquiries/corrections | Existing privacy copy says delete after completion unless legal duties apply | Not measurable/technical | Mailbox policy, trash/backups and actual deletion process unknown |
| SQLite backups | Daily/pre-change process documented; protected storage | Recommended 7 daily, 4 weekly, 6 monthly | Recommendation is not an automated/enforced repository mechanism |
| `.env` backups | Back up after approved configuration change | Not specified | Contains secrets and may include personal/processor data; retention and encryption need confirmation |
| Queue/failed job data | Tables exist | None | No current job use, but no prune schedule if use begins |

No Laravel scheduler tasks are configured for session cleanup, logs, submissions, users, password reset tokens, failed jobs or backups. The operations guide provides human procedures but not proof that production executes them.

## 11. Existing Privacy Page Assessment

**Assessment: partially accurate.**

### Technically aligned statements

- web-server processing can include IP, time, requested resource, browser information and referrer;
- search/filter values are URL parameters and may enter server logs;
- public directory pages do not need Laravel session cookies;
- protected admin uses a technically necessary Laravel session;
- no analytics, advertising or tracking services were found;
- normal pages load application assets locally and do not embed maps;
- `/up` can load Bunny Fonts and jsDelivr automatically;
- map, source and facility website connections occur only after a click;
- email contact causes processing of the sender's submitted details;
- public facility/contact data originates from public/official sources and can be subject to correction/deletion requests.

### Missing, unsupported or incomplete statements

- hosting provider, hosting location and processor/subprocessor facts are absent;
- actual access/error log retention is absent;
- legal bases and purposes are not mapped per processing operation;
- recipients/categories for hosting, mail, external `/up` assets and clicked destinations are incomplete;
- rights, complaint authority, right to object, data portability/restriction where applicable, and whether provision is required are not set out;
- international-transfer facts are unknown and therefore cannot be accurately described;
- admin account/session data, session fields, login throttling and retention are only broadly described;
- contact-suggestion/parser data and reviewer audit trail are not described;
- backup processing and retention are not described;
- the statement that email data is deleted after completion is not supported by a confirmed mailbox deletion procedure;
- no last-updated/version date is visible;
- the page does not state that correction mailto data is handled by the user's and operator's mail providers;
- actual production session encryption/security flags and actual `APP_DEBUG` are not proven by repository templates alone.

The page is not a placeholder: it contains project-specific text and matches several tested implementation details. It should not be labelled “technically aligned” overall until production provider and retention facts are confirmed and the identified processing operations are incorporated into a later legal drafting task.

## 12. Confirmed Facts

1. Public application pages do not start a Laravel session under current middleware configuration.
2. No optional application cookies, cookie-consent mechanism, analytics, ads or visitor tracking code were found.
3. Admin authentication uses sessions and is actively used.
4. All state-changing admin forms include CSRF tokens.
5. Public search/filter input travels in URL query parameters and is not explicitly written to SQLite by application code.
6. There is no public registration, password reset, owner claim, review or correction-submission backend.
7. `Datenfehler melden` is mailto-only and pre-populates facility name and canonical URL.
8. Facility/provider phone and email links hand control to the user's device/client; the Laravel application does not send the call or message.
9. Regular page assets are local; no external images, iframes, embedded maps, analytics or captcha were found.
10. `/up` automatically references `fonts.bunny.net` and `cdn.jsdelivr.net` through Laravel's framework view.
11. All inspected new-tab links include `noopener`; only some include `noreferrer`.
12. SQLite contains authentication/session structures and protected contact-review data in addition to public directory data.
13. Production templates prescribe debug off, warning-level single-file logging, encrypted secure admin sessions, synchronous queues and log mail transport.
14. The repository does not enforce application-log, contact-suggestion, admin-account or email retention periods.
15. Backups have documented recommended retention but no automated repository scheduler.

## 13. Unknowns Requiring Operator Input

1. What is the legal name and address of the production hosting provider?
2. In which country/data center are website, SQLite, logs and provider backups stored?
3. What actual web server, reverse proxy, CDN, WAF or provider analytics are enabled?
4. Which access/error log fields are recorded, who can access them, and after how many days are they deleted?
5. Is an applicable data-processing agreement with the hosting provider in place?
6. Does the real production `.env` match the secure template for debug, logging, session encryption, Secure/HttpOnly/SameSite and mail transport?
7. Which provider hosts `info@pflegeindex.com`; where are mailbox data and mail backups stored; is mail forwarded to another provider/device?
8. What is the actual retention/deletion procedure for enquiries and correction emails, including trash, spam and backups?
9. Are daily SQLite backups actually automated; where are primary/secondary copies stored; are they encrypted; is 7/4/6 retention enforced?
10. Do provider-level backups include `.env`, SQLite, Laravel logs, access logs or mailboxes, and what are their deletion periods?
11. How long should accepted/rejected contact suggestions and parser `raw_payload` be retained for audit purposes?
12. Are any facility phone/email fields personal contacts of sole traders or named employees rather than general organizational contacts?
13. Is `/up` intended for human/public browser access, or can its third-party assets be removed/avoided operationally?
14. Which supervisory authority and legal jurisdiction should be named in the future final privacy notice?
15. Are there any external monitoring, uptime, malware scanning or security services configured only in the hosting control panel and therefore absent from the repository?

## 14. Privacy Gaps

### P0 — blocks final public-launch privacy approval

- Production hosting/provider/location/log-retention and processor facts are not confirmed. A final accurate privacy notice cannot be completed from the repository alone.
- The real mailbox provider, processing location, forwarding and retention for `info@pflegeindex.com` are unknown, while the site actively invites email and correction requests.

These are factual/legal-release blockers, not evidence of a code vulnerability.

### P1 — fix before active promotion

- `/up` causes automatic third-party requests to Bunny Fonts and jsDelivr, unnecessarily exposing visitor network metadata on a technical endpoint.
- Intended production logging uses an unrotated `single` file with no defined retention or automated deletion.
- `contact_suggestions` and reviewer history have no retention/pruning rule.
- Parser upload stores the full unbounded `raw_payload` without a strict field schema or per-field size limits, increasing accidental personal-data and minimization risk.
- The current privacy page omits admin/session/contact-review/backup processing and relies on unverified hosting/mail facts.
- The email deletion statement is operationally unverified.

### P2 — later improvement

- Consider narrowing the admin session cookie path after compatibility testing.
- Consider consistent `noreferrer` for third-party new-tab links where losing origin referrer is acceptable.
- Document an administrator account offboarding/deletion process.
- Add scheduled or documented cleanup for dormant password-reset, failed-job and cache records if those features become active.
- Add a visible revision date/version to the future privacy notice.

## 15. Recommended Next Actions

1. Obtain written answers to all P0 hosting and mailbox unknowns before drafting the final Datenschutzerklärung.
2. Export or record the actual production configuration facts without exposing secret values: debug state, session security flags, log channel/level, mail transport and database/session drivers.
3. Obtain the hosting provider's access/error log specification, retention periods, backup policy, subprocessors and data-processing agreement information.
4. Decide whether `/up` should use only local/no external presentation resources; until then, keep its third-party processing explicitly documented.
5. Define and implement an operational retention matrix for Laravel logs, hosting logs, sessions, contact suggestions/raw payload, admin accounts, emails and backups.
6. Minimize parser persistence to a documented allow-list or justify and time-limit `raw_payload` retention.
7. Confirm whether published facility contacts can identify natural persons and define correction/removal handling for those cases.
8. Only after the facts above are confirmed, run a separate legal-content task to prepare the final privacy notice. Do not copy assumptions from configuration templates into legal text as production facts.
9. Keep the current no-analytics/no-optional-cookie posture unless a separate privacy assessment precedes any future external service.

| Priority | Finding | Evidence | Risk | Recommended Action |
|---|---|---|---|---|
| P0 | Hosting provider, location, subprocessors and actual log retention are unknown | `docs/DEPLOYMENT_CHECKLIST.md`, `docs/OPERATIONS.md`; no provider contract/config in repository | Final notice may be materially incomplete or inaccurate | Obtain provider facts, DPA status, log and backup policies before legal release approval |
| P0 | Mailbox provider/location/retention for public contact and corrections are unknown | Public `mailto:` links and `Datenfehler melden`; no mailbox policy in repository | Email processing and deletion statement cannot be verified | Document provider, forwarding, storage, backup and deletion process |
| P1 | `/up` automatically loads Bunny Fonts and jsDelivr | Laravel `health-up.blade.php`; current privacy page and tests | IP/user-agent/referrer reach third parties without an intentional content need | Replace/avoid external health-view assets or explicitly justify and document processing |
| P1 | Intended `single` Laravel log has no retention | Production templates and `config/logging.php` | Indefinite accumulation of stack traces, user IDs and exceptional data | Use controlled rotation/retention and restrict access |
| P1 | Hosting access/error log details are not operationally defined | Privacy page and operations guide acknowledge logs but defer to provider | Search query strings and technical identifiers may persist for unknown periods | Confirm fields and define shortest appropriate retention |
| P1 | Contact suggestions and reviewer audit trail have no deletion period | Migration/model/controllers; no scheduler or delete UI | Contact data and admin activity may persist indefinitely | Define purpose-based retention and pruning procedure |
| P1 | Full parser `raw_payload` is stored without strict field/size schema | `ContactSuggestionImporter`, upload max 10 MiB | Unnecessary or accidental personal data can enter SQLite/backups | Allow-list persisted fields, validate sizes, or time-limit/remove raw payload after review |
| P1 | Existing privacy page is only partially accurate | `resources/views/pages/privacy.blade.php` compared with implementation | Users do not receive a complete account of processing | Finalize only after operator facts are confirmed |
| P1 | Email deletion claim is not backed by a confirmed mechanism | Privacy section 4; no mailbox tooling/policy in repository | Published retention statement may not match reality | Establish mailbox retention and then align wording |
| P2 | Admin session cookie path is `/` | Production templates and `config/session.php` | Cookie is sent on public paths after admin login even though unused there | Assess `/admin` scoping with login/logout/CSRF tests |
| P2 | Most external new-tab links omit `noreferrer` | Blade external links; all have `noopener`; strict-origin policy is set | External sites receive the PflegeIndex origin | Add `noreferrer` where compatible and useful |
| P2 | No documented administrator offboarding/deletion workflow | `users` model/admin routes | Old admin account data may remain | Add an operator procedure and periodic account review |
| P2 | Backup retention is recommended but not enforced | `docs/OPERATIONS.md`; no scheduler | Old copies containing admin/contact data may survive | Verify automation, encryption, deletion and restore testing |
| INFO | Public search is GET-only and not explicitly stored by app | Home/directory forms and `DirectoryController@index` | Query remains visible in URL/log layers | Keep users from entering personal data; cover logs in notice |
| INFO | No analytics, advertising, captcha, iframe or embedded maps found | Layouts, assets and repository-wide search | No optional application-cookie requirement currently identified | Preserve this baseline unless separately assessed |
| INFO | No owner claims, reviews, registration or correction-report table exists | Routes, models, migrations and views | No current processing for these features | Re-audit before any such feature is introduced |
| INFO | Correction reporting is mailto-only | Facility view | Laravel/SQLite do not receive the report; mail providers do if sent | Document mailbox processing and retention |

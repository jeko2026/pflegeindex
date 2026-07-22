# Legal and Hosting Facts — Confirmation Matrix

**Verification date:** 2026-07-22

This matrix distinguishes repository-confirmed application behavior from
facts that require an operator contract, hosting panel or provider statement.
No new provider or operator evidence was supplied during Sprint 3.5.3.
Consequently, every such item remains `UNKNOWN` and blocks unconditional
deployment approval.

Status meanings:

- `CONFIRMED` — supported by the cited repository, test or existing recorded
  evidence;
- `NOT USED` — the audited application does not use the service or processing;
- `UNKNOWN` — no reliable source was available; the value was not guessed.

| Fact | Confirmed value | Source | Checked | Status | Action required before deployment |
| --- | --- | --- | --- | --- | --- |
| Hosting provider legal name | No confirmed value supplied | `docs/LEGAL_HOSTING_FACTS_CHECKLIST.md`; owner questions | 2026-07-22 | UNKNOWN | Obtain contracting entity from contract or invoice |
| Hosting provider legal address | No confirmed value supplied | Existing checklist; no provider document supplied | 2026-07-22 | UNKNOWN | Obtain current legal postal address |
| Server/data-centre country | No confirmed value supplied | Existing checklist; HTTP headers do not establish location | 2026-07-22 | UNKNOWN | Obtain written provider confirmation |
| AV-Vertrag / Article 28 agreement | Availability and signing status not supplied | Existing privacy audit and checklist | 2026-07-22 | UNKNOWN | Confirm applicability, sign if required and retain evidence |
| Hosting subprocessors | No confirmed list supplied | Existing checklist | 2026-07-22 | UNKNOWN | Obtain current provider subprocessor list |
| Web server and PHP | Public host previously identified nginx 1.20.2 and PHP 8.2.27 | Read-only production headers recorded in `docs/LEGAL_HOSTING_FACTS_CHECKLIST.md` | 2026-07-22 | CONFIRMED | Reconfirm after deployment; this does not identify provider/location |
| Server log types | Access/error logging behavior not documented | Provider documentation was not supplied | 2026-07-22 | UNKNOWN | Confirm enabled logs and exact fields |
| Server log retention | No confirmed value | Provider documentation was not supplied | 2026-07-22 | UNKNOWN | Confirm normal and backup deletion periods |
| IP addresses in server logs | IP is necessarily processed for HTTP transport; persistent storage is unconfirmed | HTTP operation; existing checklist | 2026-07-22 | UNKNOWN | Confirm whether IP is logged, shortened/anonymized and for how long |
| URL/query/referrer/user-agent in server logs | Persistent fields are unconfirmed | Existing privacy audit and checklist | 2026-07-22 | UNKNOWN | Obtain exact access/error-log schema |
| Laravel application logs | Daily files, 30-day retention, private `storage/logs` | `config/logging.php`; production template; logging tests | 2026-07-22 | CONFIRMED | Verify deployed environment and permissions without disclosing values |
| Mailbox provider | No confirmed value supplied | Repository contains mailto links only | 2026-07-22 | UNKNOWN | Obtain legal provider name and subprocessors |
| Mail server country | No confirmed value supplied | Existing checklist | 2026-07-22 | UNKNOWN | Confirm storage countries and transfers |
| Mail and mail-log retention | No evidenced operational period | Existing checklist; current mailbox records unavailable | 2026-07-22 | UNKNOWN | Define inbox/sent/trash/spam/log/backup deletion periods |
| Mail forwarding | No confirmed value supplied | Existing checklist | 2026-07-22 | UNKNOWN | Record all forwarding destinations and recipients |
| Production backup location | Required outside webroot, actual location not supplied | `docs/OPERATIONS.md`; deployment runbook | 2026-07-22 | UNKNOWN | Confirm protected primary/secondary locations privately |
| Production backup retention | Recommendation exists, actual policy not confirmed | `docs/OPERATIONS.md` | 2026-07-22 | UNKNOWN | Confirm active schedule, encryption, deletion and last success |
| Restore evidence | No production-compatible restore record supplied | Existing checklist | 2026-07-22 | UNKNOWN | Perform and record restore test |
| Public cookies | Public routes remove session and CSRF middleware and set no cookie in Feature test | `bootstrap/app.php`; `LegalPagesTest` | 2026-07-22 | NOT USED | Reconfirm from live response headers after deployment |
| PHP administrator session cookie | Default production name `pflegeindex-session`; 120-minute configured lifetime; encrypted; Secure, HttpOnly, SameSite=Lax | `config/session.php`; production template; response-header Feature test | 2026-07-22 | CONFIRMED | Verify live production flags after config cache |
| CSRF cookie | `XSRF-TOKEN`; 120-minute configured lifetime; Secure, SameSite=Lax, not HttpOnly for CSRF client access | Laravel CSRF middleware; session config; response-header Feature test | 2026-07-22 | CONFIRMED | Verify live production response; admin only |
| Remember cookie | Login does not request Laravel remember mode | `Admin/AuthController.php`; login form; static audit | 2026-07-22 | NOT USED | Re-audit if “Angemeldet bleiben” is added |
| Analytics cookies/services | No analytics package, script or endpoint found | Blade/CSS/JS/Composer/static audit | 2026-07-22 | NOT USED | Confirm hosting panel injects none |
| Advertising/marketing tracking | No pixel, tag manager, ad or marketing code found | Blade/CSS/JS/static audit | 2026-07-22 | NOT USED | Re-audit before adding any service |
| External fonts | Active layouts use local/system resources; no remote font request | Active Blade layouts and public CSS/static audit | 2026-07-22 | NOT USED | Recheck browser network after deployment |
| CDN resources | No active CDN script, stylesheet or image URL found | Active Blade/CSS/JS/static audit | 2026-07-22 | NOT USED | Confirm provider-level CDN/reverse proxy separately |
| Embedded maps/tiles | No iframe, tile layer or map embed; Google Maps is a clicked link only | Facility Blade template/static audit | 2026-07-22 | NOT USED | Re-audit before embedding a map |
| Automatically loaded external images | Layout, logos and OG image are local; facility pages do not load remote images | Active Blade and public assets/static audit | 2026-07-22 | NOT USED | Recheck representative live pages |
| Clicked external links | Facility/provider/source links and Google Maps connect only after click | Facility/card/editorial Blade templates | 2026-07-22 | CONFIRMED | Keep external-link disclosure; monitor new content |
| Public contact form | No public POST form or backend; directory forms are GET search/filter | Routes and Blade/static audit | 2026-07-22 | NOT USED | Re-audit before adding a form |
| Mailto links | General contact and facility correction use the user's mail client; correction pre-fills facility name and current URL | Layout and facility Blade; Feature tests | 2026-07-22 | CONFIRMED | Confirm mailbox provider and retention |
| External error monitoring | No external SDK/service or transport found | Composer/config/application static audit | 2026-07-22 | NOT USED | Re-audit before adding monitoring |
| Third-party APIs | No application-initiated public frontend API request found | PHP/JS static audit | 2026-07-22 | NOT USED | Re-audit before adding API integrations |
| `/up` external resources | Plain local `OK`; no external font/script/content | `bootstrap/app.php`; HealthCheck tests | 2026-07-22 | NOT USED | Verify exact live body and headers after deployment |
| Provider analytics/CDN/WAF | Hosting-layer behavior not documented | Existing checklist; application audit cannot prove provider injection | 2026-07-22 | UNKNOWN | Confirm in control panel/provider statement |

## Cookie Legal Basis

The two administrator cookies are documented as strictly necessary for the
explicitly requested protected administration service. The application basis
recorded for terminal access/storage is § 25(2)(2) TDDDG; subsequent personal
data processing is documented under Article 6(1)(f) GDPR. Final legal approval
remains the operator's responsibility.

Official texts checked on 2026-07-22:

- `https://www.gesetze-im-internet.de/ttdsg/__25.html`
- `https://eur-lex.europa.eu/eli/reg/2016/679/oj`

## Approval Effect

The application behavior is technically documented, but hosting, mailbox,
backup and operator evidence is incomplete. This matrix therefore supports
`CONDITIONAL GO` only and does not authorize production deployment.

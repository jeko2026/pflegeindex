# Legal, Hosting and Mailbox Facts Checklist

Complete this checklist with documentary evidence before the final
Datenschutzerklärung review and public release approval. Do not copy an
`UNKNOWN` value into legal text and never record credentials, keys or tokens in
this file.

Status values:

- `CONFIRMED` — supported by repository evidence or operator/provider records;
- `UNKNOWN` — requires a factual answer or document from the operator/provider;
- `NOT USED` — the current application does not use the processing operation.

| Fact | Current known value | Evidence/source | Status | Action required |
| --- | --- | --- | --- | --- |
| Hosting provider legal name | Not recorded | Repository and existing audits | UNKNOWN | Obtain the exact contracting entity from the hosting contract/invoice |
| Hosting provider postal address | Not recorded | Repository and existing audits | UNKNOWN | Obtain the provider's current legal address |
| Server/data-centre country | Not recorded | Repository and public HTTP headers do not prove location | UNKNOWN | Confirm country and, if available, data-centre region in writing |
| Data processing agreement | Availability and signing status not recorded | Existing privacy audit | UNKNOWN | Confirm whether an Article 28 agreement applies and retain the signed record |
| Hosting subprocessors | Not recorded | Existing privacy audit | UNKNOWN | Obtain the current provider subprocessor list |
| Web server | Public host identified itself as nginx 1.20.2 on 2026-07-22 | Read-only production response headers | CONFIRMED | Reconfirm after deployment; version-header suppression is an operational hardening item |
| PHP runtime | Public host identified PHP 8.2.27 on 2026-07-22 | Read-only production response headers | CONFIRMED | Reconfirm using the protected server checklist after deployment |
| Server access/error-log fields | Privacy copy mentions IP address, time, resource, browser and referrer, but provider fields are not documented | Current privacy page; no provider specification | UNKNOWN | Obtain the exact access/error-log schema and whether query strings are stored |
| Server access/error-log purpose and access | Security/operations purpose is intended; authorized persons are not recorded | Privacy page and operations guide | UNKNOWN | Confirm purpose, operator/provider access roles and disclosure recipients |
| Server access/error-log retention | Not recorded | Laravel rotation does not govern nginx/provider logs | UNKNOWN | Obtain the deletion period and backup behavior |
| Laravel application logs | Daily rotation, 30-day retention, private `storage/logs` path | Production templates, `config/logging.php`, `OPERATIONS.md` | CONFIRMED | Verify the deployed `.env` and filesystem permissions without exposing values |
| Mailbox provider for `info@pflegeindex.com` | Not recorded | Repository only defines public mailto links | UNKNOWN | Record legal provider name, contract entity and subprocessors |
| Mailbox server/data location | Not recorded | Existing privacy audit | UNKNOWN | Confirm storage countries and any international transfers |
| Mail metadata/content processing | User-selected email client sends address, metadata and message content to mail providers | `mailto:` links; no Laravel mail submission | CONFIRMED | Document provider roles, spam filtering, forwarding and recipients |
| Mail forwarding | Not recorded | Existing privacy audit | UNKNOWN | Record every forwarding destination/provider/device workflow |
| Mail retention and deletion | Privacy page states deletion after completion, but no operational procedure is evidenced | Privacy page and privacy audit | UNKNOWN | Define inbox, sent, trash, spam and backup retention before keeping that statement |
| Public contact form | No public form/backend; contact and correction reporting use `mailto:` | Routes, Blade templates and tests | NOT USED | Re-audit before introducing a form |
| Facility correction reporting | Mailto pre-fills facility name and canonical page URL | Facility Blade template and feature tests | CONFIRMED | Include mailbox processing and retention in the final privacy text |
| Automatically loaded external resources on public pages | Current release uses local CSS, JavaScript, fonts/images; `/up` is plain text | Layout, health endpoint and automated tests | NOT USED | Reconfirm on the deployed release with browser/network inspection |
| Clicked external links | Facility/provider websites, Google Maps and cited sources open only after a user click | Public Blade templates | CONFIRMED | Describe categories where legally required; re-audit newly added links |
| Analytics, advertising or visitor tracking | No application analytics, ads or tracking code found | Repository-wide privacy audit | NOT USED | Confirm that the hosting control panel does not inject provider analytics |
| Optional public cookies | Public middleware is sessionless; no consent-controlled cookies exist | Bootstrap middleware and tests | NOT USED | Re-audit before adding analytics, personalization or embeds |
| Admin session cookie | Technically necessary encrypted database session; Secure, HttpOnly and SameSite=Lax in production template | Session config, production templates and auth tests | CONFIRMED | Verify actual production flags after config cache is built |
| SQLite database location | Intended outside release directory and public webroot; actual server path is intentionally not recorded here | Deployment templates/checklist | UNKNOWN | Confirm separation and permissions on the server without publishing the path |
| Backup location | Required outside public webroot, preferably with a secondary copy; actual locations unknown | `OPERATIONS.md` | UNKNOWN | Record protected primary/secondary locations in a private operations record |
| Backup schedule and retention | Recommended daily/pre-change backups with 7 daily, 4 weekly and 6 monthly copies | `OPERATIONS.md` | UNKNOWN | Confirm automation, encryption, deletion and last successful run |
| Restore test | Monthly restore testing is recommended; no current evidence recorded | `OPERATIONS.md` | UNKNOWN | Perform and privately record a restore test before GO |
| Application error-monitoring service | No external error-monitoring SDK/service found | Composer lock, configuration and repository search | NOT USED | Re-audit before adding a service |
| Provider uptime/security monitoring | Not recorded | Existing production audits | UNKNOWN | Confirm uptime, SSL expiry, disk, backup and error alerting plus responsible operator |
| CDN, reverse proxy or WAF | Not recorded | Public responses alone are insufficient | UNKNOWN | Confirm provider-level services and any additional data recipients |

## Approval Record

Before final legal drafting, the operator should record privately:

- evidence date and source for every `CONFIRMED` provider fact;
- who approved the hosting, mailbox, backup and log descriptions;
- the final Datenschutzerklärung revision/date;
- the production release commit and deployment date used for the technical
  comparison.

This checklist is a factual input, not legal advice and not a substitute for
the operator's legal review.

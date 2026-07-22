# PflegeIndex Deployment Approval

**Assessment date:** 2026-07-22

| Approval item | Status | Evidence / required action |
| --- | --- | --- |
| Release candidate | PASS | Three verified `fd01a71f` archives recorded in `docs/FINAL_RELEASE_CANDIDATE_REPORT.md` |
| Application source commit | PASS | `fd01a71fb5084c22aea83ba6d50bcc00b7769f72` |
| Documentation baseline commit | PASS | `658756eaf68d0233b55ecfe86e9f30a6d3b0649b` pushed by fast-forward to `origin/main` |
| Git status before legal work | PASS | Local `main` matched `origin/main`; worktree was clean |
| Hosting provider identity/address | PENDING | Contract/provider evidence not supplied |
| Server country and subprocessors | PENDING | Provider confirmation not supplied |
| AV-Vertrag | PENDING | Availability and signing status not supplied |
| Server logs and retention | PENDING | Exact fields and deletion period not supplied |
| Mailbox facts and retention | PENDING | Provider, server country, forwarding and retention not supplied |
| Datenschutzerklärung technical alignment | PASS | False Bunny/jsDelivr statement removed; application resources, cookies, sessions and mailto behavior documented and tested |
| Datenschutzerklärung legal completeness | PENDING | Hosting/mailbox/provider-log facts remain material UNKNOWN values |
| Impressum structure/contact | PASS | Page, canonical, noindex, footer link, postal block and `info@pflegeindex.com` are present |
| Impressum operator evidence | PENDING | Operator name appears abbreviated and address/status were not directly reconfirmed in this sprint; no personal data was changed |
| External frontend resources | PASS | No automatic third-party font/script/style/image/map/API/analytics request found in active public frontend |
| Cookies | PASS | Public pages sessionless; two necessary admin cookies documented and tested; no remember/analytics/marketing cookie |
| nginx repository configuration | PASS | One-hop canonical rules reviewed and covered by application regression tests |
| nginx production application | PENDING | Hosting administrator has not confirmed application/reload/live behavior |
| Production backup | PENDING | No current production backup/restore evidence supplied |
| Production private/public/database paths | PENDING | Actual protected paths and permissions not confirmed |
| SQLite deployment plan | PENDING | Scenario A or B has not been selected and approved for production |
| Owner legal approval | PENDING | Final operator/provider facts and legal review not supplied |
| Production deployment | PENDING | Explicitly not performed in this sprint |

## Decision

**CONDITIONAL GO**

The release has no known application-code or package blocker. Deployment is
not authorized until every `PENDING` hosting, mailbox, backup, path, SQLite,
Impressum identity and owner-approval item above is converted to `PASS` using
documented evidence. A `FAIL` in security, data integrity or backup readiness
changes the decision to `NO-GO`.

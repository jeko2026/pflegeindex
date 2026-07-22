# Data Quality Framework

## 1. Purpose and scope

**Applicability: Reusable for Directory Platform**

This document defines a common internal standard for measuring, reviewing and
improving directory data. It applies to PflegeIndex and provides a configurable
contract for future Directory Platform catalogs.

This is a documentation-first design. It does not implement a dashboard,
change the current PflegeIndex Trust Layer, modify public scores, or alter code,
Blade, models, migrations, SQLite, tests, URLs or SEO.

Every subsection inherits the nearest `Applicability` label unless it contains
its own label. Catalog-specific facts are marked `PflegeIndex only`; generic
policy is marked `Reusable for Directory Platform`.

The framework evaluates the quality of information about a directory entry. It
must never be presented as a rating, recommendation, performance assessment or
quality certification of the listed organization.

## 2. Core principles

**Applicability: Reusable for Directory Platform**

- Data presence is not the same as accuracy.
- Official origin is not the same as current verification.
- Publication is not the same as verification.
- A syntactically valid value can still belong to the wrong entity.
- Every quality result must be reproducible from a versioned policy.
- Critical failures cannot be hidden by a high average score.
- Missing, not applicable and unknown are different states.
- Automated discovery may prioritize work but does not verify facts.
- Quality must be measured at field, entry and catalog level.
- KPI denominators, exclusions, snapshot time and catalog must be explicit.
- Scores guide editorial work; they do not replace evidence or human review.
- Public badges must never claim more than the underlying evidence proves.

## 3. Current Data Audit

**Applicability: PflegeIndex only**

### 3.1 Official and imported data

PflegeIndex receives facility base data from the Brandenburg source dataset.
The current import maps these values into `facilities`:

| Data group | Current fields | Quality meaning |
|---|---|---|
| Source identity | `source_id` | Stable identity in the imported source |
| Facility identity | `name`, `city_id` | Name and assigned internal city |
| Address | `postal_code`, `street`, `house_number`, `address` | Imported location fields |
| Classification | `type`, `source_sector` | Source classification, not a user rating |
| Additional classifications | `care_types`, `features` | Imported JSON; field-level provenance is not currently stored |

The import preserves existing non-null descriptions and contacts protected by
`contact_locked`. This prevents ordinary re-imports from silently overwriting
approved manual work.

GeoCore supplies the official geographic hierarchy:

```text
Country
→ State
→ District
→ Municipality
→ mapped City
→ Facility
```

Municipality records retain `source_name`, `source_date` and `source_url`.
City mappings retain match status, method, confidence and manual-review state.

The final local validation recorded:

- 257 cities;
- 255 mapped cities;
- 2 unresolved review cities;
- 1,557 facilities;
- 1,552 facilities with municipality/district coverage;
- 5 facilities without that coverage;
- no GeoCore orphan or duplicate AGS findings.

These are dated local audit facts, not a guarantee that production remains
identical.

### 3.2 Editorial and researched data

| Data group | Current fields/process | Quality controls already present |
|---|---|---|
| Contacts | `phone`, `email`, `website` | Validation, source, checked date, status and import lock |
| Contact candidates | `contact_suggestions` | Fingerprint, parser result, confidence, source, review decision and reviewer |
| Published description | `description` | Can be edited; reviewed descriptions retain sources and checked date |
| Description draft | `description_draft` | Remains private until explicit publication |
| Description provenance | `description_sources`, `description_checked_at`, `description_ai_assisted` | Sources and review metadata; AI is not treated as a source |

Current limitations:

- contacts share one general `contact_source` rather than one source per field;
- direct facility edits do not create a complete immutable change history;
- presence of `description` does not alone prove uniqueness or review;
- a single contact status covers phone, e-mail and website together;
- official and editorial values share the same facility row;
- no structured opening hours, media rights or field-level next-review date
  exists.

### 3.3 Automatically computed data

| Computed result | Inputs | Persistence |
|---|---|---|
| Canonical and public URL | routes, city slug, facility slug | Computed at render time |
| Formatted phone | stored phone | Computed by the model helper |
| Related facilities | city and listing query | Computed for the page |
| Maps search URL | name and address | Computed, external request only after click |
| Breadcrumb and structured navigation | route hierarchy | Computed at render time |
| District coverage | City → municipality → district relation | Derived from stored GeoCore mapping |
| Trust score and badges | loaded facility/city/URL facts | Computed dynamically, not stored |

Computed values require validation of both the algorithm and their source data.
A valid canonical does not make contact data accurate; a correct hierarchy does
not verify a facility's website.

### 3.4 Data requiring periodic review

| Data | Why it changes | Review trigger |
|---|---|---|
| Phone, e-mail, website | Providers change contact channels | Freshness SLA, source change, error report |
| Contact source | Pages move or disappear | Failed source check or new official page |
| Description | Services and provider facts change | Freshness SLA or supporting source change |
| Official facility base data | New official dataset releases | Controlled source import and mismatch audit |
| GeoCore hierarchy/mapping | Administrative changes or corrected evidence | New official source/mapping review |
| Facility status/existence | Closure, relocation, renaming | Source evidence or operator report |
| SEO-derived URLs | Identity/location change | Separate URL governance and redirect review |
| Future opening hours | Operationally volatile | Short freshness SLA and exception review |
| Future photographs | Rights, accuracy and age change | Rights expiry, replacement or complaint |

## 4. Unified data classification

**Applicability: Reusable for Directory Platform**

A data value must be classified on independent axes. One overloaded `status`
cannot describe provenance, verification, publication and freshness safely.

### 4.1 Origin

**Applicability: Reusable for Directory Platform**

| Origin | Definition | Example |
|---|---|---|
| `official` | Copied from a confirmed public-authority source | Official facility name or municipality AGS |
| `provider_primary` | Confirmed on the listed entity/provider's own source | Facility phone on its official page |
| `editorial` | Written or normalized by the directory team from cited evidence | Neutral unique description |
| `reported` | Submitted by an external person but not yet independently verified | Data-error e-mail or future submission |
| `derived` | Produced deterministically from other data | Canonical URL or normalized display phone |
| `unknown` | Provenance is unavailable or not yet assessed | Legacy value without source metadata |

Origin is not a quality grade. Official data may be old; editorial data may be
well verified; reported data remains a candidate until reviewed.

### 4.2 Verification state

**Applicability: Reusable for Directory Platform**

| State | Definition |
|---|---|
| `unreviewed` | Imported/discovered but no human decision exists |
| `needs_review` | Due, conflicting, incomplete, reported or otherwise queued |
| `verified` | Identity, value and source were checked under current policy |
| `rejected` | Candidate was reviewed and must not replace published data |

### 4.3 Publication state

**Applicability: Reusable for Directory Platform**

| State | Definition |
|---|---|
| `draft` | Private work in progress |
| `in_review` | Complete candidate awaiting publication decision |
| `published` | Current public version |
| `archived` | No longer current/public but retained according to policy |

### 4.4 Freshness state

**Applicability: Reusable for Directory Platform**

| State | Definition |
|---|---|
| `current` | Within the field's approved freshness window |
| `due_soon` | Approaching expiry inside the warning window |
| `overdue` | Freshness window expired |
| `unknown` | No reliable verification date or SLA exists |

### 4.5 Applicability

**Applicability: Reusable for Directory Platform**

Every expected field is one of:

- `required` for this entry type;
- `recommended` but not required for publication;
- `optional`;
- `not_applicable` with a documented rule;
- `unknown_applicability` and requiring classification.

`not_applicable` must not be counted as missing. It must also not receive free
quality points; applicable weights are renormalized.

## 5. Current PflegeIndex Trust Layer

**Applicability: PflegeIndex only**

The public facility Trust Layer currently evaluates ten criteria:

- phone;
- website;
- e-mail;
- complete address;
- description;
- coordinates;
- official source ID;
- canonical URL;
- documented review;
- absence of obvious syntactic errors.

Raw weights total 110 and are normalized to a displayed 0–100 percentage. The
calculation uses already loaded data, adds no SQL query and is not stored. The
facility schema currently has no coordinate columns, so that criterion is not
earned by the current dataset.

This score is useful as a transparent public completeness/plausibility signal,
but it is not a complete Data Quality Framework:

- it does not prove that a contact belongs to the correct facility;
- it does not apply a freshness deadline;
- it treats source ID presence as official provenance but not source recency;
- it cannot determine whether every description is unique;
- it cannot express per-field source conflicts;
- one documented review can satisfy the review criterion even if other fields
  remain stale;
- it does not measure catalog-level workflow or review throughput.

The existing Trust Layer remains unchanged. The universal internal model below
must not silently alter its weights, UI or meaning.

## 6. Data Quality Dimensions

**Applicability: Reusable for Directory Platform**

### 6.1 Completeness

**Applicability: Reusable for Directory Platform**

**Definition:** Whether all applicable required and recommended information is
present.

**Measurement:**

```text
sum(weight of present applicable fields)
÷ sum(weight of all applicable fields)
× 100
```

Presence must use field-specific rules. Whitespace is not a value; a malformed
URL is not a complete website; a generic template is not a unique description.

**Desired value:**

- 100% for publication-critical fields;
- at least 95% weighted completeness at catalog level;
- catalog-specific targets for recommended contacts/content.

**User impact:** Higher completeness reduces dead ends and helps users contact,
identify and compare entries without leaving the directory unnecessarily.

### 6.2 Accuracy

**Applicability: Reusable for Directory Platform**

**Definition:** Degree to which a value matches the correct real-world entity
and supporting evidence.

**Measurement:** Weighted pass rate of field validators and human verification:

- syntax/plausibility;
- entity identity match;
- source agreement;
- sample re-verification;
- error-report outcomes;
- import-source mismatch checks.

A syntactic validator contributes evidence but cannot alone produce `verified`.

**Desired value:**

- 100% hard-constraint pass rate for published critical fields;
- at least 98% accuracy in controlled verification samples;
- zero known critical contradictions left published without a visible handling
  decision.

**User impact:** Accurate data prevents calls to the wrong organization,
misleading addresses and incorrect service expectations.

### 6.3 Freshness

**Applicability: Reusable for Directory Platform**

**Definition:** Whether verification is recent enough for the volatility of a
field.

**Measurement:** Weighted share of applicable fields within their configured
freshness SLA:

```text
current = 1.0
due_soon = 0.75
overdue = 0.0
unknown = 0.0
```

Projects may choose another documented decay function, but it must be versioned
and reproducible. `updated_at` is not a verification timestamp.

**Desired value:**

- at least 90% of published entries current for critical volatile fields;
- zero overdue critical field ignored after a confirmed error report;
- 100% of fields have either a review date/SLA or an explicit `unknown` state.

**User impact:** Fresh contacts and operating information reduce failed calls,
bounced e-mails and reliance on closed or relocated entries.

### 6.4 Consistency

**Applicability: Reusable for Directory Platform**

**Definition:** Whether values agree with schema rules, related records and each
other.

**Measurement:** Constraint checks such as:

- unique stable source identity;
- valid parent hierarchy;
- no orphan relationships;
- no duplicate canonical/slug in scope;
- verified status cannot coexist with missing required evidence;
- `not_found` contact state cannot coexist with a published direct contact;
- address/PLZ/city and source identity do not conflict;
- computed URL and route binding agree.

**Desired value:**

- 100% hard referential and uniqueness checks;
- zero unresolved critical contradictions;
- fewer than 0.5% soft consistency warnings, all assigned for review.

**User impact:** Consistency prevents duplicate cards, wrong district placement,
broken URLs and contradictory trust signals.

### 6.5 Traceability

**Applicability: Reusable for Directory Platform**

**Definition:** Ability to identify the origin, source, reviewer, verification
time and publication decision for a value.

**Measurement:** Weighted coverage of required provenance facts:

- origin;
- source reference;
- source access/check date;
- actor/reviewer;
- publication/change event;
- policy version.

Official batch metadata may provide one source reference for a controlled set of
base fields. Editorial and contact values should have field-level provenance.

**Desired value:**

- 100% source coverage for published editorial claims and verified contacts;
- 100% official batches have source name/date/reference;
- 100% manual publication decisions identify an actor and timestamp.

**User impact:** Traceability supports correction, transparency and faster
resolution when information is questioned.

### 6.6 Editorial Confidence

**Applicability: Reusable for Directory Platform**

**Definition:** Evidence-based confidence that editorial content is specific,
supported, neutral and correctly attributed. It is not reviewer intuition and
not a machine-learning probability.

**Measurement:** A documented rubric, for example:

| Evidence component | Default share |
|---|---:|
| Source quality and directness | 40% |
| Entity/field identity match | 25% |
| Human review and publication record | 20% |
| No unresolved source conflict | 15% |

AI assistance never adds confidence and never acts as a source.

**Desired value:**

- every published editorial item meets the catalog's minimum threshold;
- recommended default threshold: 80/100;
- no unsupported medical, ranking or promotional claim is published.

**User impact:** High editorial confidence makes descriptions useful without
presenting researched additions as official facts.

## 7. Universal Quality Score Model

**Applicability: Reusable for Directory Platform**

### 7.1 Policy contract

**Applicability: Reusable for Directory Platform**

Each catalog defines a versioned `QualityPolicy` concept containing:

- catalog and entry type;
- policy version/effective date;
- field taxonomy;
- required/recommended/optional rules;
- critical fields and publication gates;
- validators;
- accepted source classes;
- freshness SLA and warning window per field;
- dimension weights;
- score bands;
- sampling and review rules.

The policy is project configuration, not a hard-coded global list of healthcare
fields.

### 7.2 Field quality facts

**Applicability: Reusable for Directory Platform**

For each evaluated field, the model needs facts rather than one opaque score:

- applicable/present;
- normalized and syntactically valid;
- origin;
- verification state;
- verified at/by;
- source references;
- freshness state;
- unresolved conflict;
- publication state;
- protected/manual status;
- policy version used.

The same facts support score explanation, dashboard filters and audit history.

### 7.3 Default dimension weights

**Applicability: Reusable for Directory Platform**

Recommended platform defaults:

| Dimension | Weight |
|---|---:|
| Completeness | 25 |
| Accuracy | 25 |
| Freshness | 15 |
| Consistency | 15 |
| Traceability | 15 |
| Editorial Confidence | 5 |
| Total | 100 |

Catalogs may change weights only through a new policy version. Editorial
Confidence is `not_applicable` to a purely official record with no editorial
content; its weight is then removed and remaining applicable weights are
renormalized.

### 7.4 Calculation

**Applicability: Reusable for Directory Platform**

```text
overall_score =
    sum(applicable dimension score × dimension weight)
    ÷ sum(applicable dimension weights)
```

Calculate and retain conceptually:

1. field results;
2. dimension scores;
3. entry score;
4. catalog aggregates.

Rounding occurs only for display. Internal aggregation should use unrounded
values so catalog KPIs are not distorted.

### 7.5 Gates and caps

**Applicability: Reusable for Directory Platform**

An average must not mask critical failure:

- missing stable identity: publication blocked;
- invalid/orphan parent relation: publication blocked;
- unsafe URL or invalid canonical: affected value/publication blocked;
- published editorial claim without required source: publication blocked;
- known entity mismatch: score capped at 49 and entry queued for review;
- overdue critical field: `Needs Review` even when score remains high;
- archived records excluded from normal public-quality aggregates;
- unknown facts receive no positive score.

Exact gates belong to the catalog policy and must be tested independently of
the arithmetic score.

### 7.6 Internal score bands

**Applicability: Reusable for Directory Platform**

| Score | Internal interpretation | Default action |
|---:|---|---|
| 90–100 | Strong evidence and coverage | Maintain scheduled review |
| 75–89 | Usable with improvement opportunities | Queue missing/stale fields |
| 60–74 | Material gaps | Prioritized editorial review |
| 0–59 | Insufficient or critical issue | Investigate gates before publication |

These labels are internal workflow aids. They must not appear as a public rating
of an organization without a separate product, legal and UX decision.

### 7.7 Aggregation rules

**Applicability: Reusable for Directory Platform**

- Report median and distribution, not only average score.
- Weight entries equally unless a documented reason says otherwise.
- Never mix entry types with different policies without separate breakdowns.
- Show numerator, denominator and excluded/not-applicable counts.
- Keep score history with policy version; do not compare incompatible versions
  as one trend.
- A catalog score cannot override record-level publication gates.
- Random sample verification must accompany self-reported dashboard metrics.

### PflegeIndex profile

**Applicability: PflegeIndex only**

The future internal policy would treat at minimum these as critical:

- `source_id` and facility identity;
- name;
- address/PLZ/city consistency;
- type/source classification;
- valid City relation;
- canonical route identity;
- no known source mismatch.

Contacts and descriptions improve utility but missing e-mail alone must not
block publication. GeoCore review status should affect geographic coverage and
consistency dimensions without removing valid City/Facility pages.

## 8. Review Lifecycle

**Applicability: Reusable for Directory Platform**

```text
Imported
→ Reviewed
→ Verified
→ Published
→ Needs Review
→ Reviewed
→ Verified
→ Published
→ Archived
```

The lifecycle is a workflow view. Implementations should still store
verification and publication as separate dimensions so a published entry can
be marked `Needs Review` without losing its history.

### 8.1 Imported

**Applicability: Reusable for Directory Platform**

Entry/candidate passed file/schema parsing and has source metadata.

Entry may transition to `Reviewed` only after:

- stable identity is present;
- duplicate check ran;
- required parent/location can be evaluated;
- raw source is retained or attributable;
- import did not overwrite protected manual fields.

Parsing success alone never produces `Verified`.

### 8.2 Reviewed

**Applicability: Reusable for Directory Platform**

An editor/reviewer has examined identity, changes, sources, validators and
conflicts.

Transitions:

- `Reviewed → Verified` when all required evidence and gates pass;
- `Reviewed → Needs Review` when evidence is incomplete or conflicting;
- candidate may be rejected without changing the current published version.

### 8.3 Verified

**Applicability: Reusable for Directory Platform**

The reviewed version meets the current policy and records reviewer/time/source.

Transitions:

- `Verified → Published` through an explicit publication decision;
- `Verified → Needs Review` if source/evidence changes before publication;
- `Verified → Archived` only when the candidate/version is intentionally
  retired.

Verification does not publish automatically.

### 8.4 Published

**Applicability: Reusable for Directory Platform**

The version is public and immutable as a historical version.

Transitions:

- `Published → Needs Review` on SLA expiry, source change, error report,
  conflicting import or failed validation;
- `Published → Archived` after confirmed closure/duplicate/replacement under URL
  and retention policy;
- a new verified version replaces it atomically while retaining history.

Stale non-critical data may remain public while queued, according to project
policy. Critical identity/safety conflicts require containment and explicit
publication handling.

### 8.5 Needs Review

**Applicability: Reusable for Directory Platform**

The entry has a reason code:

- `freshness_expired`;
- `source_unavailable`;
- `source_changed`;
- `conflict`;
- `user_report`;
- `import_drift`;
- `validation_failed`;
- `missing_required`;
- `manual_review`.

Transitions:

- `Needs Review → Reviewed` when a reviewer starts a controlled change;
- unchanged evidence can be re-verified with a new review record;
- value is not cleared merely because a source temporarily fails.

### 8.6 Archived

**Applicability: Reusable for Directory Platform**

Archive requires reason, actor, timestamp, redirect/publication decision and
retention handling. It is not automatic deletion.

Typical reasons:

- confirmed closure;
- confirmed duplicate merged into another entry;
- source identity replaced;
- catalog scope changed;
- legal removal decision.

Restoration creates a new reviewed version and does not erase archive history.

### PflegeIndex lifecycle mapping

**Applicability: PflegeIndex only**

- Facility JSON import corresponds to `Imported` but currently writes the
  active table directly.
- `pflegeindex:audit` compares official base fields and reports mismatches.
- Contact suggestions represent unreviewed/review candidates.
- `accepted`/`rejected` records preserve the parser-review decision.
- Description drafts represent private draft/in-review content.
- Description publication requires sources and checked date.
- `contact_status=verified` records a current contact-review outcome.
- A complete universal version/history lifecycle is not yet implemented.

## 9. Data Quality KPIs

**Applicability: Reusable for Directory Platform**

### 9.1 KPI definitions

**Applicability: Reusable for Directory Platform**

| KPI | Definition |
|---|---|
| Entries with any usable contact | Published entries with at least one valid active contact / applicable published entries |
| Entries with complete expected contacts | Entries meeting the entry-type contact policy / applicable published entries |
| Verified entries | Published entries whose critical fields are verified under current policy / applicable published entries |
| Average verification age | Mean days since last qualifying verification; always pair with median and P90 |
| Freshness compliance | Entries within critical-field SLA / applicable published entries |
| Unique descriptions | Published, source-backed, reviewed, non-template descriptions / applicable published entries |
| Confirmed source coverage | Values requiring provenance with valid source reference / such published values |
| Pending suggestions | Count of undecided imported/reported candidates |
| Review lead time | Time from candidate creation to accepted/rejected decision; report median and P90 |
| Publication lead time | Time from verified candidate to publication |
| Source conflict backlog | Open conflicts grouped by severity and age |
| Critical validation failures | Count of publication-blocking failures; target zero |
| Import drift | Source-vs-current base-field mismatch count |
| Geographic coverage | Entries reachable through complete project geography / applicable entries |
| Correction recurrence | Entries receiving repeated confirmed corrections inside the review window |

### 9.2 KPI rules

**Applicability: Reusable for Directory Platform**

- Define `valid`, `complete`, `unique`, `verified` and `recent` in the policy.
- Show snapshot timestamp and timezone.
- Show production/local/staging environment.
- Show numerator, denominator and excluded entries.
- Keep missing separate from invalid and not applicable.
- Use full distributions for age and score.
- Do not rank individual editors by raw throughput without complexity/quality
  context.
- Do not improve KPI by deleting hard records from the denominator.
- Recalculate historical trends under their original policy version.
- Validate dashboard queries against a reproducible audit sample.

### PflegeIndex baseline

**Applicability: PflegeIndex only**

The read-only local snapshot documented on 22 July 2026 contains:

| KPI precursor | Count | Share of 1,557 |
|---|---:|---:|
| Phone present | 834 | 53.6% |
| Website present | 834 | 53.6% |
| E-mail present | 558 | 35.8% |
| Description present | 1,557 | 100% |
| Description with sources and checked date | 1,020 | 65.5% |
| Description without checked date | 537 | 34.5% |
| Verified contact with source and checked date | 834 | 53.6% |
| Contact or description reviewed within 12 months | 1,177 | 75.6% |
| Geo district coverage | 1,552 | 99.68% |

Contact-suggestion snapshot:

| State | Count |
|---|---:|
| Pending | 23 |
| Accepted | 87 |
| Rejected | 5 |

These are precursors, not final KPI values. For example, `description present`
does not prove uniqueness, and `phone present` does not prove the number still
belongs to the facility.

## 10. Dashboard Concept

**Applicability: Reusable for Directory Platform**

The future Data Quality Dashboard is an authenticated operational tool. It must
not expose internal notes, reviewer identity, source conflicts or unpublished
data publicly.

### 10.1 Header and snapshot context

**Applicability: Reusable for Directory Platform**

Always display:

- catalog/environment;
- snapshot timestamp/timezone;
- QualityPolicy version;
- selected filters;
- last successful calculation;
- warnings when data is partial or stale.

### 10.2 Primary cards

**Applicability: Reusable for Directory Platform**

Recommended cards:

1. Published entries.
2. Median overall quality score.
3. Critical failures.
4. Verified-entry coverage.
5. Freshness compliance.
6. Source traceability coverage.
7. Entries needing review.
8. Pending suggestions.
9. Median/P90 review lead time.
10. Geographic coverage when applicable.

Cards must show count and rate, comparison period, denominator and direct link
to the filtered worklist.

### 10.3 Charts

**Applicability: Reusable for Directory Platform**

| Chart | Purpose |
|---|---|
| Dimension trend | Completeness, accuracy, freshness, consistency, traceability and confidence over time |
| Score distribution | Avoid hiding low-quality tail behind an average |
| Lifecycle funnel | Imported → reviewed → verified → published → needs review |
| Verification-age buckets | Current, due soon, overdue, unknown |
| Missing/invalid fields | Prioritize high-impact enrichment |
| Source coverage | Official/provider/editorial/unknown provenance |
| Review throughput | Created, decided, published and overdue per period |
| Regional/type heatmap | Detect systematic catalog coverage gaps |

Charts must remain useful without color alone and provide accessible table
alternatives.

### 10.4 Filters

**Applicability: Reusable for Directory Platform**

- catalog/project;
- entry type/category;
- region/location scope;
- lifecycle state;
- publication state;
- origin;
- verification/freshness state;
- score band and dimension;
- missing/invalid field;
- source class/domain;
- protected/manual status;
- assigned reviewer;
- created/reviewed date range;
- reason code;
- policy version.

Filters must preserve denominator visibility and provide a reset action.

### 10.5 Priority worklists

**Applicability: Reusable for Directory Platform**

| Priority | Worklist examples |
|---|---|
| P0 | Orphan/duplicate identity, unsafe URL, known wrong entity, corrupt source/import, publication gate failure |
| P1 | Confirmed correction, overdue critical contact, official-source conflict, broad import drift |
| P2 | Missing useful contact, description without provenance, due-soon review, broken source URL |
| P3 | Optional enrichment, media, non-critical metadata improvements |

Sort by severity, user impact, age and number of affected entries. A high-volume
easy task must not hide a low-volume critical integrity issue.

### 10.6 Daily editor tasks

**Applicability: Reusable for Directory Platform**

The dashboard should guide the editor through:

1. confirmed correction/error reports;
2. P0/P1 validation failures;
3. oldest pending suggestions;
4. overdue source and contact reviews;
5. new import/source mismatches;
6. drafts ready for review;
7. source conflicts;
8. a small random verification sample;
9. publication queue;
10. follow-up/next-review scheduling.

Each item needs source links, field diff, previous value, reason, protected
status, available actions and audit-history context.

### 10.7 Accessibility and performance

**Applicability: Reusable for Directory Platform**

- keyboard-accessible tables, filters and actions;
- clear heading order and focus states;
- text/status icon in addition to color;
- accessible chart alternatives;
- pagination/aggregation rather than loading all entries;
- no N+1 query pattern;
- cached aggregate snapshots where justified;
- live critical counts must show calculation age;
- exports must respect authorization and personal-data minimization.

### PflegeIndex dashboard profile

**Applicability: PflegeIndex only**

Initial PflegeIndex cards should include:

- LASV source mismatch count;
- verified contacts with source/date;
- missing phone/website/e-mail;
- source-backed descriptions;
- pending contact suggestions;
- cities/facilities with unresolved GeoCore coverage;
- protected contacts;
- overdue checks;
- invalid contact/source URLs;
- verified-without-contact contradictions.

Filters should include facility type, city, district, contact status, contact
field presence, description state, GeoCore review state and source availability.

## 11. Governance and policy versioning

**Applicability: Reusable for Directory Platform**

Every quality-policy change records:

- old/new version;
- effective date;
- changed fields, validators, weights, SLAs or gates;
- reason and approver;
- expected score/KPI impact;
- whether historical recalculation is allowed;
- dashboard/release notes.

Do not compare trends across incompatible policy versions without a visible
break or recalculated series. Do not adjust weights solely to make a KPI look
better.

Recommended review cadence:

- monthly operational KPI review;
- quarterly policy review;
- immediate review after a systemic correction, source change or SEV-1/SEV-2
  data incident;
- catalog-specific review before a new entry type is launched.

## 12. Verification strategy

**Applicability: Reusable for Directory Platform**

Future implementation must verify:

- deterministic score for the same facts and policy version;
- correct N/A weight renormalization;
- zero positive credit for unknown/missing facts;
- critical gates override high averages;
- lifecycle transitions reject invalid shortcuts;
- drafts/rejected candidates do not affect public KPIs;
- archived entries are excluded correctly;
- KPI numerator/denominator match independent queries;
- per-field source/freshness status is explainable;
- policy changes do not rewrite historical meaning silently;
- dashboard filters preserve correct totals;
- calculations do not introduce N+1 queries;
- no public claim rates an entry's service quality.

Operational validation should include periodic random samples against primary
sources. Automated tests prove calculation consistency, not real-world truth.

### PflegeIndex verification inputs

**Applicability: PflegeIndex only**

Reuse existing evidence where appropriate:

- `pflegeindex:audit` source-vs-database mismatch checks;
- GeoCore integrity, uniqueness and hierarchy checks;
- contact suggestion decisions;
- description source/check metadata;
- URL validation;
- current Trust Layer tests;
- public route/canonical/sitemap tests.

Do not execute imports or GeoCore mapping merely to calculate a dashboard.

## 13. Platform Reuse

**Applicability: Reusable for Directory Platform**

Universal concepts:

- classification axes;
- six quality dimensions;
- QualityPolicy contract;
- field facts and explainable score result;
- lifecycle and transition validation;
- KPI definitions;
- dashboard cards/charts/filter contracts;
- priority/reason codes;
- policy versioning;
- audit and sampling rules.

Project adapters/configuration provide:

- entry types and field taxonomy;
- required/critical/applicable fields;
- source authority hierarchy;
- validators;
- freshness windows;
- dimension weights and gates;
- project-specific KPIs;
- terminology and dashboard labels;
- entry links and permissions.

The reusable framework must not depend on:

- Eloquent `Facility`;
- LASV, Pflegefonds or Brandenburg;
- PflegeIndex routes/slugs;
- healthcare field names;
- the current public Trust Layer weights;
- a specific database engine;
- a particular admin Blade layout.

### PflegeIndex-only policy

**Applicability: PflegeIndex only**

PflegeIndex retains:

- LASV/official base-data attribution;
- Pflege facility taxonomy;
- GeoCore mapping profile for Brandenburg;
- public `PflegeIndex Qualität` wording and weights;
- medical/editorial claim restrictions;
- project contact-review workflow;
- current canonical routes and SEO behavior;
- German UI terminology.

## 14. Anti-gaming and interpretation rules

**Applicability: Reusable for Directory Platform**

- Never fill a field with placeholder text to increase completeness.
- Never mark a provider homepage as a facility-specific source without identity
  evidence.
- Never reset verification age because an unrelated field changed.
- Never count AI-generated text as unique/verified without source-backed human
  review.
- Never drop unresolved records from KPI denominators without reporting the
  exclusion.
- Never convert `unknown` to `not_applicable` without a policy rule.
- Never treat official origin as permanent accuracy.
- Never average away a critical gate failure.
- Never use quality score to rank organizations or imply service quality.
- Never publish individual editor leaderboards from raw throughput.

## 15. Recommended implementation sequence

**Applicability: Reusable for Directory Platform**

No implementation is part of this sprint. A future sequence should be:

1. Approve classification vocabulary and QualityPolicy v1.
2. Define PflegeIndex field taxonomy, critical fields and freshness SLAs.
3. Create read-only metric queries and validate them against manual samples.
4. Add explainable field/dimension result objects without changing public UI.
5. Build an authenticated read-only dashboard from aggregate queries.
6. Add priority worklists using existing suggestions/review metadata.
7. Introduce field-level provenance/history through the approved Editorial
   Model plan.
8. Consider any public Trust Layer evolution only as a separate product/UX/SEO
   task.

## 16. Acceptance criteria for a future implementation

**Applicability: Reusable for Directory Platform**

The framework is operationally ready when:

- all scores cite policy version and snapshot time;
- every score is explainable down to field facts;
- all dimensions have tested numerator/denominator rules;
- critical gates cannot be bypassed by weighting;
- N/A, missing, invalid and unknown remain distinct;
- lifecycle transitions are auditable;
- source and freshness coverage are measurable;
- dashboard priorities link to actionable records;
- metrics match independent audit samples;
- no public behavior changes without separate approval;
- a second catalog can supply its own policy without PflegeIndex dependencies.

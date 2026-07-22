# Editorial Standard

## 1. Purpose

This document is the official editorial standard for PflegeIndex lexicon articles and for comparable editorial content in future Directory Platform products. It defines how articles are planned, written, verified, linked, reviewed, and maintained.

A consistent format helps readers find answers quickly, compare related topics, and understand which statements are supported by reliable sources. It also makes editorial review predictable and allows structured content to be reused without creating a separate template for every article.

The current PflegeIndex lexicon stores articles as structured configuration data rather than individual Blade files. This standard governs that content; it does not change the configuration schema, templates, routes, or SEO implementation.

### Reference Implementation: PflegeIndex Lexicon Schema

Editors must use only fields supported by the current application unless a separate technical change has been approved.

| Editorial purpose | Current structured field |
|---|---|
| Title | `title` |
| Alphabetical grouping | `letter` |
| Summary and meta-description basis | `summary` |
| Introduction | `intro` |
| Main H2 topics | Ordered entries in `sections` with `title` and `body` |
| Practical orientation | `tip` |
| Sources | `sources` with `label` and `url` |
| Related lexicon articles | `related` containing valid article slugs |
| Last verified date | `checked_at` |
| Example and legal-context profile | Corresponding entry in `config/lexicon_details.php` |

The standard article structure below is semantic. Optional topics such as advantages, disadvantages, costs, and FAQ content may be expressed through appropriately titled `sections` when the current renderer supports the required presentation. Editors must not add unsupported keys, HTML, or Markdown to plain-text fields. A table of contents is recommended only for a long-form format whose renderer supports it; it may be omitted from current short lexicon entries.

## 2. Editorial Philosophy

Our editorial work is guided by a core philosophy that prioritizes long-term value over short-term metrics:

- **Quality over Quantity:** A single well-researched, high-quality article is worth more than dozens of thin or automated pages.
- **User over Search Engine:** We write for real people seeking answers, not for web crawlers. If a sentence does not help the reader, it does not belong in the article.
- **Speed to Value:** Each article should answer the user's primary question as quickly and clearly as possible. Respect the reader's time.
- **Accuracy over Volume:** We prefer short, factually correct content over lengthy paragraphs filled with fluff or unverified claims.
- **Trust over SEO:** Building and preserving long-term trust is our primary objective. We never compromise factual integrity for temporary search ranking gains.

## 3. Editorial Principles

### User first

Every article must solve a real user question. Lead with the information that helps a reader understand the topic or decide what to do next. Do not add sections merely to make an article longer.

### Factual accuracy

Statements must be accurate, current enough for their purpose, and proportionate to the available evidence. Distinguish general orientation from conditions that depend on an individual case, contract, authority, insurer, or current law.

### Official and primary sources

Prefer legislation, public authorities, statutory bodies, insurers, and other authoritative primary sources. Provider material may support provider-specific facts but should not be treated as independent evidence for broad claims.

Search snippets, user reviews, and generative AI output are not sources. They may help identify a source, but the underlying source must be opened and checked before publication.

### Simple German

Public articles are written in clear, respectful German. Explain specialist terms on first use, prefer direct sentences, and avoid unnecessary bureaucracy, jargon, and unexplained abbreviations. The tone must remain suitable for older people, relatives, and readers under time pressure.

### No SEO spam

Use search terms only where they help the reader. Do not repeat keywords unnaturally, create near-duplicate passages, or add low-value text to reach an arbitrary length.

### No AI filler

Every sentence must contribute verified meaning. Generic introductions, repetitive conclusions, invented examples, and unreviewed machine-generated copy must not be published. Editorial responsibility always remains with a human reviewer.

### Neutrality and independence

Articles must be informational rather than promotional. Do not rank, endorse, or disparage providers without a documented editorial basis. Commercial relationships must never influence factual presentation or internal ranking.

## 4. Standard Article Structure

Use the following order when the subject requires all sections. A section may be omitted when it would not help answer the user's question. Do not publish an empty, repetitive, or artificial section merely to complete the pattern.

### Title

Name the topic in the wording users are likely to recognise. Keep the title specific and avoid promotional claims, clickbait, and unnecessary qualifiers. The page template is responsible for rendering the single H1.

### Summary

Answer the main question in one or two concise sentences. The summary must remain meaningful when displayed outside the article and must accurately support the generated meta description.

### Introduction

Explain why the topic matters and establish the scope of the article. Do not repeat the summary word for word. State important limitations early when rules differ by situation.

### Table of Contents

Use a table of contents for longer articles when the presentation layer supports it. Labels must match visible section headings and link only to sections on the same page. Current short PflegeIndex lexicon entries may omit it because the existing renderer does not provide this feature.

### What is ...?

Give a plain-language definition. Clarify whether the term describes a service, legal concept, funding mechanism, facility type, or process. Avoid circular definitions.

### Who is it for?

Describe the relevant audience or eligibility conditions without diagnosing a reader or promising entitlement. Identify the responsible authority or professional decision where applicable.

### How does it work?

Explain the process in a logical order. Mention applications, assessments, documents, responsible bodies, or common decision points only when verified and relevant.

### Advantages

Describe practical benefits in neutral, conditional language. Do not imply that one option is universally better or guarantee an outcome.

### Disadvantages

Describe limitations, obligations, possible waiting periods, costs, or trade-offs fairly. Do not use alarmist language.

### Costs / Funding

Include this section only when costs or funding are material to the topic. State what may be covered, what may remain payable, and which conditions apply. Time-sensitive amounts, thresholds, and rules require an authoritative source and a clear effective or verification date.

### FAQ

Include only genuine recurring questions not already answered clearly in the main text. Keep each answer short and self-contained. Under the current lexicon schema, a question may be a section title and its answer the section body; this does not by itself create FAQ structured data.

### Related Topics

Offer a small set of directly relevant next steps. For the current lexicon, use valid slugs in `related`; the application filters these links against existing terms.

### Sources

List the authoritative pages used to verify material claims. Labels must identify the organisation and subject clearly. Link to the most specific stable source page available rather than a generic homepage or search result.

## 5. Writing Guidelines

- Use short paragraphs, normally two to four sentences.
- Keep one main idea per paragraph.
- Prefer common German words and active constructions.
- Explain technical, legal, or care-related terms when first introduced.
- Address the reader respectfully and avoid assumptions about age, health, family structure, or finances.
- Use lists only when they make steps, conditions, or comparisons easier to scan.
- Keep terminology consistent across the title, summary, article, related links, and catalogue pages.
- Separate facts from examples. Examples must be clearly marked, realistic, and must not imply an individual entitlement or outcome.
- Do not use advertising language such as guarantees, superlatives, urgency tactics, or unsupported quality claims.
- Do not provide diagnoses, treatment instructions, or personalised medical recommendations.
- Do not present general information as individual legal, financial, or insurance advice.
- Do not publish unsupported statements, invented figures, fabricated quotations, or guessed facility information.
- Qualify claims with words such as `kann`, `in der Regel`, or `abhängig von` only when the qualification is factually justified, not as a substitute for verification.
- Do not place HTML or Markdown in fields rendered as plain text.

## 6. Internal Linking

Internal links must help the reader continue a relevant task or understand a necessary prerequisite.

- Link to related lexicon articles when they explain a concept used in the current article or provide a clear next step.
- Link to relevant catalogue, region, city, or facility-type pages only when the destination matches the article's subject and supports the user's intent.
- Prefer a small number of strong links over a long list. As a general editorial target, two to five related topics are sufficient for most lexicon entries.
- Use descriptive link labels. Avoid vague labels such as “click here”.
- Link only to existing public routes and canonical destinations.
- Do not create reciprocal links automatically, force links for SEO, or link unrelated articles because they contain a similar keyword.
- Do not embed unsupported links in plain-text configuration fields. If the current schema cannot express a useful catalogue link, record it as an editorial proposal for a separately approved implementation.
- Check every link before publication and during substantive review.

## 7. Trust

### Source hierarchy

Use sources in this order where available:

1. Legislation, official authorities, and statutory public bodies.
2. Official insurer, funding body, or professional-regulator guidance.
3. Original provider documentation for facts specifically about that provider or service.
4. Reputable secondary sources for context when a primary source is unavailable or insufficient.

Important legal, eligibility, funding, or cost claims should not rely solely on a secondary source.

### Verification date

Set `checked_at` only after an editor has opened the cited sources and verified that the published article remains accurate. A formatting-only edit is not a new factual review. Recheck time-sensitive content whenever the underlying rule or source changes.

### Transparency

Readers must be able to understand where important information originates. Source labels should name the responsible organisation, and the article should distinguish official rules from editorial explanation or illustrative examples.

### No invented data

Never infer missing dates, amounts, eligibility, contacts, services, or outcomes. If a reliable fact cannot be confirmed, omit it or state the uncertainty plainly. Do not turn an assumption into a definitive statement.

## 8. SEO Guidelines

SEO supports discoverability but never overrides accuracy or usefulness.

- Each article has one H1, generated from its title by the page template.
- Organise the article with descriptive H2 headings in a logical order. Do not skip levels merely for visual styling.
- Keep the title unique, accurate, and aligned with the primary user question.
- Write a specific summary that can support an accurate meta description; do not stuff it with variants of the same keyword.
- Use the principal term and natural synonyms where they fit the explanation.
- Answer the main question early and make each section independently understandable.
- Avoid duplicate or near-duplicate articles that target spelling variants without a distinct user need.
- Do not create text solely to increase word count, keyword frequency, or the number of indexable pages.
- Internal links must follow the relevance rules in this standard.
- Canonical tags, Open Graph, robots directives, and structured data are application concerns. Editors must not imitate or override them inside article content.

## 9. Quality Checklist

Complete this checklist before publication and after every substantive update.

### User value and structure

- [ ] The article answers the user's main question in the summary and opening content.
- [ ] The scope is clear, and the article does not promise more than it explains.
- [ ] Sections follow a logical order, and optional sections exist only when useful.
- [ ] Paragraphs are short, focused, and easy to scan.
- [ ] The German is clear, respectful, and appropriate for the target audience.

### Accuracy and trust

- [ ] Every material claim has been checked against an appropriate source.
- [ ] Official or primary sources are used wherever available.
- [ ] Costs, eligibility rules, deadlines, and legal statements are current and qualified correctly.
- [ ] Sources link to the exact pages used and have clear labels.
- [ ] `checked_at` reflects a genuine factual review.
- [ ] Facts, editorial explanations, and examples are distinguishable.
- [ ] The article contains no invented data, unsupported claims, or fabricated examples.
- [ ] The article contains no personalised medical, legal, financial, or insurance advice.
- [ ] The article contains no advertising promises, hidden endorsements, or exaggerated claims.

### Links and discoverability

- [ ] Related materials are genuinely useful and not selected randomly.
- [ ] Every internal and external link resolves to the intended destination.
- [ ] All lexicon links use valid existing slugs and all catalogue links use canonical public URLs.
- [ ] The title is unique and the page has one clear H1.
- [ ] H2 headings describe the content accurately and follow a logical hierarchy.
- [ ] Keywords occur naturally; there is no repetition written solely for SEO.

### Structured-content integrity

- [ ] Only fields supported by the current Lexicon implementation are used.
- [ ] Required fields contain meaningful content and no placeholder text.
- [ ] Plain-text fields contain no HTML or Markdown.
- [ ] The article has been reviewed in its rendered page, including mobile presentation.
- [ ] A human editor has approved the final text.

## 10. AI Usage Policy

Generative AI is a tool to support editorial efficiency, but it does not replace human judgment, verification, or responsibility.

- **Drafts and Style:** Editors may use AI to draft initial structures, summarize raw materials, or suggest stylistic improvements.
- **Fact-Checking:** AI tools are not sources of truth. All factual claims, figures, dates, and names must be verified independently against official primary sources.
- **No Fabrications:** Publishing AI-generated fabrications, mock data, or fake statistics is strictly prohibited.
- **Medical and Legal Claims:** Do not publish medical advice, treatment instructions, or legal guidance unless verified against official guidelines or statutory sources.

## 11. Content Freshness

Information changes, and articles must be reviewed periodically to ensure accuracy.

- **Periodic Review:** Content should be revisited systematically to verify that referenced regulations and external links remain functional.
- **High-Priority Content:** Articles covering legislation, insurance rules, public funding, and government programs require frequent reviews as underlying rules are subject to change.
- **Low-Priority Content:** Basic terminology and definitions are historically stable and typically require less frequent updates.

## Maintenance and Platform Reuse

Articles should be reviewed when a cited source changes, a rule becomes time-sensitive, a broken link is detected, or user feedback identifies an ambiguity. A full review is preferable to changing a single number without checking the surrounding explanation.

The principles, article structure, trust rules, linking discipline, and quality checklist are reusable for Directory Platform. Audience language, terminology, permitted claims, authoritative sources, catalogue destinations, and review frequency remain project-specific and must be defined for each directory. Reuse the standard only after adapting those project-specific elements; never copy PflegeIndex facts into another vertical without verification.

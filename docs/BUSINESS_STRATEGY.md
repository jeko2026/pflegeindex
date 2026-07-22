# Business Strategy 2026–2028

## 1. Назначение и границы

**Scope: Reusable for Directory Platform, with PflegeIndex-specific baseline and gates.**

Этот документ определяет долгосрочную бизнес-стратегию PflegeIndex и будущей
Directory Platform. Он дополняет `PRODUCT_ROADMAP.md`: roadmap отвечает на вопрос,
что и в какой последовательности развивать, а business strategy — для кого создаётся
ценность, при каких условиях допустима монетизация и как проверить устойчивость
модели.

Документ:

- не утверждает, что PflegeIndex уже является коммерческим оператором;
- не содержит прогнозов дохода, оценок компании или обещаний окупаемости;
- не разрешает рекламу, tracking, API или платные профили автоматически;
- не заменяет юридическую, налоговую, privacy или licensing-проверку;
- не меняет принцип нейтрального органического каталога.

Дата планирования: 2026–2028 годы. Стратегия пересматривается ежеквартально и перед
каждым новым источником дохода, регионом или каталогом.

---

## 2. Business thesis

**Scope: PflegeIndex only.**

PflegeIndex создаёт ценность, уменьшая время и неопределённость при поиске информации
об учреждениях ухода. Основа продукта — официальные базовые данные, проверенные
контакты, прозрачные источники, понятная география и нейтральное редакционное
содержание.

Долгосрочная бизнес-гипотеза:

1. Пользователи и поисковые системы ценят не максимальное число карточек, а
   актуальные, понятные и прослеживаемые данные.
2. Учреждения заинтересованы в исправлении и дополнении информации, если процесс
   прозрачен и не требует оплаты за базовую точность.
3. Повторяемые процессы импорта, редакционной проверки, SEO и эксплуатации могут
   поддерживать новые регионы и вертикали с меньшими затратами на запуск.
4. Доход допустим только там, где он создаёт дополнительную ценность и не покупает
   официальный статус, quality score или organic ranking.

Первичная цель 2026 года — доказать полезность и эксплуатационную устойчивость
Brandenburg. Монетизация не должна отвлекать от закрытия production, data-quality и
editorial gaps.

---

## 3. Business Model

### 3.1 Общие правила монетизации

**Scope: Reusable for Directory Platform.**

Для всех моделей обязательны следующие правила:

- базовая карточка, корректировка фактической ошибки и удаление недостоверных данных
  не зависят от оплаты;
- платный статус не меняет official/editorial origin, verification status или
  quality score;
- платное размещение явно маркируется как `Anzeige`, `Werbung` или иным понятным
  немецким обозначением;
- organic results не смешиваются с платными позициями без визуального разделения;
- медицинские обещания, гарантии результата, неподтверждённые superlatives и
  misleading scarcity запрещены;
- third-party tracking не загружается до выполнения consent и privacy gates;
- коммерческое использование official/open data проходит отдельную licence-проверку;
- доход оценивается вместе с влиянием на доверие, accessibility, performance и
  editorial workload.

### 3.2 Near-term: после подтверждённого public launch

**Scope: PflegeIndex only for initial validation; model may later be reusable.**

#### 3.2.1 Прямое sponsorship без tracking

Небольшой статичный sponsor-блок может продаваться напрямую и отдаваться с
собственного сайта без рекламной сети, behavioural profiling и стороннего JavaScript.

| Аспект | Оценка |
|---|---|
| Преимущества | Низкая техническая сложность; предсказуемый UX; нет зависимости от ad network; возможен фиксированный контекст |
| Риски | Конфликт доверия; ручные продажи и договоры; sponsor может восприниматься как рекомендация PflegeIndex |
| Требования | Явная маркировка; отдельный блок вне organic ranking; договор, invoice/tax readiness, content policy; отсутствие tracking pixels |
| Ориентировочный этап | Не ранее стабильного v1.0 и выполнения Advertising Gate |

Рекомендуемый формат — один спокойный sponsor-блок на ограниченном наборе страниц,
без interstitial, autoplay, sticky overlay и влияния на порядок учреждений.

#### 3.2.2 Контекстные партнёрские программы

Партнёрская ссылка может использоваться только для самостоятельной услуги, реально
полезной пользователю, и не должна выглядеть как рекомендация конкретного учреждения.

| Аспект | Оценка |
|---|---|
| Преимущества | Не требует собственного продукта; можно проверить спрос на смежную услугу |
| Риски | Комиссия влияет на редакционный выбор; legal disclosure; некачественный партнёр снижает доверие; внешняя передача данных |
| Требования | Partner due diligence; disclosure; договор и privacy review; nofollow/sponsored policy; измерение без необязательного tracking по умолчанию |
| Ориентировочный этап | После 90-дневного traffic baseline и Partner Gate, вероятно v1.1–v1.2 |

Не подходят программы, основанные на продаже персональных leads без явного запроса и
информированного действия пользователя.

#### 3.2.3 Поддержка проекта

Добровольная поддержка может быть рассмотрена только после определения юридического
статуса оператора и допустимого платёжного процесса.

| Аспект | Оценка |
|---|---|
| Преимущества | Не влияет на ранжирование и данные; понятна лояльной аудитории |
| Риски | Низкая предсказуемость; payment provider обрабатывает персональные данные; налоговые требования |
| Требования | Operator/tax review; privacy disclosure; прозрачное назначение поддержки; отсутствие обещания встречной услуги |
| Ориентировочный этап | Опционально после public launch, но не приоритет v1.0 |

### 3.3 Mid-term: подтверждённая аудитория и editorial operations

**Scope: Reusable for Directory Platform after PflegeIndex validation.**

#### 3.3.1 Premium profile

Учреждение получает инструменты для добавления и регулярного подтверждения
расширенной информации: часов работы, дополнительных контактов, фотографий с правами,
официальных ссылок и обновлений профиля.

| Аспект | Оценка |
|---|---|
| Преимущества | Создаёт прямую B2B-ценность; улучшает полноту; повторяемая услуга |
| Риски | Pay-to-trust; ложные claims; рост moderation; owner impersonation; конфликт с бесплатным исправлением ошибок |
| Требования | Owner verification; roles/audit trail; moderation SLA; media rights; cancellation/export policy; бесплатная базовая корректировка |
| Ориентировочный этап | После Editorial Platform v1 и Premium Profile Gate, ориентировочно v1.2–v2.0 |

Оплата даёт дополнительные инструменты и presentation options, но не знак
`Amtliche Daten`, не более высокий score и не гарантированную позицию.

#### 3.3.2 Платное выделение карточки

Отдельный clearly labelled placement может существовать над или рядом с organic
results, но не внутри organic order без маркировки.

| Аспект | Оценка |
|---|---|
| Преимущества | Понятный коммерческий продукт; измеримый inventory |
| Риски | Наиболее высокий риск потери доверия; accessibility и layout shift; создаёт впечатление рекомендации |
| Требования | Отдельный ad slot; лимит частоты; маркировка; запрет влияния на score; performance budget; complaint process |
| Ориентировочный этап | Только после успешного direct sponsorship pilot; не является обязательной моделью |

Продажа включения в каталог или удаления конкурента недопустима.

#### 3.3.3 Data quality и verification services для операторов

Платной может быть помощь в структурировании и регулярной проверке предоставленных
оператором данных, если итог остаётся проверяемым и явно классифицированным.

| Аспект | Оценка |
|---|---|
| Преимущества | Совпадает с компетенцией PflegeIndex; повышает traceability; B2B-ценность не зависит только от traffic |
| Риски | Самоподтверждение клиента; ручные затраты; договорная ответственность за accuracy |
| Требования | Независимые verification rules; source requirements; audit trail; SLA; liability и correction policy |
| Ориентировочный этап | После Data Quality Dashboard и Editorial Platform, ориентировочно 2027 |

#### 3.3.4 Ограниченный data/API access

Read-only API может предоставлять разрешённый набор публичных и лицензируемых полей
партнёрам с конкретным use case.

| Аспект | Оценка |
|---|---|
| Преимущества | Повторяемый B2B-продукт; интеграционная ценность; полезен нескольким каталогам |
| Риски | Права на данные; устаревшие downstream copies; нагрузка; scraping substitution; персональные данные |
| Требования | Data licence matrix; versioning; authentication; rate limits; freshness metadata; SLA; revoke/change policy |
| Ориентировочный этап | Pilot после API Gate, вероятно v2.0–v3.0 |

### 3.4 Long-term: Directory Platform portfolio

**Scope: Reusable for Directory Platform.**

#### 3.4.1 Лицензирование редакционных данных

Лицензировать можно только данные, на которые у оператора есть соответствующие права.
Official/open data и собственные editorial enhancements должны учитываться отдельно.

| Аспект | Оценка |
|---|---|
| Преимущества | Монетизирует traceability и editorial investment; не зависит напрямую от page views |
| Риски | Licence incompatibility; потеря контроля над свежестью; downstream misrepresentation |
| Требования | Field-level provenance; rights registry; licence terms; update/correction feed; legal review |
| Ориентировочный этап | После устойчивого API и доказанной уникальности данных, ориентировочно 2028+ |

#### 3.4.2 SaaS для операторов каталогов

Directory Platform может предоставлять hosted workflow для импорта, editorial review,
quality management, публикации и эксплуатации отраслевого каталога.

| Аспект | Оценка |
|---|---|
| Преимущества | Повторяемая подписочная модель; использует platform capabilities; снижает зависимость от одного vertical |
| Риски | Multi-tenancy, support и security значительно усложняют продукт; customer-specific customizations разрушают platform boundary |
| Требования | Два production-каталога; tenant/security design; onboarding standard; billing; support SLA; portability и data ownership |
| Ориентировочный этап | После SaaS Gate, не ранее зрелой Directory Platform v3 |

#### 3.4.3 White-label Directory Platform

White-label позволяет партнёру использовать собственный brand/domain при общей
операционной основе.

| Аспект | Оценка |
|---|---|
| Преимущества | Крупные B2B contracts; переиспользование import, quality и operations standards |
| Риски | Forks, custom SEO/legal requirements, reputational dependency, высокая стоимость внедрения |
| Требования | Configuration boundary; theme/content contracts; per-project legal/data ownership; release compatibility; strict customization catalogue |
| Ориентировочный этап | После успешного SaaS или нескольких повторяемых каталогов, ориентировочно 2028+ |

#### 3.4.4 Managed launch service

До полноценного SaaS возможна стандартизированная услуга запуска каталога на
Directory Platform с ограниченным набором поддерживаемых вариантов.

| Аспект | Оценка |
|---|---|
| Преимущества | Проверяет спрос и onboarding раньше multi-tenant разработки; приносит реальные requirements |
| Риски | Превращение в индивидуальную agency work; скрытые support costs |
| Требования | Fixed scope; readiness questionnaire; source/licence gate; template contract; change-request policy |
| Ориентировочный этап | После второго production-каталога, до решения о SaaS |

---

## 4. Stakeholders

### 4.1 Карта заинтересованных сторон

**Scope: Reusable for Directory Platform; examples are PflegeIndex-specific.**

| Stakeholder | Ценность | Ожидания | Основной риск |
|---|---|---|---|
| Пользователи и родственники | Быстро найти понятные данные и прямой контакт | Читаемость, актуальность, нейтральность, мобильная доступность, отсутствие misleading claims | Устаревшие контакты, рекламное давление, принятие quality score за рейтинг учреждения |
| Учреждения и операторы | Корректное публичное представление и канал исправлений | Бесплатное исправление фактов, понятный source/status, справедливая moderation | Ошибочные сведения, impersonation, pay-to-correct или платное влияние на ranking |
| Редакция | Приоритетная очередь и доказуемый workflow | Источники, роли, protected fields, history, SLA и удобные инструменты | Backlog, ручные ошибки, публикация draft, неясная ответственность |
| Product/operations owner | Устойчивый продукт и управляемые затраты | Метрики, monitoring, backup, repeatable release, ясные gates | Key-person dependency, скрытые operating costs, premature expansion |
| Поисковые системы | Доступные, уникальные и технически согласованные страницы | Canonical, sitemap, useful content, performance, отсутствие spam/duplicates | Thin pages, массовое устаревание, некорректные structured data claims |
| Государственные и официальные источники | Корректное использование и attribution данных | Соблюдение licence, source fidelity, отсутствие представления сайта как official register | Изменение формата, прекращение доступа, неверная интерпретация official data |
| Партнёры и affiliates | Прозрачный доступ к релевантной аудитории | Понятные условия, измерение, brand safety | Давление на editorial choices, tracking/privacy violations, низкое качество предложения |
| Рекламодатели и sponsors | Ограниченное контекстное присутствие | Прозрачный inventory, маркировка, предсказуемое размещение | Ожидание влияния на ranking или endorsement |
| API/data customers | Стабильные, документированные и свежие данные | Versioning, SLA, licence, correction feed, rate limits | Downstream устаревание, misuse и требования к полям без прав распространения |
| Hosting и service providers | Техническое обслуживание по договору | Предсказуемая нагрузка, корректная конфигурация и контакты оператора | Outage, retention неизвестна, third-party processing не отражён в privacy docs |
| Regulators и legal/privacy reviewers | Соответствие применимым требованиям | Data inventory, lawful processing, disclosure, retention, consent | Неподтверждённые юридические утверждения или скрытая обработка данных |
| Будущие catalog owners | Быстрый и контролируемый запуск vertical | Ясная граница Platform/project, ownership данных и operations | Ожидание универсального продукта до доказанной повторяемости |

### 4.2 Правило разрешения конфликтов

При конфликте интересов используется следующий порядок:

1. безопасность, законность и точность;
2. практическая польза и доступность для пользователя;
3. независимость editorial и organic results;
4. устойчивость эксплуатации и качества данных;
5. коммерческая эффективность.

Коммерческий договор не может отменить пункты 1–4.

---

## 5. Growth Strategy

### 5.1 Brandenburg — prove the operating model

**Scope: PflegeIndex only.**

Цель этапа — не максимальный traffic, а доказательство полного цикла:

`official source → import → geo mapping → editorial enrichment → publication →
discovery → correction → re-verification → operations`.

Приоритеты:

- подтвердить public production release;
- установить 90-дневные traffic, indexing и quality baselines;
- увеличить Verified Useful Coverage;
- стабилизировать contact suggestion SLA;
- подтвердить backup/restore и incident response;
- проверить спрос на коммерческие предложения без изменения organic UX.

### 5.2 Germany — expand by state, not by page count

**Scope: PflegeIndex only; expansion method is reusable.**

Каждая новая федеральная земля проходит отдельный source, licence, GeoCore, quality,
SEO и operations review. Расширение выполняется последовательными cohorts, чтобы
ошибка источника или importer не затронула всю страну одновременно.

Порядок:

1. source/licence feasibility;
2. sample import и duplicate analysis;
3. Geo mapping coverage;
4. minimum useful contact/content coverage;
5. staged publication и indexation;
6. monitoring и correction capacity;
7. только затем следующая земля.

Germany coverage нельзя заявлять, если опубликованные данные или operational
capacity не соответствуют зафиксированному quality threshold.

### 5.3 Directory Platform — prove reuse with a real consumer

**Scope: Reusable for Directory Platform.**

Второй каталог создаётся как самостоятельный продукт с собственной аудиторией,
источниками, taxonomy, legal owner и SEO. Он использует существующий DirectoryCore
через adapter. Новые общие компоненты извлекаются только после сравнения двух реальных
реализаций.

Business objective — сократить время и риск повторного запуска, а не максимизировать
объём общего кода.

### 5.4 Additional verticals — portfolio, not clones

**Scope: Reusable for Directory Platform.**

Новый vertical допускается, если:

- решает подтверждённую информационную проблему;
- имеет поддерживаемый законный источник;
- существует путь к актуализации, а не только одноразовый import;
- каталог способен содержать полезные detail pages;
- назначены product, data и operations owners;
- vertical не требует ложной унификации taxonomy или quality policy.

Портфель развивается по одному pilot за раз. Следующий pilot не начинается, пока
предыдущий не прошёл production и operations review.

---

## 6. Decision Gates

### 6.1 Расширение PflegeIndex в новый регион

**Scope: PflegeIndex only; gate pattern is reusable.**

Все условия обязательны:

- текущий production стабилен не менее 90 дней;
- нет открытых P0 и повторяющихся критических incidents;
- availability критических маршрутов ≥99,5% без плановых окон;
- Verified Useful Coverage текущего региона ≥65%;
- freshness within 12 months ≥85%;
- для новой земли подтверждены source, licence и update frequency;
- sample import даёт ≥90% корректно сопоставляемых записей либо документированный
  manual-review capacity;
- есть budget и owner для обработки полного ожидаемого review backlog;
- sitemap/SEO assessment исключает массовые thin и duplicate pages;
- backup, rollback и staged publication plan проверены.

### 6.2 Запуск второго каталога

**Scope: Reusable for Directory Platform.**

Все условия обязательны:

- PflegeIndex стабильно эксплуатируется 8–12 недель после последнего major release;
- median contact suggestion review ≤7 дней и p90 ≤30 дней;
- operations handbook выполняется без просроченных critical actions;
- у второго каталога есть named audience, validated problem и product owner;
- source/licence/data-refresh process документированы;
- minimum viable dataset способен создать полезные, не thin detail pages;
- определены как минимум два реальных shared use case;
- запуск укладывается в доступную editorial и support capacity без ухудшения KPI
  PflegeIndex;
- есть отдельные privacy, legal, SEO и rollback sign-offs.

Пустой test adapter не считается выполнением этого gate.

### 6.3 Введение рекламы или sponsorship

**Scope: Reusable for Directory Platform; initial pilot is PflegeIndex only.**

Все условия обязательны:

- минимум три последовательных месяца стабильного organic traffic baseline;
- нет ухудшения Core Web Vitals или accessibility на pilot pages;
- утверждены advertising, prohibited-claims и labelling policies;
- подтверждён operator/tax/invoice process;
- direct demand подтверждён минимум тремя qualified advertiser conversations или
  одним договорным pilot без эксклюзивного влияния на editorial;
- определены maximum one ad unit per viewport и отсутствие interstitial/autoplay;
- third-party requests отсутствуют либо consent/privacy gates полностью выполнены;
- A/B или before/after review измеряет trust complaints, contact-action rate и
  performance, а не только рекламные клики.

Pilot останавливается при misleading feedback, заметном ухудшении UX или требовании
партнёра влиять на organic ranking.

### 6.4 Запуск Premium Profile

**Scope: Reusable for Directory Platform after PflegeIndex pilot.**

Все условия обязательны:

- реализованы owner verification, roles, moderation и audit trail;
- бесплатная корректировка базовых фактов остаётся доступной;
- protected official/editorial fields невозможно незаметно перезаписать;
- есть минимум пять qualified institution interviews и не менее двух pilot intents;
- определены included fields, media rights, SLA, cancellation и data export;
- paid status визуально отделён и не влияет на quality score/organic order;
- privacy/legal/security review завершён.

### 6.5 Запуск API

**Scope: Reusable for Directory Platform.**

Все условия обязательны:

- минимум три qualified external use cases и два готовых pilot consumers;
- для каждого exposed field известны origin, licence и redistribution rights;
- data freshness и correction propagation измеряются;
- API имеет versioning, authentication, rate limits, monitoring и deprecation policy;
- projected load проверен без ухудшения public site;
- определены SLA, support owner и incident/abuse process;
- API не раскрывает персональные или internal editorial данные без основания.

### 6.6 Переход к SaaS или white-label

**Scope: Reusable for Directory Platform.**

Все условия обязательны:

- минимум два реальных каталога работают в production не менее шести месяцев;
- новый каталог запускается по стандартному process без fork общего ядра;
- типовой onboarding после готовности данных занимает не более 10 рабочих дней;
- ≥70% требований нового клиента покрываются configuration и documented extension
  points, а не custom core changes;
- есть минимум три qualified B2B opportunities;
- tenant isolation, access control, billing, support, backup и data portability
  спроектированы и проверены;
- support capacity выдерживает минимум двух клиентов без нарушения SLA действующих
  каталогов;
- unit-cost model рассчитана, даже если revenue forecast не публикуется.

---

## 7. Financial Assumptions

### 7.1 Правила финансового планирования

**Scope: Reusable for Directory Platform.**

- Планирование начинается с cost drivers, а не с желаемой выручки.
- Время владельца, редактора и поддержки учитывается как реальная стоимость.
- Бесплатные official sources не считаются бесплатными в обработке и контроле.
- Каждый источник дохода несёт compliance, support и trust costs.
- Расширение допускается только при наличии ресурса поддерживать уже опубликованные
  данные.
- Для решений используются conservative, base и stress cost scenarios без обещания
  конкретного дохода.

### 7.2 Постоянные расходы

**Scope: PflegeIndex only initially; categories are reusable.**

Расходы, мало зависящие от количества карточек:

- domain, DNS, SSL и базовый hosting;
- repository, backup storage и monitoring tools;
- бухгалтерия, юридические и privacy-консультации;
- минимальная техническая поддержка и security maintenance;
- брендовые assets и обязательная документация;
- базовая редакционная/операционная доступность.

### 7.3 Переменные расходы

**Scope: Reusable for Directory Platform.**

Расходы, растущие с объёмом операций:

- ручная проверка контактов, descriptions и suggestions;
- поиск и подтверждение источников;
- media licensing, storage и processing;
- transactional e-mail и support requests;
- API traffic и rate-limit infrastructure;
- payment processing, refunds и invoicing;
- moderation Premium Profiles и advertising creatives;
- incident response после data/import changes.

### 7.4 Масштабируемые ступенчатые расходы

**Scope: Reusable for Directory Platform.**

Расходы, возникающие при переходе порога:

- upgrade shared hosting или переход на управляемую инфраструктуру;
- migration с SQLite при доказанных concurrency/capacity limits;
- отдельные staging, monitoring и search services;
- найм дополнительного редактора, support или sales capacity;
- tenant isolation, billing и SLA tooling для SaaS;
- внешняя security assessment и penetration testing;
- legal/licensing work для новых земель, стран или verticals.

### 7.5 Стоимость расширения

**Scope: Reusable for Directory Platform.**

Для каждого нового региона или каталога отдельно оцениваются:

- source acquisition и licence review;
- parser/import development и validation;
- Geo/taxonomy mapping и manual review;
- initial contact/editorial enrichment;
- SEO/content preparation;
- deployment, backup и monitoring;
- ежемесячный refresh и correction workload;
- support и compliance overhead.

Количество записей само по себе не определяет стоимость. Главные drivers — качество
источника, доля manual review, частота изменений и требуемый verification level.

---

## 8. Success Metrics

### 8.1 User and discovery metrics

**Scope: PflegeIndex only; metric definitions are reusable.**

| Metric | Что показывает | Правило успеха |
|---|---|---|
| Organic non-brand clicks | Находит ли новая аудитория продукт | Положительный квартальный trend после 90-дневного baseline без роста thin pages |
| Eligible index coverage | Соответствуют ли индексируемые URL sitemap intent | 90–110%; устойчивые отклонения расследуются |
| Search CTR | Соответствуют ли snippets intent | Улучшение приоритетных query/page clusters без clickbait |
| Returning-user share | Возвращаются ли пользователи за обновлённой информацией | Измерять только privacy-compatible способом; сначала установить baseline |
| Direct contact action rate | Доходит ли пользователь до телефона, website или e-mail | Стабильный или растущий trend при сохранении neutral UX |
| Core Web Vitals | Не ухудшает ли рост производительность | ≥75% Good URL в 2026, движение к ≥90% |

### 8.2 Data and editorial metrics

**Scope: Reusable for Directory Platform; targets are PflegeIndex-specific.**

| Metric | Baseline PflegeIndex | Цель/правило |
|---|---:|---|
| Verified Useful Coverage | Phone или website precursor: 53,6% | ≥65% в 2026; ≥80% в 2027; ≥90% в 2028 после formal calculation |
| Fresh within 12 months | 75,6% | ≥85% в 2026; ≥90% в 2027; ≥95% в 2028 |
| Source-backed descriptions | 65,5% | ≥75% в 2026; ≥85% в 2027; ≥90% в 2028 |
| Geo coverage | 99,68% | Поддерживать ≥99,5%; не форсировать 100% без evidence |
| Pending suggestions | 23 | Median review ≤7 дней; p90 ≤30 дней |
| Verified updates per editor hour | Требует baseline | Улучшать без снижения rejection/reversal quality |
| Reversal/error rate | Требует audit trail | Снижающийся trend; критические ошибки разбираются отдельно |
| Average verification age | Требует formal metric | Снижение в priority cohorts и отсутствие скрытого исключения старых записей |

### 8.3 Operational metrics

**Scope: Reusable for Directory Platform.**

- availability критических маршрутов;
- количество и длительность P0/P1 incidents;
- доля успешных planned backups и restore tests;
- deployment success/rollback rate;
- время от approved change до production verification;
- log/disk capacity и число повторяющихся errors;
- просроченные handbook actions;
- доля releases с подтверждённым exact commit.

### 8.4 Business validation metrics

**Scope: Reusable for Directory Platform.**

- число qualified partner/institution interviews, а не просто входящих писем;
- число pilot intents и доля pilot-to-paid conversion после появления предложения;
- editorial/support hours на одного активного premium customer;
- cost per verified useful record и cost per maintained region;
- доля коммерческих обращений, требующих запрещённого pay-to-rank;
- complaints на 1 000 commercial impressions;
- API active consumers, error rate и support hours на consumer;
- время standard onboarding нового каталога;
- доля reusable requirements против custom core changes;
- retention/churn только после появления повторяющейся платной модели.

Traffic, revenue и число клиентов не считаются успехом, если одновременно ухудшаются
data quality, trust, accessibility или operations.

---

## 9. Exit Criteria: признаки зрелого продукта

### 9.1 PflegeIndex maturity

**Scope: PflegeIndex only.**

PflegeIndex переходит из стадии validation в зрелую эксплуатацию, когда одновременно:

- production стабилен не менее 12 месяцев и нет повторяющегося класса P0 incidents;
- releases, backups, restores и rollback выполняются разными ответственными по
  документации, а не только автором системы;
- Verified Useful Coverage ≥90%, freshness within 12 months ≥95%;
- data quality KPI воспроизводимы и не зависят от ручного пересчёта;
- editorial backlog удерживается в SLA минимум шесть месяцев;
- organic discovery имеет устойчивый годовой trend и не зависит от одной группы URL;
- минимум один существенный algorithm update пережит без потери продуктовой полезности;
- расходы на поддержание региона измеримы и предсказуемы;
- legal, privacy, licensing и operator data актуальны;
- коммерческие функции, если есть, не ухудшают trust и organic neutrality.

### 9.2 Directory Platform maturity

**Scope: Reusable for Directory Platform.**

Directory Platform считается зрелой, когда:

- минимум два самостоятельных каталога работают в production не менее шести месяцев;
- новый каталог запускается повторяемо без fork общего ядра;
- Platform contracts versioned и имеют consumer/dependency tests;
- project-specific taxonomy, source, SEO, legal и quality policy остаются отделены;
- стандартный launch после готовности данных занимает не более 10 рабочих дней;
- operations, quality и incident reporting сравнимы между каталогами;
- common change может быть deployed и rolled back контролируемо для каждого consumer;
- support workload и infrastructure cost прогнозируются по измеримым drivers;
- существует решение: оставаться managed platform, переходить к SaaS или отказаться
  от SaaS на основании реального спроса, а не архитектурной привлекательности.

### 9.3 Business maturity

**Scope: Reusable for Directory Platform.**

Бизнес-модель считается подтверждённой, когда:

- существует минимум один повторяемый источник дохода, не нарушающий trust rules;
- customer value и delivery cost измерены на реальных cohorts;
- коммерческая деятельность не финансируется скрытым ухудшением бесплатного каталога;
- договоры, invoicing, tax, privacy и support responsibilities оформлены;
- прекращение одного партнёрства не ставит под угрозу работу каталога;
- продукт способен отказаться от дохода, требующего pay-to-rank, misleading claims
  или неправомерного использования данных.

Зрелость не означает обязательную продажу бизнеса. Здесь exit criteria — условия
выхода из экспериментальной стадии в предсказуемую эксплуатацию.

---

## 10. Platform Reuse Matrix

| Стратегическая область | PflegeIndex only | Reusable for Directory Platform |
|---|---|---|
| Миссия | Помощь в поиске Pflegeeinrichtungen, Brandenburg/Germany | Достоверный отраслевой каталог с прозрачным качеством данных |
| Stakeholders | Учреждения ухода, родственники, Pflege data sources | Пользователи, listed entities, editors, partners, sources, operators |
| Organic inclusion policy | Конкретные PflegeIndex eligibility rules | Бесплатная фактическая корректировка и no pay-to-rank |
| Quality targets | Baseline и проценты PflegeIndex | Dimensions, metric contracts, anti-gaming rules |
| Regional growth | Федеральные земли Германии | Cohort-based source/licence/quality expansion gate |
| Editorial operations | Pflege terminology и source rules | Provenance, review, moderation, history и SLA principles |
| Advertising pilot | Немецкая маркировка и конкретные placements PflegeIndex | Separation, labelling, privacy и performance gates |
| Premium profile | Поля и claims учреждений ухода | Owner verification, paid/free boundary, audit trail |
| API fields | Только разрешённые PflegeIndex data | Versioning, rights registry, rate limits, SLA |
| Platform services | Не применимо к одному каталогу | Managed launch, SaaS и white-label readiness gates |
| Financial baseline | Hosting и editorial workload PflegeIndex | Cost-driver taxonomy и unit-cost method |
| Success targets | Конкретные 2026–2028 значения PflegeIndex | Metric definitions и maturity model |
| Legal/privacy text | Operator, service и jurisdiction specifics | Review gates и data-minimization principles |

Повторное использование означает общий контракт или процесс, а не копирование
PflegeIndex wording, taxonomy, routes, quality weights или commercial offer.

---

## 11. Recommended Business Sequence

**Scope: PflegeIndex first, then reusable for Directory Platform.**

1. **Stabilize:** подтвердить production, operations и 90-дневные baselines.
2. **Improve:** повысить Verified Useful Coverage, freshness и editorial throughput.
3. **Validate demand:** провести interviews с учреждениями, партнёрами и возможными
   API consumers без обещания ещё не созданного продукта.
4. **Pilot low-risk monetization:** при выполненном gate проверить один direct
   sponsor или narrowly defined partner offer без tracking и pay-to-rank.
5. **Build editorial value:** реализовывать Premium Profile только после verification,
   moderation и history capabilities.
6. **Expand geography:** добавлять земли последовательно при сохранении quality SLA.
7. **Prove second catalog:** запустить реальный vertical и измерить reusable boundary.
8. **Pilot API:** открыть минимальный лицензируемый read-only scope двум consumers.
9. **Choose operating model:** на данных решить между managed launches, SaaS,
   white-label или сохранением portfolio собственных каталогов.
10. **Scale only proven loops:** расширять только те каналы, где user value, quality,
    privacy, operations и unit cost подтверждены одновременно.

---

## 12. Governance and review cadence

**Scope: Reusable for Directory Platform.**

Ежемесячно:

- quality, freshness, editorial backlog и production health;
- actual operating hours и основные cost drivers;
- complaints, correction reversals и commercial trust signals.

Ежеквартально:

- organic/search baseline и growth cohorts;
- partner/customer discovery evidence;
- progress по Decision Gates;
- продолжить, изменить или остановить pilots;
- capacity для региона, каталога или коммерческого продукта.

Ежегодно:

- актуальность business thesis и stakeholder risks;
- выбор между geographic growth и data-quality investment;
- пригодность Directory Platform для следующего consumer;
- legal, privacy, licensing, tax и hosting review;
- подтверждение или пересмотр целей `PRODUCT_ROADMAP.md`.

Каждое решение `go`, `hold` или `stop` должно ссылаться на snapshot метрик, владельца,
дату и невыполненные риски. Отсутствие данных означает `hold`, а не автоматическое
разрешение запуска.

---

## 13. Итоговая стратегия

PflegeIndex должен сначала доказать, что способен поддерживать полезный и прозрачный
каталог Brandenburg в production. Затем он может последовательно расширяться по
Германии, одновременно превращая editorial и data-quality процессы в повторяемую
операционную систему.

Наиболее безопасная последовательность доходов:

`direct low-tracking partnership → verified premium workflow → controlled API →
managed catalog launches → SaaS/white-label only after proven repetition`.

Directory Platform становится бизнес-активом не тогда, когда в ней много общих
модулей, а когда она сокращает время, стоимость и риск запуска второго и последующих
качественных каталогов без потери самостоятельности проектов.

Главное ограничение стратегии: ни рост, ни доход не оправдывают ухудшение точности,
privacy, accessibility, editorial independence или доверия пользователя.

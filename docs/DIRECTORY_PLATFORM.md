# Directory Platform: Reuse Boundary and Second-Catalog Plan

## 1. Назначение документа

Этот документ фиксирует фактическое состояние Directory Platform после аудита PflegeIndex и определяет безопасный путь к запуску второго каталога за 1–2 недели.

Аудит не меняет поведение PflegeIndex. Публичные URL, SEO, Blade-разметка, база данных, GeoCore, Trust Layer и Content Layer остаются без изменений.

Важно различать:

- **реализованную платформу** — код, который уже находится в `app/Platform` и защищён архитектурными тестами;
- **проектные адаптеры** — код в `app/Projects`, использующий платформенные контракты;
- **кандидатов на переиспользование** — сходные механизмы, которые пока связаны с моделями, маршрутами, текстами или источниками PflegeIndex;
- **целевую архитектуру** — направление следующего этапа, а не описание уже существующего кода.

## 2. Фактическая структура сегодня

```text
app/Platform/DirectoryCore
├── Application/ListEntries
├── Contracts/EntryRepository
├── Domain
│   ├── EntryIdentifier
│   ├── EntrySort
│   ├── LocationScope
│   ├── LocationScopeType
│   └── PaginationOptions
└── ReadModel
    ├── EntrySummary
    ├── ListingCriteria
    └── ListingResult

app/Projects/PflegeIndex
├── Directory/PflegeEntryRepository
├── Directory/Presentation/*
└── Trust/FacilityDataQuality

app/Projects/FuneralIndex
└── Directory/FuneralEntryRepository
```

Поток списка сейчас выглядит так:

```text
Laravel controller
  → DirectoryCore ListingCriteria
  → DirectoryCore ListEntries
  → project EntryRepository
  → DirectoryCore ListingResult<EntrySummary>
  → project presenter
  → existing Blade view
```

`DirectoryCore` не зависит от Laravel, Eloquent, моделей приложения или проектных namespace. Это проверяет `PlatformDependencyTest`. `SecondProjectAdapterTest` подтверждает, что PflegeIndex и текущий пустой FuneralIndex adapter реализуют один контракт и что в Platform отсутствуют условия по имени проекта.

Текущий `FuneralEntryRepository` является только proof-of-boundary: он возвращает пустой результат и не имеет persistence, routes, controllers, presentation, SEO или публичных страниц. Он доказывает направление зависимостей, но ещё не доказывает готовность полноценного второго продукта.

## 3. Что уже является универсальным

| Компонент | Текущий статус | Почему универсален | Ограничение |
| --- | --- | --- | --- |
| `EntryRepository` | Реализован в Platform | Не знает модель, таблицу или отрасль | Покрывает только listing use case |
| `ListEntries` | Реализован в Platform | Работает с любым adapter через контракт | Нет detail/related use case |
| `ListingCriteria` | Реализован в Platform | Универсальные search, category, location, sort, pagination | Один category filter; нет произвольных project filters |
| `ListingResult` | Реализован в Platform | Общая пагинационная read model | Не является Laravel paginator |
| `EntrySummary` | Реализован в Platform | Общий минимум карточки результата | Нет website, e-mail, description, sources или trust fields |
| `EntryIdentifier` | Реализован в Platform | Типизированный идентификатор без Eloquent | Не определяет route key |
| `EntrySort` | Реализован в Platform | Небольшой общий набор сортировок | Project default реализуется adapter-ом |
| `LocationScope` | Реализован в Platform | Представляет State/District/City без Geo-моделей | Идентификатор и его семантику выбирает adapter |
| `PaginationOptions` | Реализован в Platform | Framework-independent validation | HTTP 404 policy остаётся в Laravel concern |
| Dependency direction | Реализована и тестируется | Project может зависеть от Platform, обратная зависимость запрещена | Composition root пока не выбирает проект динамически |

Дополнительно `App\Support\HttpUrl` написан на чистом PHP и по смыслу пригоден для нескольких каталогов. Сейчас он не является частью Platform API и используется также обычным приложением. Перемещать его только ради структуры не требуется; сначала нужен второй реальный consumer.

## 4. Что пока зависит от PflegeIndex

### 4.1 Persistence и repository adapter

`PflegeEntryRepository` зависит от:

- Eloquent-модели `Facility`;
- таблиц `facilities` и `cities`;
- конкретных колонок `type`, `postal_code`, `phone`;
- текущей GeoCore-схемы и legacy fallback Brandenburg;
- правил сортировки PflegeIndex.

Это правильное место для проектной зависимости. Универсальным является контракт, а не SQL/Eloquent-запрос.

### 4.2 Presentation

`PflegeEntryPresenter` и его view models зависят от route `facilities.show`, терминов facility/city, форматирования немецкого телефона и полей карточки PflegeIndex. Они должны остаться в `Projects/PflegeIndex`, пока второй каталог не покажет реально одинаковый presentation contract.

### 4.3 Controllers

Directory, Region, District и City controllers используют DirectoryCore для listing, но дополнительно напрямую обращаются к `City`, `Facility`, `GeoDistrict`, Pflege presenter и route-specific данным. Внедряются конкретные `PflegeEntryRepository` и `PflegeEntryPresenter`; глобального неоднозначного binding `EntryRepository` нет.

Это означает, что listing kernel уже разделён, но HTTP orchestration пока проектное. Для второго каталога безопаснее создать собственные controllers/adapters, а не добавлять ветвления `if project` в существующие controllers.

### 4.4 GeoCore

Geo-модели, migrations, импорт и public geographic pages фактически находятся в основном приложении, а не в `app/Platform/GeoCore`. Географическая иерархия концептуально переиспользуема, но её техническая extraction не завершена.

Второй каталог может использовать существующие Geo-модели через свой adapter, если его география совпадает. Переносить модели и таблицы перед запуском второго каталога не требуется и рискованно для PflegeIndex.

### 4.5 SEO

SEO сейчас формируется в Blade и controllers:

- project-specific title и description;
- route-based canonical;
- Open Graph;
- JSON-LD для `LocalBusiness`, `CollectionPage`, `WebSite` и `Organization`;
- sitemap с Facility, Brandenburg и Pflegelexikon;
- pagination suffix и indexing rules.

Общий механизм метаданных потенциально переиспользуем, но тексты, route names, canonical host, типы Schema.org и sitemap sources принадлежат проекту.

### 4.6 PflegeIndex-only domain

Только в PflegeIndex должны оставаться:

- Facility/City terminology и Pflege-категории;
- LASV Brandenburg и DL-DE Zero wording;
- `Amtliche Grunddaten` policy и дата источника;
- Pflegelexikon и ссылки на Pflegegrad/Kurzzeitpflege;
- Pflege-specific FAQ и guidance;
- contact verification workflow и `info@pflegeindex.com`;
- Fährmann editorial exception и другие индивидуальные редакционные материалы;
- Facility import/parser, protected contacts и description drafts;
- Brandenburg URL, state rules, district composition и legacy mapping;
- PflegeIndex brand, legal pages, social image and canonical domain;
- `LocalBusiness`/care offer Schema.org policy;
- веса и значения текущего Facility Data Quality Score.

## 5. Аудит shared-component candidates

### 5.1 Trust Layer

**Потенциально универсально:** нормализация weighted criteria в процент, список выполненных критериев, progress representation и правило «это качество информации, а не рейтинг организации».

**Сейчас проектно:** `FacilityDataQuality` принимает `Facility` и `City`, знает `source_id`, contact/description review fields, canonical slugs, пятизначный PLZ, немецкие подписи и `Amtliche Grunddaten`.

**Безопасная граница:** в Platform со временем может появиться чистая структура `QualityCriterion`/`QualityResult` и calculator, принимающий готовые boolean criteria. Состав критериев, веса, labels, badges и mapping из модели должен оставаться в проектной policy.

**Решение сейчас:** не переносить текущий класс целиком. Сначала реализовать quality policy второго каталога и извлечь только доказанно одинаковую математику.

### 5.2 Content Layer

**Потенциально универсально:** порядок секций guidance → FAQ → internal resources → contact prompt и безопасное правило «не создавать ссылку без существующего route target».

**Сейчас проектно:** немецкие Pflege-тексты, Pflegeversicherung, Pflegelexikon config, Facility contact fields и формулировки о Pflegeplatz.

**Безопасная граница:** общий presentation DTO может описывать `GuidanceItem`, `FaqItem`, `ResourceLink` и `ContactAction`. Каждый проект обязан предоставить собственный content provider. Пустые секции не должны рендериться.

**Решение сейчас:** partial остаётся PflegeIndex-only. Не переносить отраслевой текст в Platform.

### 5.3 Related Facilities

**Потенциально универсально:** запрос ограниченного числа других entries, исключение текущего entry, отсутствие N+1 и отображение после detail content.

**Сейчас проектно:** Eloquent `Facility`, city relation, limit 3, сортировка по Pflege type/name и Facility card partial.

**Безопасная граница:** отдельный будущий `RelatedEntryProvider` может принимать entry identifier, optional location/category context и limit и возвращать `EntrySummary`. Relevance policy и SQL остаются в project adapter.

**Решение сейчас:** не расширять `EntryRepository::list()` несвязанной ответственностью. Новый контракт вводить только вместе с реальным вторым implementation.

### 5.4 Contact Card

**Потенциально универсально:** отображение доступных phone/e-mail/website actions, verified status и secondary actions.

**Сейчас проектно:** LASV fallback wording, Google Maps URL, correction mailto, provider-source label, contact verification fields и немецкие labels.

**Безопасная граница:** общий `ContactViewModel`/`ContactAction` может содержать уже подготовленные label, href, external flag и verification note. Platform не должен строить `mailto`, Google URL или утверждать verification semantics.

**Решение сейчас:** оставить Contact Card в PflegeIndex до появления второй карточки с теми же interaction rules.

### 5.5 Quick Actions

**Потенциально универсально:** небольшой доступный список доступных contact actions без ложных кнопок.

**Сейчас проектно:** берёт поля `Facility`, использует Pflege labels и специальный `$displayWebsite`.

**Безопасная граница:** это самый простой кандидат на общий Blade primitive, если он будет принимать только список готовых `ContactAction`. Нельзя передавать в общий partial Eloquent model.

**Решение сейчас:** extraction допустима первой среди UI-компонентов, но только после characterization tests, сохраняющих текущий HTML.

### 5.6 Breadcrumb

**Потенциально универсально:** ordered breadcrumb items, accessible HTML, `aria-current` и построение `BreadcrumbList` JSON-LD из того же набора элементов.

**Сейчас проектно:** route names, Brandenburg hierarchy, conditional district и Facility/City labels заданы прямо в views.

**Безопасная граница:** Platform presentation object `BreadcrumbTrail` с готовыми `label`/`url`; проект формирует элементы, общий renderer создаёт HTML и JSON-LD.

**Решение сейчас:** сильный кандидат на extraction, потому что устраняет расхождение между видимыми breadcrumbs и schema. В первом этапе нельзя менять итоговый HTML/JSON.

### 5.7 SEO helpers

**Потенциально универсально:** immutable page metadata object, canonical/pagination normalization, Open Graph field rendering, BreadcrumbList и CollectionPage builders, sitemap URL record.

**Сейчас проектно:** все copy, route generation, canonical host, social image, locale, robots decisions и Schema.org entity type.

**Безопасная граница:** Platform может предоставлять структуры и deterministic builders; проект должен передавать готовые значения и выбирать schema types. SEO helper не должен самостоятельно угадывать title, URL или indexing policy.

**Решение сейчас:** начинать с data objects/builders, а не с универсального SEO engine. Не менять существующие метаданные PflegeIndex во время extraction.

## 6. Рекомендуемая целевая структура

Это целевая схема после появления второго рабочего каталога, а не обязательная структура следующего commit:

```text
app/Platform
├── DirectoryCore                 # уже существует
├── Presentation
│   ├── BreadcrumbTrail           # кандидат
│   ├── ContactAction             # кандидат
│   ├── ContentSection DTOs       # кандидат
│   └── QualityResult             # кандидат
└── Seo
    ├── PageMetadata              # кандидат
    └── Schema builders           # кандидат

app/Projects/PflegeIndex
├── Directory
├── Content
├── Trust
├── Seo
└── Presentation

app/Projects/<SecondCatalog>
├── Directory
├── Content
├── Trust
├── Seo
└── Presentation
```

Platform остаётся framework-independent там, где это возможно. Laravel route generation, Eloquent queries, Blade selection и service-provider wiring остаются на уровне приложения или проекта.

## 7. Требования ко второму каталогу

До начала реализации необходимо утвердить:

1. окончательное имя проекта (текущий proof называется `FuneralIndex`, тогда как продукт может называться BestatterIndex);
2. отдельный domain/canonical origin;
3. одну application deployment или отдельный deployment на каталог;
4. URL-структуру без влияния на существующие PflegeIndex routes;
5. entry taxonomy и обязательные поля;
6. источник данных, license, provenance и правила verification;
7. географический охват и возможность использования текущей Geo hierarchy;
8. detail Schema.org type;
9. title/description/robots/sitemap policies;
10. branding, locale, legal operator data и contact mailbox;
11. trust criteria и объяснение, почему score не является рейтингом организации;
12. project-specific guidance/FAQ и допустимые утверждения;
13. import, deduplication, update and rollback procedure;
14. минимальный набор sample data для end-to-end тестов.

Второй каталог не должен использовать таблицы `facilities` или Pflege labels только ради скорости. Он реализует `EntryRepository` через собственный persistence adapter, даже если временно использует ту же SQLite connection и Geo-модели.

## 8. Roadmap на 1–2 недели

### Дни 1–2: Product contract и skeleton

- утвердить требования из предыдущего раздела;
- выбрать project namespace и route prefix;
- создать project-specific model/repository/presenter skeleton;
- заменить пустой proof adapter реальным in-memory fixture или безопасным read-only persistence adapter;
- сохранить текущий architecture dependency test.

**Результат:** второй проект выполняет `ListEntries` и возвращает непустой `ListingResult`, не затрагивая PflegeIndex.

### Дни 3–4: Listing pages

- создать отдельные routes/controllers/views второго каталога;
- использовать существующие `ListingCriteria`, `LocationScope`, `PaginationOptions` и `ListingResult`;
- реализовать category/search/location mapping в project repository;
- добавить project presenter, URL builder и card view model;
- проверить query count и pagination bounds.

**Результат:** directory и geographic listing второго каталога работают через DirectoryCore.

### Дни 5–6: Detail page и безопасное первое переиспользование

- создать project detail read model;
- реализовать собственные Contact Card, Quick Actions и Related Entries policy;
- сравнить их с PflegeIndex;
- извлечь общий `ContactAction` и/или Quick Actions renderer только при идентичном поведении;
- не менять существующий HTML PflegeIndex без characterization test.

**Результат:** detail page второго каталога работает, а первый shared UI primitive доказан двумя consumers.

### Дни 7–8: Trust, Content и SEO

- определить project-specific trust criteria и content provider;
- при совпадении извлечь чистый weighted-quality calculator без model dependencies;
- ввести metadata/breadcrumb DTO с готовыми значениями;
- сохранить project-specific SEO copy, schema type, canonical generation и sitemap source;
- проверить отсутствие broken internal links.

**Результат:** второй каталог имеет собственные trust/content/SEO policies без Pflege terminology в Platform.

### Дни 9–10: Hardening и release preparation

- добавить architecture tests для второго project namespace;
- выполнить end-to-end Feature tests: listing, detail, canonical, robots, sitemap, JSON-LD, empty data and pagination;
- проверить отсутствие новых Platform → Project/Laravel dependencies;
- запустить полный набор PflegeIndex regression tests;
- подготовить отдельную deployment configuration и production verification checklist.

**Результат:** второй каталог готов к отдельному release candidate, а поведение PflegeIndex подтверждено без изменений.

## 9. Acceptance criteria extraction-этапа

Extraction считается успешной только если:

- `app/Platform` не импортирует Laravel, Eloquent, `App\Models` или `App\Projects`;
- Platform не содержит слов Pflege, Facility, Bestatter или project route names;
- оба проекта имеют собственные persistence и presentation adapters;
- ни один shared component не принимает Eloquent model;
- project copy, route names, domain, schema type и data-source wording задаются проектом;
- PflegeIndex URL, HTML, SEO metadata, query count и Feature tests не изменились;
- второй каталог имеет хотя бы один end-to-end public listing и detail test;
- shared component имеет минимум два реальных consumers, а не один production consumer и один пустой stub;
- database extraction не является предварительным условием запуска второго каталога.

## 10. Основные риски

| Риск | Последствие | Защита |
| --- | --- | --- |
| Преждевременная универсализация | Platform заполняется Pflege-specific optional fields | Извлекать только после второго consumer |
| Универсальный controller с project conditions | Сложные ветвления и SEO-регрессии | Отдельные project controllers/adapters |
| Передача Eloquent models в Platform | Обратная зависимость от persistence | Только immutable DTO/read models |
| Общий SEO helper генерирует copy | Неверные title/schema для второго каталога | Проект передаёт готовые metadata values |
| Trust weights становятся «универсальной истиной» | Ложная оценка качества другого каталога | Общая только математика, policy проектная |
| Общий Content Layer содержит Pflege advice | Нерелевантный или юридически рискованный текст | Content provider на уровне проекта |
| Миграция Geo/DB одновременно с запуском | Высокий риск для production PflegeIndex | Использовать adapters; перенос отложить |
| Один глобальный `EntryRepository` binding | Неоднозначный repository в multi-project app | Явная project wiring/composition root |

## 11. Итог аудита

PflegeIndex уже имеет корректное минимальное универсальное ядро для listing use case. Оно достаточно, чтобы начать второй каталог без изменения существующей архитектуры и базы PflegeIndex.

Trust Layer, Content Layer, Related Facilities, Contact Card, Quick Actions, Breadcrumb и SEO имеют переиспользуемые идеи, но сегодня не являются готовыми Platform-компонентами. Их безопасная extraction должна происходить постепенно и только на основании двух реальных implementations.

Наиболее подходящий первый шаг — не перенос существующих Blade partials в `Platform`, а реализация реального второго repository/presenter поверх существующего DirectoryCore. После этого можно последовательно извлечь `ContactAction`, Breadcrumb DTO и чистую математику Quality Score, сохраняя project-specific policy в соответствующих project modules.

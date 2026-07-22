# Product Roadmap 2026–2028

## 1. Назначение документа

Этот документ определяет продуктовую стратегию PflegeIndex и Directory Platform
на период 2026–2028 годов. Он связывает текущее состояние продукта с измеримыми
результатами, критериями релизов и условиями дальнейшего расширения.

Roadmap не является обещанием выпустить функцию к фиксированной дате. Переход к
следующему этапу допускается только после выполнения соответствующих критериев
готовности. Качество данных, доверие пользователей и эксплуатационная устойчивость
имеют приоритет над количеством функций, страниц и каталогов.

Плановая дата пересмотра: не реже одного раза в квартал и после каждого крупного
релиза.

---

## 2. Current Product State

### 2.1 Краткая сводка

| Область | Текущее состояние | Сильная сторона | Следующий подтверждаемый пробел |
|---|---|---|---|
| Продукт | PflegeIndex подготовлен как первый каталог с фокусом на Brandenburg | Полный пользовательский путь от региона до карточки учреждения | Подтвердить стабильную работу точной версии release на production |
| Архитектура | Laravel 12, PHP 8.2, SQLite, Blade; списки используют DirectoryCore | Независимый `DirectoryCore`, проектный adapter и presenter | Не извлекать дополнительные abstractions до появления второго реального потребителя |
| География | Country → State → District → Municipality → City → Facility | 255 из 257 городов сопоставлены; 1 552 из 1 557 учреждений имеют район | 2 города и 5 учреждений остаются нерешёнными без достаточных оснований для принудительного mapping |
| Данные | Официальные базовые данные дополнены контактами и редакционными описаниями | Разделение amtliche Grunddaten и редакционных дополнений; источники и даты проверки | Поднять полноту контактов, свежесть и traceability без вымышленных данных |
| UX и доверие | Реализованы Trust Layer, Content Layer, быстрые контакты и сообщение об ошибке | Доступное объяснение качества именно информации, а не учреждения | Проверить реальные мобильные сценарии и регулярно измерять полезность |
| SEO | Canonical, sitemap, robots, pagination SEO, Open Graph, JSON-LD и HTTPS-модель покрыты локальными тестами | Предсказуемые индексируемые URL и единый canonical host | Подтвердить фактическую индексацию, redirects и assets после каждого deployment |
| Инфраструктура | Есть release-процесс, операции, backup и verification документация | Повторяемые checklist и incident playbooks | Продолжать подтверждать hosting, backup restore, disk/log health и deployed commit на production |
| Аналитика | GA4 и Clarity интегрированы конфигурационно и отключены при пустых ID | Нет сторонних analytics-запросов без настройки | Не включать сервисы до реализации consent layer и privacy-проверки |
| Документация | Есть архитектурные, deployment, operations, privacy, editorial и data-quality документы | Решения и ограничения прослеживаются | Поддерживать документы синхронно с фактическим поведением продукта |

### 2.2 Архитектурная позиция

PflegeIndex остаётся самостоятельным продуктом на общей платформенной основе.
`DirectoryCore` уже предоставляет независимый контракт для списков, фильтрации,
сортировки и пагинации. PflegeIndex хранит собственные модели, маршруты, немецкие
тексты, SEO-правила, импорт и представление.

Тестовый adapter второго проекта подтверждает независимость API, но не является
вторым работающим каталогом. Общие UI-, SEO-, editorial- и data-quality-компоненты
следует извлекать только после подтверждения их одинаковых требований двумя
реальными продуктами.

### 2.3 Текущая база данных и качества

Зафиксированный baseline для планирования:

- 1 557 учреждений;
- 257 городов;
- 255 сопоставленных городов;
- 1 552 учреждения с municipality/district coverage — 99,68%;
- 5 учреждений в 2 городах без подтверждённого municipality mapping;
- 834 карточки с телефоном — 53,6%;
- 834 карточки с website — 53,6%;
- 558 карточек с e-mail — 35,8%;
- 1 020 описаний с источником и датой проверки — 65,5%;
- 1 177 карточек с контактом или описанием, проверенным за последние 12 месяцев — 75,6%;
- 23 ожидающих, 87 принятых и 5 отклонённых contact suggestions.

Эти числа являются снимком текущих проектных данных, а не гарантией состояния
production. После запуска baseline должен рассчитываться из проверенного production
snapshot и датироваться в каждом отчёте.

### 2.4 Production readiness

Репозиторий имеет зрелую release-candidate основу, автоматические тесты, deployment
документацию и эксплуатационные проверки. Однако готовность репозитория нельзя
приравнивать к готовности работающего сайта. Public release считается завершённым
только после deployment точного commit, проверки backup/restore, canonical redirects,
assets, `/up`, sitemap, robots, legal pages и основных пользовательских маршрутов на
production.

---

## 3. Product Vision

### 3.1 Миссия PflegeIndex

Сделать надёжную, понятную и прозрачную информацию об учреждениях ухода легко
доступной людям, начинающим поиск в Brandenburg, а затем — в других регионах
Германии.

PflegeIndex помогает пользователю найти подходящие учреждения и напрямую связаться
с ними. Он не заменяет консультацию, не оценивает медицинское качество и не обещает
наличие свободных мест.

### 3.2 Какую проблему решает продукт

Информация об учреждениях распределена между официальными источниками, сайтами
операторов и устаревающими списками. Названия, контакты, география и источники могут
не совпадать. Для пожилого человека или родственника это превращает простой поиск
телефона, сайта или адреса в длительную проверку нескольких ресурсов.

PflegeIndex объединяет официальную основу и проверенные редакционные дополнения,
явно показывает качество и свежесть доступной информации и предоставляет короткий
путь к прямому контакту.

### 3.3 Почему пользователи будут возвращаться

- контакты и источники регулярно перепроверяются;
- изменения и сообщения об ошибках проходят понятный editorial workflow;
- страницы остаются простыми, читаемыми и удобными на мобильных устройствах;
- пользователь видит, какие данные официальные, какие дополнены редакцией и когда
  они проверялись;
- географическая навигация позволяет продолжить поиск в городе или районе;
- нейтральные объяснения помогают подготовиться к контакту с учреждением.

### 3.4 Отличие от обычного каталога

PflegeIndex конкурирует не количеством рекламных карточек, а доказуемым качеством
информации:

- official base data не смешиваются с editorial data без маркировки;
- источник, дата проверки и confidence важнее заполнения поля любой ценой;
- PflegeIndex Qualität описывает полноту карточки, но не рейтинг учреждения;
- платное размещение в будущем не должно влиять на органический порядок, официальный
  статус или quality score;
- SEO-страницы создаются для реальной пользовательской задачи, а не ради количества;
- исправления не публикуются без проверки и не перезаписывают защищённые данные.

### 3.5 Продуктовые non-goals

PflegeIndex не должен становиться:

- официальным государственным реестром;
- сервисом медицинских рекомендаций;
- системой неподтверждённых пользовательских рейтингов и ранжирования учреждений;
- источником гарантированных цен, свободных мест или результатов ухода;
- рекламным каталогом, где оплата подменяет качество данных;
- агрегатором текстов и изображений без разрешённого источника.

---

## 4. Стратегические принципы 2026–2028

1. **Brandenburg first.** Сначала стабильная эксплуатация и измеримое качество
   текущего каталога, затем расширение.
2. **Quality before coverage.** Неподтверждённое соответствие или контакт лучше
   оставить неизвестным, чем публиковать уверенную ошибку.
3. **Evidence before claims.** Источники, даты проверки и production evidence должны
   подтверждать публичные утверждения.
4. **Privacy by default.** Необязательное tracking не загружается без необходимого
   consent и актуальной privacy-документации.
5. **Accessible by design.** Контраст, keyboard flow, ясные формулировки и мобильный
   сценарий являются release criteria.
6. **Platform from proven reuse.** Общий компонент появляется после подтверждения
   двух реальных потребителей, а не ради предполагаемой универсальности.
7. **Neutral monetization.** Доход не должен менять фактический статус, organic
   ranking или оценку качества информации.
8. **Operational ownership.** Каждая новая область данных имеет владельца, SLA,
   мониторинг, backup и rollback plan.

---

## 5. Three-Year Roadmap

### 5.1 2026 — Public Launch, Brandenburg, Data Quality

#### Цели

- завершить подтверждённый public launch Brandenburg;
- создать первые реальные SEO, UX и operational baselines;
- повысить полезность текущих карточек без искусственного расширения данных;
- подготовить измеримый editorial и data-quality процесс.

#### Основные результаты

1. **Launch closure**
   - deploy только идентифицированного release commit;
   - проверить backup и восстановление до рискованных операций;
   - подтвердить canonical host, one-hop redirects, SSL, sitemap, robots, OG image,
     health endpoint и ключевые страницы на production;
   - закрыть P0 release blockers и зарегистрировать принятые ограничения.

2. **Search foundation**
   - подтвердить сайт в Google Search Console и Bing Webmaster Tools;
   - отправить sitemap и отслеживать coverage, crawl, canonical и 404;
   - не включать GA4 или Clarity без consent layer и privacy approval;
   - получить минимум 90 дней поискового baseline перед прогнозами роста.

3. **Data quality operating cycle**
   - утвердить versioned QualityPolicy и baseline универсальных метрик;
   - повысить долю карточек с подтверждёнными прямыми контактами;
   - приоритизировать карточки с высоким спросом и низкой полнотой;
   - сохранить 2 unresolved cities до появления надёжного официального evidence;
   - измерять backlog и время обработки contact suggestions.

4. **Editorial pilot**
   - формализовать источник → проверку → редактуру → публикацию → повторную проверку;
   - не публиковать автоматически непроверенные предложения;
   - определить владельца и SLA для повторной проверки;
   - проверить, какие части `EDITORIAL_MODEL.md` действительно нужны редактору.

5. **Operational stabilization**
   - выполнить минимум один подтверждённый restore test;
   - вести ежедневные, еженедельные и ежемесячные проверки из handbook;
   - измерять disk, logs, errors, uptime и Core Web Vitals;
   - устранить расхождения между deployed state и repository state.

#### Exit criteria 2026

- точный release commit подтверждён на production;
- нет открытых P0 по безопасности, legal pages, backup или canonical redirects;
- Search Console и Bing видят актуальный sitemap;
- установлен минимум 90-дневный KPI baseline;
- не менее 65% карточек имеют подтверждённый телефон или website;
- не менее 85% карточек имеют проверку контакта или описания не старше 12 месяцев;
- contact suggestion backlog имеет владельца и измеряемый SLA;
- три последовательных месяца проходят operational review без необработанного
  критического инцидента.

### 5.2 2027 — Regional Expansion, Editorial Platform, Feedback, Growth

#### Цели

- превратить ручной editorial pilot в управляемый процесс;
- расширять PflegeIndex по федеральным землям только при подтверждённых источниках;
- использовать обратную связь для повышения точности данных;
- проверить платформенную повторяемость на реальном втором продукте.

#### Основные результаты

1. **Editorial Platform v1**
   - field-level provenance и verification status;
   - история изменений, protected fields и review queue;
   - Data Quality Dashboard с приоритетными задачами;
   - retention, роли и audit trail, соответствующие privacy review.

2. **User Feedback workflow**
   - безопасная обработка correction reports и owner claims только после отдельного
     product/privacy design;
   - подтверждение изменений по первичным источникам;
   - измеримый response и publication SLA;
   - отсутствие публичных рейтингов учреждений.

3. **Новые федеральные земли**
   - запускать по одной земле после source/licence/coverage assessment;
   - использовать отдельный import dry run, duplicate review и GeoCore validation;
   - не создавать thin pages без достаточных данных;
   - сохранять региональные особенности в PflegeIndex, а не в DirectoryCore.

4. **Sustainable growth**
   - развивать landing pages и внутренние ссылки на основе реальных запросов;
   - улучшать snippets по CTR и intent, не создавая дубли;
   - поддерживать Core Web Vitals и accessibility при росте контента;
   - диверсифицировать discovery через Bing, direct и referrals.

5. **Второй каталог — pilot**
   - выбрать одну конкретную пользовательскую проблему и доступный надёжный источник;
   - создать реальный project adapter без ветвлений PflegeIndex в Platform;
   - извлечь только компоненты, требования которых подтверждены обоими каталогами;
   - не считать тестовый пустой adapter готовым вторым продуктом.

#### Exit criteria 2027

- editorial lifecycle используется в ежедневной работе и имеет audit trail;
- не менее 80% активных карточек имеют подтверждённый прямой контакт;
- не менее 90% карточек имеют актуальную проверку не старше 12 месяцев;
- каждая новая земля проходит source, legal, import, SEO и operations gates;
- органический рост не сопровождается ухудшением index coverage или Core Web Vitals;
- второй каталог имеет собственные данные, routes, UI, SEO и production verification;
- общие abstractions имеют два реальных потребителя и dependency tests.

### 5.3 2028 — Germany Coverage, API, Premium Services, Platform Portfolio

#### Цели

- приблизиться к национальному покрытию без снижения качества;
- оформить Directory Platform как стабильную основу нескольких каталогов;
- открыть контролируемый API и коммерческие услуги только при доказанной ценности;
- сохранить нейтральность органического каталога.

#### Основные результаты

1. **Germany coverage**
   - расширять покрытие до Германии только для земель, прошедших quality gates;
   - публиковать coverage dashboard по региону и свежести;
   - иметь регулярный update process для каждого официального источника;
   - архивировать или маркировать записи, которые больше нельзя подтвердить.

2. **Directory Platform v3**
   - versioned contracts для подтверждённых shared capabilities;
   - минимум два production-каталога без project-specific зависимостей в Platform;
   - общий operations и data-quality standard при раздельных taxonomy, brand, legal,
     import и SEO policy;
   - documented upgrade и rollback path для каждого каталога.

3. **API**
   - начать с ограниченного read-only API для подтверждённых use cases;
   - определить authentication, rate limits, licence, freshness и versioning;
   - не раскрывать персональные или редакционные данные без правового основания;
   - измерять использование и стоимость до расширения API.

4. **Premium services**
   - рассмотреть verified operator workspace, расширенное управление подтверждённым
     профилем, отчёты по качеству данных или договорный API;
   - чётко маркировать платные возможности;
   - не продавать официальный статус, quality score или organic ranking;
   - проводить legal, privacy и user-trust review до запуска каждой услуги.

#### Exit criteria 2028

- национальное покрытие заявляется только при опубликованном и проверенном coverage;
- минимум 90% активных карточек имеют подтверждённый прямой контакт;
- минимум 95% карточек имеют актуальную проверку не старше 12 месяцев;
- минимум два каталога стабильно работают на Directory Platform;
- API имеет владельца, SLA, versioning, rate limits и документацию;
- коммерческие функции отделены от органического ранжирования и trust indicators;
- operations capacity и выручка, если она появляется, покрывают поддержку качества.

---

## 6. Release Roadmap

| Version | Ориентир | Цель | Ключевые изменения | Критерии завершения |
|---|---|---|---|---|
| v1.0 | 2026 | Публичный запуск Brandenburg | Текущий каталог, GeoCore coverage, DirectoryCore lists, Trust/Content Layer, SEO и operations baseline | Точный commit deployed; backup/restore проверен; ключевые URL, redirects, assets и legal pages подтверждены; нет P0; Search Console/Bing готовы |
| v1.1 | 2026 | Наблюдаемость и quality baseline | Search monitoring, KPI baseline, versioned QualityPolicy, operational reports; consent layer только если analytics действительно нужны | 90 дней baseline; analytics отсутствует без consent; quality metrics воспроизводимы; operational review выполняется регулярно |
| v1.2 | 2027 | Editorial Operations | Provenance, review lifecycle, history, dashboard, correction workflow и freshness queue | Protected data сохранены; audit trail работает; retention и privacy подтверждены; SLA измеряется; auto-publishing непроверенных данных отсутствует |
| v2.0 | 2027 | Multi-region PflegeIndex | Последовательное добавление федеральных земель, repeatable import/Geo/SEO/operations process | Не менее двух земель прошли все gates; quality thresholds соблюдены; нет thin/duplicate pages; rollback проверен |
| v3.0 | 2028 | Directory Platform portfolio | Germany coverage по подтверждённым регионам, второй production-каталог, versioned Platform, controlled API и допустимые premium services | Два production consumers; стабильные contracts; API governance; общая эксплуатация; коммерция не влияет на органический trust |

Версия считается завершённой по критериям, а не по наличию commit или объявленной
дате. Scope следующей версии не начинается при открытом P0 предыдущей.

---

## 7. Product KPIs

### 7.1 North Star Metric

**Verified Useful Coverage** — доля публичных карточек, где пользователь получает
минимум один действующий прямой контакт, подтверждённый источником и проверенный в
установленный период.

Эта метрика лучше числа страниц или посещений: она одновременно отражает coverage,
полезность, traceability и freshness.

### 7.2 KPI framework

| KPI | Определение | Baseline | Предлагаемая цель | Периодичность |
|---|---|---:|---:|---|
| Public facilities | Активные индексируемые учреждения | 1 557 | Не задавать рост без нового официального coverage | После импорта |
| Geo coverage | Учреждения с подтверждённой municipality/district hierarchy | 99,68% | Поддерживать ≥99,5%; 100% только при evidence | После импорта |
| Verified direct contact | Карточки с подтверждённым phone или website | 53,6% | ≥65% в 2026; ≥80% в 2027; ≥90% в 2028 | Ежемесячно |
| E-mail completeness | Карточки с подтверждённым e-mail | 35,8% | Baseline +10 п.п. без guessed addresses | Ежеквартально |
| Source-backed description | Описания с источником и датой проверки | 65,5% | ≥75% в 2026; ≥85% в 2027; ≥90% в 2028 | Ежемесячно |
| Fresh within 12 months | Карточки с недавней проверкой контакта или описания | 75,6% | ≥85% в 2026; ≥90% в 2027; ≥95% в 2028 | Ежемесячно |
| Data Quality Index | Versioned aggregate completeness, accuracy, freshness, consistency, traceability и confidence | Требует baseline | Установить в v1.1; улучшать без смены policy в периоде | Ежемесячно |
| Suggestion backlog | Ожидающие contact suggestions | 23 | Median review ≤7 дней; p90 ≤30 дней после запуска workflow | Еженедельно |
| Suggestion acceptance quality | Доля принятых suggestions, не отменённых последующей проверкой | Требует baseline | Установить после audit trail | Ежеквартально |
| Eligible index coverage | Индексируемые canonical URL в поиске / eligible sitemap URL | Unknown до production GSC | 90–110%; расхождения расследуются | Еженедельно |
| Organic non-brand clicks | Клики из поиска без branded queries | Unknown | Baseline 90 дней, затем положительный квартальный trend | Ежемесячно |
| Search CTR | CTR по query/page/device clusters | Unknown | +10% относительно baseline в приоритетных clusters без clickbait | Ежемесячно |
| Core Web Vitals | Доля URL с статусом Good | Unknown production | ≥75% в 2026; ≥90% к 2028 | Ежемесячно |
| Critical-route availability | Успешные проверки home, directory, sitemap, robots и `/up` | Требует monitoring | ≥99,5% без плановых окон | Еженедельно/автоматически |
| Restore success | Успешные контролируемые restore tests | Не считать без evidence | 100% запланированных тестов | Ежемесячно |
| Second-catalog reuse | Общие production components с двумя реальными consumers | 0 подтверждённых UI consumers | Извлекать только после второго use case | На release gate |

### 7.3 Правила измерения

- фиксировать timestamp, среду и denominator каждого отчёта;
- не сравнивать KPI после изменения определения или QualityPolicy без пересчёта;
- отделять missing от invalid и unknown от not applicable;
- не считать тестовый adapter или staging traffic production usage;
- не оптимизировать количество индексируемых страниц отдельно от качества;
- не включать tracking только ради получения метрики: privacy gate обязателен;
- цели после первых 90 дней production baseline могут быть скорректированы с
  документированным обоснованием.

---

## 8. Основные риски

| Риск | Вероятность | Влияние | Ранний сигнал | Стратегия снижения |
|---|---|---|---|---|
| Изменение формата или доступности официального источника | Высокая | Высокое | Import validation или counts резко меняются | Версионировать parser, хранить source evidence, dry run и backup до применения, иметь ручной fallback |
| Снижение свежести данных | Высокая | Высокое | Растёт возраст verification и bounce на contact actions | Freshness queue, SLA, приоритет популярных/неполных карточек, регулярный recheck |
| Ошибочное автоматическое сопоставление географии | Средняя | Высокое | Неожиданные district counts или ambiguous candidates | Confidence threshold, manual review, hierarchy audit; unknown предпочтительнее ошибки |
| Юридические или privacy-изменения | Средняя | Критическое | Новые сервисы, формы, claims или требования регулятора | Privacy review до запуска, data inventory, consent gate, минимизация и retention policy |
| Чрезмерная зависимость от SEO | Высокая | Высокое | Резкое падение clicks при стабильном продукте | Direct/referral channels, Bing, brand trust, полезный контент, отсутствие doorway pages |
| Thin или duplicate pages при расширении | Высокая | Высокое | Indexed pages растут быстрее полезных данных | Content/coverage gate, canonical audit, noindex пустых/неполезных страниц, staged rollout |
| Ограничения shared hosting | Средняя | Высокое | Disk/log pressure, timeouts, невозможность cache operations | Monitoring, log rotation, capacity threshold, план миграции hosting до перегрузки |
| SQLite concurrency и scale | Средняя | Среднее/высокое | Lock errors, замедление imports/admin writes | Измерять нагрузку, сериализовать writes, backup; планировать DB migration только по evidence |
| Рост editorial workload и затрат | Высокая | Высокое | Backlog и review age растут | Prioritization, dashboard, SLA, batch tooling с manual approval, бюджет на редактуру |
| Premature platform abstraction | Средняя | Высокое | Общий код содержит project flags и исключения | Rule of two real consumers, adapters, dependency tests, reversible extraction |
| Нарушение прав на тексты или изображения | Средняя | Высокое | Нет source/licence или приходит complaint | Primary sources, paraphrase, source URL, rights review, быстрый takedown process |
| Ошибка consent/analytics | Средняя | Высокое | Requests уходят до выбора или после отказа | Analytics off by default, automated network tests, accessible preferences, privacy approval |
| Конфликт monetization и доверия | Средняя | Высокое | Платные карточки выглядят официальнее или ранжируются выше | Явная маркировка, separation policy, независимые quality score и organic ordering |
| Key-person dependency | Высокая | Высокое | Операции или import невозможно повторить без одного человека | Handbook, role assignment, rehearsal, credential ownership и change logs |
| Потеря или повреждение данных | Низкая/средняя | Критическое | Integrity errors или неуспешный backup | Automated backups, off-host copies, restore tests и read-only validation до mutations |

---

## 9. Directory Platform Strategy

### 9.1 Когда создавать второй каталог

Второй каталог следует начинать не по календарю, а после выполнения всех условий:

- PflegeIndex v1.0 стабильно работает минимум 8–12 недель;
- нет открытых P0 и повторяющихся критических инцидентов;
- backup, restore, deployment и monitoring выполняются не только документально;
- editorial workload текущего каталога находится в пределах согласованного SLA;
- у второго каталога есть конкретная аудитория, проблема и владелец;
- определены законный источник, licence, update frequency и минимальное качество;
- понятны taxonomy, geography, SEO intent, privacy и operating cost;
- существует минимум два подтверждённых shared use case, а не только внешне похожий UI.

### 9.2 Что переносить в Platform

Уже универсально:

- `DirectoryCore` contracts, listing criteria/result/summary и value objects;
- направление зависимостей через project repository adapter;
- правила независимости общего ядра от Laravel/Eloquent/Blade.

Кандидаты на перенос после доказательства вторым продуктом:

- versioned quality calculation contracts, но не PflegeIndex weights и wording;
- editorial lifecycle primitives и provenance types;
- related-entry provider contract, но не конкретные relation rules;
- contact/quick-action view models, но не немецкие labels;
- breadcrumb и metadata data objects, но не project URL/SEO policy;
- operations, incident и data-quality templates.

Должно остаться проектным:

- taxonomy PflegeIndex и смысл facility types;
- routes, URLs, copy, brand и legal pages;
- import sources, licence rules и protected-field policy;
- Geo interpretation, если она специфична для каталога;
- SEO templates, structured data decisions и indexation policy;
- editorial facts, quality weights и commercial policy конкретного каталога.

### 9.3 Критерии готовности Platform

| Область | Критерий |
|---|---|
| Product | Два каталога решают реальные задачи и имеют активных пользователей |
| Architecture | Ни один Platform component не импортирует project model, route, copy или framework-specific persistence |
| API | Contracts versioned, documented и покрыты consumer tests |
| Data | Каждый проект владеет schema/source/import, общие правила подтверждены обоими |
| UX | Reuse не заставляет каталоги использовать ложные одинаковые labels или flows |
| SEO | Canonical, taxonomy и structured data остаются явной project policy |
| Operations | Deployment, backups, monitoring и incident ownership определены для каждого проекта |
| Privacy | Data inventory, retention, legal owner и consent requirements определены отдельно |
| Economics | Есть ресурс поддерживать качество обоих каталогов после запуска |

### 9.4 Реалистичный pilot за 1–2 недели

Оценка 1–2 недели относится только к реализации после завершённой discovery и
готовых данных, а не ко всему запуску:

1. **До начала:** утверждены пользовательская задача, source/licence, taxonomy,
   sample dataset, URL/SEO и legal/operations owner.
2. **Неделя 1:** project skeleton, repository adapter, list/detail flows, importer dry
   run и тесты направления зависимостей.
3. **Неделя 2:** project-specific UI/SEO, quality checks, deployment package,
   backup/rollback и production verification.
4. **После pilot:** сравнить два продукта и извлечь только подтверждённые общие части.

Если data discovery, licensing или editorial ownership не завершены, проект нельзя
оценивать как двухнедельный.

---

## 10. Decision Gates

### Gate A — Public Launch

Требуется: exact commit deployment, backup/restore evidence, no P0, production URL
checks, legal/hosting confirmation и rollback path.

### Gate B — Analytics Enablement

Требуется: доказанная продуктовая необходимость, consent layer, обновлённая privacy
page, withdrawal flow, network verification до/после consent и operator approval.
До Gate B GA4 и Clarity остаются выключенными.

### Gate C — New Federal State

Требуется: official source/licence, GeoCore mapping feasibility, ≥90% useful initial
coverage, import dry run, duplicate review, SEO demand и operational owner.

### Gate D — Editorial Platform

Требуется: подтверждённый ручной workflow, роли, retention, protected-field rules,
audit trail и измеримый backlog. Нельзя автоматизировать неопределённый процесс.

### Gate E — Second Directory

Требуется: 8–12 недель стабильного PflegeIndex, готовые данные и аудитория второго
проекта, capacity и два доказанных shared use case.

### Gate F — API or Premium Services

Требуется: реальный спрос, cost model, security/privacy/legal review, SLA, neutral
ranking policy и отдельная маркировка коммерческих возможностей.

---

## 11. Приоритеты на первые 90 дней после public launch

1. Подтвердить deployed commit, canonical redirects, assets, sitemap и health checks.
2. Выполнить и задокументировать backup restore test.
3. Подключить Google Search Console и Bing без включения analytics tracking.
4. Зафиксировать production baseline index coverage, CTR, queries и Core Web Vitals.
5. Обрабатывать contact suggestions по согласованному SLA.
6. Поднять Verified Useful Coverage через первичные источники, начиная с популярных
   и неполных карточек.
7. Проверить карточки старше 12 месяцев и обновить verification dates только после
   реальной проверки.
8. Сохранить unresolved GeoCore records неизвестными до появления evidence.
9. Проводить еженедельный production health review и ежемесячный KPI review.
10. Не начинать новый регион или каталог до прохождения соответствующего gate.

---

## 12. Roadmap Governance

- Product owner утверждает изменение приоритетов и release scope.
- Technical owner подтверждает architecture, security, deployment и rollback gates.
- Data/editorial owner отвечает за sources, review SLA, quality и исправления.
- Privacy/legal вопросы подтверждаются компетентным reviewer до включения tracking,
  новых форм, owner claims или коммерческих функций.
- Каждый квартал baseline и targets пересматриваются по production evidence.
- Изменение определения KPI или QualityPolicy фиксируется версией и датой.
- Невыполненный gate переносит scope; он не обходится снижением критерия задним числом.
- Реализованная функция без операционного владельца не считается завершённой.

---

## 13. Определение успеха к концу 2028 года

Roadmap успешен, если:

- PflegeIndex предоставляет широкое, честно измеренное покрытие Германии с высокой
  свежестью и прослеживаемыми источниками;
- пользователи быстро находят прямой контакт и понимают ограничения информации;
- органический трафик растёт без thin pages, манипулятивных claims и ухудшения UX;
- data quality измеряется одной versioned моделью и управляет ежедневной работой;
- минимум два production-каталога используют подтверждённые части Directory Platform;
- deployment, backup, monitoring, privacy и editorial operations воспроизводимы;
- API или premium services, если запущены, создают ценность без влияния на organic
  ranking, official status и quality score;
- рост покрытия не ухудшает точность, accessibility и доверие.

Главный показатель успеха — не количество созданных страниц или модулей, а доля
пользователей, получающих актуальную, понятную и проверяемую информацию для
следующего практического шага.

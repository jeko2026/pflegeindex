# 05_ROADMAP.md

# Directory Platform Roadmap

## Назначение документа

Roadmap определяет долгосрочное развитие Directory Platform.

Документ отражает последовательность развития платформы и помогает принимать решения о приоритетах.

Roadmap является живым документом и регулярно обновляется.

---

# Статусы

Используются следующие статусы:

✅ Завершено

🟡 В разработке

⚪ Запланировано

💡 Идея

---

# Этап 1. Foundation

Статус: ✅ Завершено

## Цель

Создать надёжный фундамент платформы.

## Основные задачи

✅ Git

✅ GitHub Repository

✅ Laravel

✅ Базовая структура проекта

✅ Документация платформы

✅ GeoCore foundation

✅ SEO Foundation

✅ DirectoryCore foundation

---

# Этап 2. GeoCore

Статус: 🟡 В разработке

## Цель

Построить универсальную географическую модель.

### План

✅ Country

✅ State

✅ District

✅ Municipality

✅ Brandenburg official-data import

✅ PflegeIndex City mapping and manual review

🟡 Platform boundary extraction

⚪ Neutral GeoCore read contracts

---

# Этап 3. DirectoryCore

Статус: ✅ Завершено для v1.0 RC

## Реализовано

✅ Immutable value objects и read models

✅ `EntryRepository` contract

✅ `ListEntries` use case

✅ PflegeIndex repository adapter

✅ PflegeIndex card presenter и view models

✅ `/pflegeheime.html` использует DirectoryCore

✅ `/brandenburg/{city}.html` использует DirectoryCore

✅ Поиск, city/type filters, сортировка и пагинация

✅ Автоматическая защита направления зависимостей Platform

✅ Типизированный location scope для city/district/state

✅ District facility list использует DirectoryCore

✅ Brandenburg region facility list использует DirectoryCore

✅ Второй project adapter доказал неизменность Platform API

## Следующие задачи

⚪ Related entries и detail use cases — только после отдельного дизайна

---

# Этап 4. DataCore

Статус: ⚪ Запланировано

## Основные задачи

- общие data contracts, подтверждённые несколькими проектами

- контакты

- координаты

- статусы

- атрибуты

- связи

Универсальная Eloquent-модель `Entry` не планируется. Проектные модели подключаются через adapters.

---

# Этап 5. ImportCore

Статус: ⚪ Запланировано

## Основные задачи

- CSV Import

- JSON Import

- API Import

- Duplicate Detection

- Validation

- Import Logs

---

# Этап 6. SEOCore

Статус: ⚪ Запланировано

## Основные задачи

- Meta Templates

- Canonical

- Open Graph

- JSON-LD

- XML Sitemap

- Robots

- Pagination SEO

- Schema.org

---

# Этап 7. SearchCore

Статус: ⚪ Запланировано

## Основные задачи

- поиск

- автодополнение

- фильтры

- полнотекстовый поиск

- сортировка

---

# Этап 8. MediaCore

Статус: ⚪ Запланировано

## Основные задачи

- изображения

- WebP

- Resize

- Placeholder

- Gallery

- Image Optimizer

---

# Этап 9. ReviewCore

Статус: ⚪ Запланировано

## Основные задачи

- отзывы

- рейтинг

- модерация

- защита от спама

- ответы организаций

---

# Этап 10. Administration

Статус: 🟡 В разработке

## Основные задачи

✅ управление учреждениями PflegeIndex

✅ проверка контактных предложений

✅ базовая аутентификация администратора

⚪ управление импортом через UI

⚪ project-neutral administration contracts

---

# Этап 11. Performance

Статус: ⚪ Запланировано

## Основные задачи

- кеширование

- оптимизация запросов

- Lazy Loading

- очереди

- мониторинг

---

# Этап 12. PflegeIndex

Статус: 🟡 В разработке

## Цель

Запустить первый полноценный каталог.

### План

🟡 Brandenburg

⚪ Berlin

⚪ Sachsen

⚪ Mecklenburg-Vorpommern

⚪ Germany

---

# Следующие проекты

Статус: 💡 Идея

После успешного запуска платформы возможно создание новых каталогов.

Кандидаты:

- BestatterIndex

- FriedhofIndex

- ÄrzteIndex

- HandwerkerIndex

- HotelIndex

- ImmobilienIndex

---

# Version Roadmap

## v0.1

Foundation

## v0.2

GeoCore

## v0.3

DirectoryCore

## v0.4

Architecture Baseline

## v0.5

Location Scope and geographic listings

## v0.6

Project composition and GeoCore boundaries

## v0.7

Second directory architecture proof

## v1.0

Release Candidate первого каталога на базе Directory Platform.

---

# Приоритет разработки

Приоритет всегда определяется следующим образом:

1. Стабильность

2. Архитектура

3. Повторное использование

4. Производительность

5. Новая функциональность

---

# Статус архитектурных итераций

## ✅ Iteration 6 — DirectoryCore Location Scope v2

Определить типизированный нейтральный scope для city, district и state без неявной перегрузки `locationIdentifier`.

## ✅ Iteration 7 — District listing migration

Перевести только facility list районной страницы на DirectoryCore с сохранением GeoCore page context и SEO.

## ✅ Iteration 8 — Brandenburg region listing migration

Перевести основной список федеральной земли на DirectoryCore и сохранить существующие district/city aggregates.

## ⚪ Iteration 9 — Project listing composition

После появления нескольких consumers устранить доказанное дублирование сборки `ListEntries`, presenter и Laravel paginator в project layer.

## ⚪ Iteration 10 — GeoCore boundary hardening

Отделить универсальные GeoCore contracts/read models от Eloquent infrastructure и устранить обратную зависимость `GeoMunicipality → City` отдельным безопасным изменением.

## ✅ Iteration 11 — Second directory architecture proof

Проверить DirectoryCore новым project adapter и presenter без изменения платформенного ядра.

---

# Критерий успеха

Проект считается успешным, когда новый каталог можно запустить без изменения ядра платформы, используя только новый проектный модуль.

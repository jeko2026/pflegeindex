# GeoCore Manual Review Mapping

Дата подготовки: 2026-07-21.

Статус документа: read-only основа для ручного утверждения. Этот документ не является разрешением на импорт и не назначает `geo_municipality_id`.

## 1. Источники и методика

Отчёт сверяет:

- текущую локальную SQLite `database/database.sqlite` через `PRAGMA query_only=ON`;
- `storage/app/geocore/brandenburg/pflegeindex-city-mapping.csv`;
- `storage/app/geocore/brandenburg/manual-review.csv`;
- `storage/app/geocore/brandenburg/official-municipalities.csv`;
- ограничения существующего `geocore:import-brandenburg`.

Ни одна миграция или команда импорта не запускалась. Кандидаты ниже остаются предположениями. Для утверждения требуется официальный источник уровня Gemeinde, Amt, Landkreis или Brandenburg Gemeinde- und Ortsteilverzeichnis; совпадения по названию и PLZ недостаточно.

## 2. Актуальная сводка

| Метрика | Количество городов | Учреждения |
| --- | ---: | ---: |
| Всего | 257 | 1 557 |
| Автоматически сопоставлены (`exact` + `normalized`) | 180 | 1 158 |
| Manual review | 77 | 399 |
| `partial` | 26 | 220 |
| `locality` | 49 | 175 |
| `ambiguous` | 2 | 4 |
| `unmatched` | 0 | 0 |

Состояние SQLite совпадает с mapping CSV: 180 городов имеют `geo_municipality_id`, 77 имеют `geo_requires_manual_review = true`.

## 3. Классификация manual review

Категории `partial`, `locality` и `ambiguous` взаимоисключающие и покрывают все 77 записей. Остальные категории отражают отсутствие отдельной подтверждённой классификации в текущих исходных данных.

| Категория | Количество | Интерпретация |
| --- | ---: | --- |
| Простое отличие написания | 26 | Короткое имя, официальный суффикс, двуязычная форма или уточнение в официальном названии; один кандидат, но автоматическое назначение запрещено текущими правилами. |
| Ortsteil / не самостоятельный населённый пункт | 49 | Текущий источник пометил запись как `locality`; PLZ-кандидаты используются только как подсказки. |
| Объединённая Gemeinde | 0 отдельно подтверждённых | Возможные случаи находятся внутри `locality`, но текущий dataset не доказывает административное объединение отдельным полем. |
| Историческое название | 0 отдельно подтверждённых | Текущий dataset не содержит подтверждённого исторического статуса. |
| Несколько возможных совпадений | 2 формальных `ambiguous` | Briesen и Petershagen. Дополнительно 20 locality-записей имеют более одного PLZ-кандидата. |
| Отсутствует в официальном источнике | 0 `unmatched` | Пять locality-записей не имеют кандидата, но это не доказывает отсутствие Ortsteil в другом официальном источнике. |
| Требует ручной проверки | 77 | Общий статус всех строк этого отчёта. |

## 4. Влияние полного mapping

- После успешного утверждения всех 77 связей district pages смогут получить ещё **399 учреждений**.
- Для **380 учреждений** уже существует хотя бы один кандидат; **19 учреждений** находятся в пяти городах без кандидата.
- Текущие кандидаты затрагивают **15 существующих district pages**: Barnim, Cottbus, Dahme-Spreewald, Elbe-Elster, Havelland, Märkisch-Oderland, Oberhavel, Oberspreewald-Lausitz, Oder-Spree, Ostprignitz-Ruppin, Potsdam-Mittelmark, Prignitz, Spree-Neiße, Teltow-Fläming и Uckermark.
- Количество URL в sitemap, которые должны быть добавлены или удалены: **0**. Все 18 district URLs уже существуют; изменится содержимое существующих страниц.
- Новые district pages появиться не должны: **0**.

Итоговые числа по отдельным районам нельзя считать утверждёнными до ручной проверки каждого кандидата.

## 5. Риски

### Формально ambiguous

- ID 36 — Briesen, 1 учреждение.
- ID 176 — Petershagen, 3 учреждения.

### Несколько PLZ-кандидатов

20 locality-записей имеют `candidate_count > 1`: Angermünde/OT Wolletzsee, Bad Freienwalde, Bernau, Bernau-Waldsiedlung, Blandikow, Buckow, Burg, Eggersdorf, Falkenhagen, Gartz, Grünheide, Herzberg, Lichtenow, Lieskau, Lindow, Neuenhagen, Neustadt, Radensleben, Reichenberg/Märkische Heide и Temnitztal/Wildberg. Первый кандидат в таблице нельзя принимать автоматически.

### Без кандидата

| ID | Город | PLZ | Учреждения |
| ---: | --- | --- | ---: |
| 2 | Alt Ruppin | 16827 | 2 |
| 8 | Annahütte | 01994 | 4 |
| 99 | Hennickendorf | 15378 / 15379 | 4 |
| 141 | Mahlow | 15831 | 5 |
| 198 | Schildow | 16552 | 4 |

Для этих пяти записей нельзя подставлять общеизвестную Gemeinde без документированной проверки официального Ortsteil-источника.

### Slug и нормализация

- Подтверждённых синтаксических ошибок slug: **0**.
- Дубликатов slug: **0**.
- Все 77 slug соответствуют шаблону `^[a-z0-9]+(?:-[a-z0-9]+)*$`.
- Требуют семантической проверки пары/варианты: `Ferch OT Schwielowsee` и `Schwielowsee OT Ferch`; `Werder` и `Werder a. d. Havel`; `Bernau` и `Bernau-Waldsiedlung`; `Groß Kreutz` и `Groß Kreutz (OT Deetz)`.
- `Reichenberg, Märkische Heide` особенно рискован: имя указывает на Märkische Heide, но первый PLZ-кандидат находится в Märkisch-Oderland. Это нельзя исправлять нормализацией строки.
- Несколько внутренних городов могут законно ссылаться на одну Gemeinde. Это не означает, что city slug или city page следует объединять.

## 6. Записи для ручного утверждения

`High` отсутствует: текущие безопасные high-confidence строки уже входят в 180 автоматических связей. `Medium` соответствует одному partial-кандидату. `Low` означает locality/ambiguous или отсутствие кандидата.

| ID | Город | Slug | Учр. | Категория | Предполагаемый Landkreis | Предполагаемая Gemeinde | Причина manual review | Возможный кандидат | Confidence |
| ---: | --- | --- | ---: | --- | --- | --- | --- | --- | --- |
| 2 | Alt Ruppin | `alt-ruppin` | 2 | Ortsteil / locality | — | — | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | — | Low |
| 7 | Angermünde/OT Wolletzsee | `angermuende-ot-wolletzsee` | 1 | Ortsteil / locality | Uckermark | Angermünde, Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12073008` Angermünde, Stadt | Low |
| 8 | Annahütte | `annahuette` | 4 | Ortsteil / locality | — | — | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | — | Low |
| 10 | Bad Freienwalde | `bad-freienwalde` | 10 | Ortsteil / locality | Märkisch-Oderland | Bad Freienwalde (Oder), Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12064044` Bad Freienwalde (Oder), Stadt | Low |
| 14 | Baruth | `baruth` | 1 | Простое отличие написания | Teltow-Fläming | Baruth/Mark, Stadt | Короткое/неполное имя отличается от официального. | `12072014` Baruth/Mark, Stadt | Medium |
| 17 | Beelitz-Heilstätten | `beelitz-heilstaetten` | 3 | Ortsteil / locality | Potsdam-Mittelmark | Beelitz, Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12069017` Beelitz, Stadt | Low |
| 22 | Bernau | `bernau` | 28 | Ortsteil / locality | Barnim | Bernau bei Berlin, Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12060020` Bernau bei Berlin, Stadt | Low |
| 24 | Bernau-Waldsiedlung | `bernau-waldsiedlung` | 1 | Ortsteil / locality | Barnim | Bernau bei Berlin, Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12060020` Bernau bei Berlin, Stadt | Low |
| 28 | Blandikow | `blandikow` | 1 | Ortsteil / locality | Ostprignitz-Ruppin | Wittstock/Dosse, Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12068468` Wittstock/Dosse, Stadt | Low |
| 29 | Blankenfelde | `blankenfelde` | 2 | Ortsteil / locality | Teltow-Fläming | Blankenfelde-Mahlow | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12072017` Blankenfelde-Mahlow | Low |
| 36 | Briesen | `briesen` | 1 | Несколько совпадений | Spree-Neiße | Briesen/Brjazyna | Имя допускает несколько официальных совпадений. | `12071028` Briesen/Brjazyna | Low |
| 39 | Buckow | `buckow` | 2 | Ortsteil / locality | Märkisch-Oderland | Buckow (Märkische Schweiz), Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12064084` Buckow (Märkische Schweiz), Stadt | Low |
| 40 | Burg | `burg` | 4 | Ortsteil / locality | Spree-Neiße | Burg (Spreewald)/Bórkowy (Błota) | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12071032` Burg (Spreewald)/Bórkowy (Błota) | Low |
| 41 | Burxdorf | `burxdorf` | 1 | Ortsteil / locality | Elbe-Elster | Falkenberg/Elster, Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12062128` Falkenberg/Elster, Stadt | Low |
| 42 | Calau | `calau` | 6 | Простое отличие написания | Oberspreewald-Lausitz | Calau/Kalawa, Stadt | Короткое/неполное имя отличается от официального. | `12066052` Calau/Kalawa, Stadt | Medium |
| 44 | Cottbus | `cottbus` | 72 | Простое отличие написания | Cottbus, Stadt | Cottbus/Chóśebuz, Stadt | Короткое/неполное имя отличается от официального. | `12052000` Cottbus/Chóśebuz, Stadt | Medium |
| 46 | Dahme | `dahme` | 5 | Простое отличие написания | Teltow-Fläming | Dahme/Mark, Stadt | Короткое/неполное имя отличается от официального. | `12072053` Dahme/Mark, Stadt | Medium |
| 49 | Döbern | `doebern` | 3 | Простое отличие написания | Spree-Neiße | Döbern/Derbno, Stadt | Короткое/неполное имя отличается от официального. | `12071044` Döbern/Derbno, Stadt | Medium |
| 50 | Drebkau | `drebkau` | 1 | Простое отличие написания | Spree-Neiße | Drebkau/Drjowk, Stadt | Короткое/неполное имя отличается от официального. | `12071057` Drebkau/Drjowk, Stadt | Medium |
| 53 | Eggersdorf | `eggersdorf` | 3 | Ortsteil / locality | Märkisch-Oderland | Petershagen/Eggersdorf | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12064380` Petershagen/Eggersdorf | Low |
| 59 | Falkenhagen | `falkenhagen` | 1 | Ortsteil / locality | Märkisch-Oderland | Falkenhagen (Mark) | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12064128` Falkenhagen (Mark) | Low |
| 62 | Ferch OT Schwielowsee | `ferch-ot-schwielowsee` | 1 | Ortsteil / locality | Potsdam-Mittelmark | Schwielowsee | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12069590` Schwielowsee | Low |
| 63 | Finowfurt | `finowfurt` | 1 | Ortsteil / locality | Barnim | Schorfheide | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12060198` Schorfheide | Low |
| 66 | Forst (Lausitz) | `forst-lausitz` | 17 | Простое отличие написания | Spree-Neiße | Forst (Lausitz)/Baršć (Łužyca), Stadt | Короткое/неполное имя отличается от официального. | `12071076` Forst (Lausitz)/Baršć (Łužyca), Stadt | Medium |
| 71 | Fürstenberg | `fuerstenberg` | 4 | Простое отличие написания | Oberhavel | Fürstenberg/Havel, Stadt | Короткое/неполное имя отличается от официального. | `12065084` Fürstenberg/Havel, Stadt | Medium |
| 73 | Gartz | `gartz` | 4 | Ortsteil / locality | Uckermark | Gartz (Oder), Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12073189` Gartz (Oder), Stadt | Low |
| 76 | Glienicke | `glienicke` | 3 | Простое отличие написания | Oberhavel | Glienicke/Nordbahn | Короткое/неполное имя отличается от официального. | `12065096` Glienicke/Nordbahn | Medium |
| 85 | Groß Kreutz | `gross-kreutz` | 2 | Ortsteil / locality | Potsdam-Mittelmark | Groß Kreutz (Havel) | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12069249` Groß Kreutz (Havel) | Low |
| 87 | Groß Kreutz (OT Deetz) | `gross-kreutz-ot-deetz` | 1 | Ortsteil / locality | Potsdam-Mittelmark | Groß Kreutz (Havel) | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12069249` Groß Kreutz (Havel) | Low |
| 90 | Großkoschen | `grosskoschen` | 1 | Ortsteil / locality | Oberspreewald-Lausitz | Senftenberg/Zły Komorow, Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12066304` Senftenberg/Zły Komorow, Stadt | Low |
| 91 | Großräschen | `grossraeschen` | 9 | Простое отличие написания | Oberspreewald-Lausitz | Großräschen/Rań, Stadt | Короткое/неполное имя отличается от официального. | `12066112` Großräschen/Rań, Stadt | Medium |
| 92 | Grünheide | `gruenheide` | 5 | Ortsteil / locality | Oder-Spree | Grünheide (Mark) | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12067201` Grünheide (Mark) | Low |
| 99 | Hennickendorf | `hennickendorf` | 4 | Ortsteil / locality | — | — | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | — | Low |
| 101 | Herzberg | `herzberg` | 10 | Ortsteil / locality | Elbe-Elster | Herzberg (Elster), Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12062224` Herzberg (Elster), Stadt | Low |
| 110 | Ketzin | `ketzin` | 3 | Простое отличие написания | Havelland | Ketzin/Havel, Stadt | Короткое/неполное имя отличается от официального. | `12063148` Ketzin/Havel, Stadt | Medium |
| 113 | Klettwitz | `klettwitz` | 1 | Ortsteil / locality | Oberspreewald-Lausitz | Schipkau | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12066285` Schipkau | Low |
| 115 | Kolkwitz | `kolkwitz` | 3 | Простое отличие написания | Spree-Neiße | Kolkwitz/Gołkojce | Короткое/неполное имя отличается от официального. | `12071244` Kolkwitz/Gołkojce | Medium |
| 116 | Kolkwitz/Limberg | `kolkwitz-limberg` | 1 | Ortsteil / locality | Spree-Neiße | Kolkwitz/Gołkojce | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12071244` Kolkwitz/Gołkojce | Low |
| 119 | Kremmen/OT Sommerfeld | `kremmen-ot-sommerfeld` | 1 | Ortsteil / locality | Oberhavel | Kremmen, Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12065165` Kremmen, Stadt | Low |
| 128 | Lichtenow | `lichtenow` | 1 | Ortsteil / locality | Märkisch-Oderland | Reichenow-Möglin | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12064417` Reichenow-Möglin | Low |
| 130 | Lieskau | `lieskau` | 1 | Ortsteil / locality | Elbe-Elster | Gorden-Staupitz | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12062177` Gorden-Staupitz | Low |
| 132 | Lindow | `lindow` | 3 | Ortsteil / locality | Ostprignitz-Ruppin | Lindow (Mark), Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12068280` Lindow (Mark), Stadt | Low |
| 135 | Lübben | `luebben` | 10 | Ortsteil / locality | Dahme-Spreewald | Lübben (Spreewald) / Lubin (Błota), Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12061316` Lübben (Spreewald) / Lubin (Błota), Stadt | Low |
| 136 | Lübbenau/Spreewald | `luebbenau-spreewald` | 11 | Простое отличие написания | Oberspreewald-Lausitz | Lübbenau/Spreewald / Lubnjow/Błota, Stadt | Короткое/неполное имя отличается от официального. | `12066196` Lübbenau/Spreewald / Lubnjow/Błota, Stadt | Medium |
| 141 | Mahlow | `mahlow` | 5 | Ortsteil / locality | — | — | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | — | Low |
| 142 | Märkische Heide | `maerkische-heide` | 1 | Простое отличие написания | Dahme-Spreewald | Märkische Heide/Markojska Góla | Короткое/неполное имя отличается от официального. | `12061329` Märkische Heide/Markojska Góla | Medium |
| 147 | Mühlberg | `muehlberg` | 5 | Простое отличие написания | Elbe-Elster | Mühlberg/Elbe, Stadt | Короткое/неполное имя отличается от официального. | `12062341` Mühlberg/Elbe, Stadt | Medium |
| 148 | Mühlenbeck | `muehlenbeck` | 1 | Ortsteil / locality | Oberhavel | Mühlenbecker Land | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12065225` Mühlenbecker Land | Low |
| 154 | Neuenhagen | `neuenhagen` | 9 | Ortsteil / locality | Märkisch-Oderland | Neuenhagen bei Berlin | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12064336` Neuenhagen bei Berlin | Low |
| 156 | Neuhausen | `neuhausen` | 2 | Простое отличие написания | Spree-Neiße | Neuhausen/Spree / Kopańce/Sprjewja | Короткое/неполное имя отличается от официального. | `12071301` Neuhausen/Spree / Kopańce/Sprjewja | Medium |
| 158 | Neustadt | `neustadt` | 3 | Ortsteil / locality | Ostprignitz-Ruppin | Neustadt (Dosse), Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12068324` Neustadt (Dosse), Stadt | Low |
| 170 | Oschätzchen | `oschaetzchen` | 1 | Ortsteil / locality | Elbe-Elster | Bad Liebenwerda, Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12062024` Bad Liebenwerda, Stadt | Low |
| 172 | Panketal-Schwanebeck | `panketal-schwanebeck` | 1 | Ortsteil / locality | Barnim | Panketal | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12060181` Panketal | Low |
| 174 | Peitz | `peitz` | 6 | Простое отличие написания | Spree-Neiße | Peitz/Picnjo, Stadt | Короткое/неполное имя отличается от официального. | `12071304` Peitz/Picnjo, Stadt | Medium |
| 176 | Petershagen | `petershagen` | 3 | Несколько совпадений | Märkisch-Oderland | Petershagen/Eggersdorf | Имя допускает несколько официальных совпадений. | `12064380` Petershagen/Eggersdorf | Low |
| 177 | Plattenburg OT Hoppenrade | `plattenburg-ot-hoppenrade` | 1 | Ortsteil / locality | Prignitz | Plattenburg | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12070302` Plattenburg | Low |
| 183 | Prieschka | `prieschka` | 1 | Ortsteil / locality | Elbe-Elster | Bad Liebenwerda, Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12062024` Bad Liebenwerda, Stadt | Low |
| 186 | Radensleben | `radensleben` | 1 | Ortsteil / locality | Ostprignitz-Ruppin | Walsleben | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12068452` Walsleben | Low |
| 190 | Reichenberg, Märkische Heide | `reichenberg-maerkische-heide` | 1 | Ortsteil / locality | Märkisch-Oderland | Märkische Höhe | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12064303` Märkische Höhe | Low |
| 196 | Rüdersdorf | `ruedersdorf` | 8 | Ortsteil / locality | Märkisch-Oderland | Rüdersdorf bei Berlin | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12064428` Rüdersdorf bei Berlin | Low |
| 198 | Schildow | `schildow` | 4 | Ortsteil / locality | — | — | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | — | Low |
| 200 | Schlepzig | `schlepzig` | 2 | Простое отличие написания | Dahme-Spreewald | Schlepzig/Słopišća | Короткое/неполное имя отличается от официального. | `12061428` Schlepzig/Słopišća | Medium |
| 203 | Schöneiche | `schoeneiche` | 7 | Ortsteil / locality | Oder-Spree | Schöneiche bei Berlin | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12067440` Schöneiche bei Berlin | Low |
| 210 | Schwielochsee | `schwielochsee` | 2 | Простое отличие написания | Dahme-Spreewald | Schwielochsee/Gójacki Jazor | Короткое/неполное имя отличается от официального. | `12061450` Schwielochsee/Gójacki Jazor | Medium |
| 212 | Schwielowsee OT Ferch | `schwielowsee-ot-ferch` | 1 | Ortsteil / locality | Potsdam-Mittelmark | Schwielowsee | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12069590` Schwielowsee | Low |
| 214 | Senftenberg | `senftenberg` | 14 | Простое отличие написания | Oberspreewald-Lausitz | Senftenberg/Zły Komorow, Stadt | Короткое/неполное имя отличается от официального. | `12066304` Senftenberg/Zły Komorow, Stadt | Medium |
| 217 | Spremberg | `spremberg` | 22 | Простое отличие написания | Spree-Neiße | Spremberg/Grodk, Stadt | Короткое/неполное имя отличается от официального. | `12071372` Spremberg/Grodk, Stadt | Medium |
| 220 | Storkow | `storkow` | 7 | Ortsteil / locality | Oder-Spree | Storkow (Mark), Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12067481` Storkow (Mark), Stadt | Low |
| 225 | Temnitztal/Wildberg | `temnitztal-wildberg` | 1 | Ortsteil / locality | Ostprignitz-Ruppin | Temnitztal | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12068426` Temnitztal | Low |
| 230 | Tschernitz | `tschernitz` | 1 | Простое отличие написания | Spree-Neiße | Tschernitz/Cersk | Короткое/неполное имя отличается от официального. | `12071392` Tschernitz/Cersk | Medium |
| 232 | Vetschau/Spreewald | `vetschau-spreewald` | 7 | Простое отличие написания | Oberspreewald-Lausitz | Vetschau/Spreewald / Wětošow/Błota, Stadt | Короткое/неполное имя отличается от официального. | `12066320` Vetschau/Spreewald / Wětošow/Błota, Stadt | Medium |
| 234 | Wahrenbrück | `wahrenbrueck` | 1 | Ortsteil / locality | Elbe-Elster | Bad Liebenwerda, Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12062024` Bad Liebenwerda, Stadt | Low |
| 238 | Welzow | `welzow` | 4 | Простое отличие написания | Spree-Neiße | Welzow/Wjelcej, Stadt | Короткое/неполное имя отличается от официального. | `12071408` Welzow/Wjelcej | Medium |
| 240 | Werder | `werder` | 11 | Ortsteil / locality | Potsdam-Mittelmark | Werder (Havel), Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12069656` Werder (Havel), Stadt | Low |
| 242 | Werder a. d. Havel | `werder-a-d-havel` | 1 | Ortsteil / locality | Potsdam-Mittelmark | Werder (Havel), Stadt | Вероятный Ortsteil или не самостоятельный населённый пункт; PLZ недостаточен. | `12069656` Werder (Havel), Stadt | Low |
| 248 | Wittstock | `wittstock` | 13 | Простое отличие написания | Ostprignitz-Ruppin | Wittstock/Dosse, Stadt | Короткое/неполное имя отличается от официального. | `12068468` Wittstock/Dosse, Stadt | Medium |
| 252 | Wusterhausen | `wusterhausen` | 3 | Простое отличие написания | Ostprignitz-Ruppin | Wusterhausen/Dosse | Короткое/неполное имя отличается от официального. | `12068477` Wusterhausen/Dosse | Medium |

## 7. Безопасная процедура следующего этапа

1. Проверить каждую строку по официальному Ortsteil/Gemeinde-источнику и сохранить URL источника решения.
2. Для строк с несколькими кандидатами проверить адреса каждого учреждения, а не только агрегированный PLZ города.
3. Для пяти строк без кандидата сначала установить официальную Gemeinde; не использовать предположение как mapping.
4. Сформировать отдельный утверждённый data-fix с `city_id`, целевым municipality AGS, источником и решением владельца.
5. Перед применением сделать backup SQLite, выполнить dry-run и сверить неизменность 257 городов и 1 557 учреждений.
6. После применения проверить 18 district pages, особенно Cottbus, sitemap membership и итоговые facility counts.

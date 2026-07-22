# Search and Analytics Setup

## 1. Назначение

PflegeIndex подготовлен к подтверждению сайта в Google Search Console и Bing
Webmaster Tools, а также к опциональному подключению Google Analytics 4 и
Microsoft Clarity.

Интеграции аналитики выключены по умолчанию. Пока соответствующий идентификатор
не задан, HTML страницы не содержит кода сервиса и браузер не отправляет ему
запросы.

## 2. Обязательная проверка перед включением аналитики

GA4 и Clarity являются внешними сервисами и могут обрабатывать технические и
персональные данные посетителей. До добавления production-ID необходимо:

1. определить правовое основание и необходимость согласия пользователей;
2. при необходимости внедрить consent-механизм до загрузки скриптов;
3. обновить Datenschutzerklärung, которая сейчас описывает сайт без аналитики;
4. проверить настройки хранения данных, передачи за пределы ЕС и договоры с
   поставщиками;
5. повторно провести privacy-аудит.

Наличие технической интеграции само по себе не является разрешением включать
аналитику в production.

## 3. Google Search Console

### Вариант A: HTML-файл

1. В Search Console выбрать подтверждение через HTML-файл.
2. Скачать выданный Google файл без переименования и изменения содержимого.
3. Поместить его в Laravel-директорию `public/` и загрузить в document root
   production-сайта.
4. Проверить прямой адрес вида
   `https://pflegeindex.com/google1234567890abcdef.html`.
5. Адрес должен вернуть `HTTP 200` без перенаправления, авторизации и HTML
   приложения вместо содержимого проверочного файла.
6. Выполнить подтверждение в Search Console и не удалять файл, пока используется
   этот способ проверки.

Проверочный файл следует добавлять только после получения оригинала от Google.
Нельзя создавать или угадывать его имя и содержимое.

### Вариант B: meta tag

1. В Search Console скопировать выданный тег
   `<meta name="google-site-verification" content="...">`.
2. Добавить его в `<head>` общего layout
   `resources/views/layouts/app.blade.php`, чтобы тег присутствовал на главной
   странице без JavaScript.
3. Очистить view/config cache при deployment.
4. Открыть исходный HTML `https://pflegeindex.com/` и убедиться, что тег виден,
   а его значение совпадает с выданным Google.
5. После этого выполнить подтверждение.

Секретом этот verification token обычно не является, но использовать нужно
только значение, выданное для домена PflegeIndex. Сейчас meta tag намеренно не
добавлен, поскольку реальный token отсутствует.

Для Domain property Google может потребовать DNS TXT verification. Этот способ
настраивается у DNS-провайдера и не требует изменения Laravel.

## 4. Bing Webmaster Tools

### Вариант A: XML-файл

1. В Bing Webmaster Tools выбрать XML File Authentication.
2. Скачать оригинальный файл `BingSiteAuth.xml`.
3. Поместить его в `public/` и загрузить в production document root.
4. Проверить `https://pflegeindex.com/BingSiteAuth.xml`: ответ `HTTP 200`, без
   redirect и без подмены страницей Laravel.
5. Подтвердить сайт и сохранять файл, пока этот метод используется.

### Вариант B: meta tag

1. Скопировать выданный Bing тег
   `<meta name="msvalidate.01" content="...">`.
2. Добавить его в `<head>` общего layout
   `resources/views/layouts/app.blade.php`.
3. Выполнить deployment и очистить view/config cache.
4. Проверить исходный HTML главной страницы и затем подтвердить сайт в Bing.

Реальный Bing token в репозитории сейчас отсутствует. Его нельзя заменять
placeholder-значением. Bing также позволяет импортировать подтверждённый ресурс
из Google Search Console; доступность этого варианта следует проверять в текущем
интерфейсе Bing.

## 5. Google Analytics 4

Интеграция читает Measurement ID через Laravel config:

```text
config('services.analytics.ga4_measurement_id')
```

Источник значения в `.env`:

```text
GA4_MEASUREMENT_ID=
```

Пустое или отсутствующее значение полностью исключает GA4-код из HTML. Для
активации после privacy-проверки нужно указать выданный ID формата `G-...` в
production `.env`, затем выполнить:

```text
php artisan config:clear
php artisan config:cache
```

Проверка:

1. открыть исходный HTML публичной страницы;
2. убедиться, что присутствует только ожидаемый Measurement ID;
3. в DevTools проверить загрузку `https://www.googletagmanager.com/gtag/js`;
4. проверить DebugView или Realtime в GA4;
5. убедиться, что при удалённом ID запрос к домену Google отсутствует.

## 6. Microsoft Clarity

Интеграция читает Project ID через:

```text
config('services.analytics.clarity_project_id')
```

Источник значения в `.env`:

```text
CLARITY_PROJECT_ID=
```

Пустое или отсутствующее значение полностью исключает Clarity-код из HTML. Для
активации после privacy-проверки нужно указать Project ID в production `.env` и
пересобрать config cache теми же командами.

Проверка:

1. открыть исходный HTML и найти ожидаемый Project ID;
2. в DevTools проверить запрос к `https://www.clarity.ms/tag/`;
3. проверить поступление сессии в панели Clarity;
4. удалить ID, пересобрать config cache и подтвердить отсутствие внешнего
   запроса.

## 7. Где задавать значения

`.env.example` содержит только пустые имена переменных. Реальные ID задаются
только в production `.env`, который не коммитится и не должен находиться в
public document root:

```text
GA4_MEASUREMENT_ID=
CLARITY_PROJECT_ID=
```

Не следует добавлять реальные ID в Blade, JavaScript или deployment-документы.
Для временного отключения достаточно оставить значение пустым и пересобрать
config cache.

## 8. Проверка безопасного выключения

После deployment с пустыми значениями:

1. открыть исходный HTML главной и одной карточки учреждения;
2. убедиться, что строки `googletagmanager.com`, `gtag(`, `clarity.ms` и
   `window.dataLayer` отсутствуют;
3. проверить вкладку Network без фильтра и убедиться, что нет запросов к Google
   Analytics и Clarity;
4. проверить Console: отсутствие ID не должно вызывать JavaScript-ошибки;
5. повторить проверку после `php artisan config:cache`.

Feature-тесты фиксируют оба режима: отсутствие скриптов без конфигурации и их
появление при заданных test-only ID.

# Memory — tools.googlepagespeed

Память **только** модуля «Инструменты для Google PageSpeed». Сюда — нюансы, решения и договорённости по этому репозиторию. Общий `site/memory.md` — про сайт; сюда не тащить.

Читать в начале работы над модулем; важные находки дописывать сюда.

## Репозиторий и Git

- **Отдельный git-репозиторий** (свой `.git`), не submodule родителя `site/`.
- Remote: `https://github.com/Just-guy/tools.googlepagespeed.git`, ветка `main`.
- Путь в сайте: `local/modules/tools.googlepagespeed/`.
- Родительский репозиторий `site/` видит каталог как untracked `??` — так и задумано.
- Cursor SCM по умолчанию (`git.repositoryScanMaxDepth: 1`) модуль не подхватывает — глубина 3. Чтобы появился во вкладке: `repositoryScanMaxDepth` ≥ 3 и/или **Git: Open Repository** → этот каталог.
- Коммиты модуля — **в этом** `.git`, не в корне сайта.

## Назначение

Модуль Bitrix модифицирует HTML публичных страниц перед отдачей (хук `OnEndBufferContent`), чтобы снизить замечания Lighthouse: resource hints (`link`), `async`/`defer` для скриптов, lazy для картинок, отложенный CSS, опционально вырезание Метрики / GA / GTM (в т.ч. только для робота).

Это дополнение поверх шаблона, не замена нормальной оптимизации. После установки всё выключено — работает только после включения опций и «Применить».

Документация для людей: `readme.md`. Версия в `install/version.php` (сейчас `1.3.5`).

## Структура

| Путь | Назначение |
|------|------------|
| `lib/Main.php` | Оркестратор `OnEndBufferContent` |
| `lib/BufferGuard.php` | Пропуск admin / AJAX / CLI / ответов без `<head>` |
| `lib/HtmlBuffer.php` | Вставка после `<head>`, async/defer на `<script>` |
| `lib/RobotDetector.php` | Детект UA Lighthouse |
| `lib/SettingsProvider.php` | Чтение настроек модуля (опции / link / script) через ManagedCache |
| `lib/OptionActions.php` | Действия опций + реестр `OPTION_ACTION` |
| `lib/ScriptDeferral.php` | Очередь отложенных скриптов, stubs, runtime |
| `lib/DeferredPresets.php` | Определения пресетов E + ensure в БД |
| `lib/ScriptScanCatalog.php` | Реестр сканера: hide (ядро/аналитика) + presets (Jivo и др.) |
| `lib/PageScriptScanner.php` | HTTP-скан страницы, разбор `<script src>`, классификация |
| `lib/GPSOptions.php` | ORM опций |
| `lib/ConnectedCssStyle.php` | ORM правил `<link>` (preload и т.п.) |
| `lib/ConnectedJsScript.php` | ORM правил `<script>` (async/defer) |
| `include.php` | Автозагрузка классов |
| `admin/tools.googlepagespeed_options.php` | Админка: вкладки Опции / Тэг link / Тэг script (+ AJAX скан) |
| `admin/menu.php` | Пункт меню в «Настройки» |
| `install/` | Установка / удаление, копия admin-скрипта |

Админка: **Настройки → Инструменты для Google PageSpeed → Настройки**.

## Карта классов и методов (v1.3.4)

Поток публички: `OnEndBufferContent` → `Main` → (опции / link / script) → HTML.

```
Main::OnEndBufferContent
  ├─ ScriptDeferral::reset
  ├─ BufferGuard::shouldSkip → выход
  ├─ SettingsProvider::getLinksCssStyles(ACTIVE=Y)
  │    └─ HtmlBuffer::insertAfterOpeningHead  (preload/prefetch…)
  ├─ SettingsProvider::getOptions(ACTIVE=Y)
  │    ├─ LIMITATION=for-gps-robot → RobotDetector::isPageSpeedRobot
  │    ├─ OPTION_TYPE=regular-expression → preg_replace (вырезать Метрику/GA/GTM)
  │    └─ OPTION_TYPE=function → OptionActions::run(OPTION_ACTION)
  │         ├─ eliminateStyleSheetsThatBlockDisplay
  │         ├─ eliminateScriptsGeneralJs  → relocateMatchingHeadScripts(jquery)
  │         ├─ eliminateScriptsAsproJs    → relocateMatchingHeadScripts(speed.min)
  │         ├─ cutYandexMetrika
  │         ├─ addLoadingLazyAttributeAllTagsImg
  │         ├─ addDecodingAsyncAttributeAllTagsImg
  │         ├─ ScriptDeferral::deferYandexMetrika
  │         └─ ScriptDeferral::deferGoogleAnalytics
  ├─ SettingsProvider::getLinksJsScripts(ACTIVE=Y)
  │    └─ HtmlBuffer::addAttributeToMatchingScripts  (async/defer по правилам вкладки)
  └─ ScriptDeferral::injectRuntime  (idle/interaction loader перед </body>)
```

| Класс | За что | Методы |
|-------|--------|--------|
| **Main** | Единственный вход с хука | `OnEndBufferContent` |
| **BufferGuard** | Не трогать admin/AJAX/CLI/без head | `shouldSkip` |
| **SettingsProvider** | Чтение настроек + ManagedCache | `getOptions`, `getLinksCssStyles`, `getLinksJsScripts`, `clearCache` |
| **RobotDetector** | UA Lighthouse | `isPageSpeedRobot` |
| **HtmlBuffer** | Правки разметки для вкладок link/script | `insertAfterOpeningHead`, `addAttributeToMatchingScripts` |
| **OptionActions** | Реестр и тело опций вкладки «Опции» | `run`, `eliminateStyleSheets…`, `eliminateScriptsGeneralJs`, `eliminateScriptsAsproJs`, `eliminateScriptsThatBlockDisplay` (deprecated alias = оба), `relocateMatchingHeadScripts` (private), `addLoadingLazy…`, `addDecodingAsync…`, `ensureEliminateScriptsOption`, `ensureImgAttributeOptions`, `getImgAttributeOptionDefinitions` |
| **ScriptDeferral** | Пресеты «отложить» (не путать с вырезать) | `reset`, `deferYandexMetrika`, `deferGoogleAnalytics`, `injectRuntime` |
| **DeferredPresets** | Строки БД для пресетов E | `getOptionDefinitions`, `ensureOptions` |
| **PageScriptScanner** | AJAX-скан вкладки «Тэг script» | `scan`, `parseUrlList`, `getPublicOrigin`, `normalizeSrc`, `suggestPublicPart` |
| **ScriptScanCatalog** | Справочник сканера | `getHideRules`, `getPresets`, `getScanUrlPresets`, `matchHide`, `matchPreset` |
| **GPSOptionsTable** | ORM `b_gps_options` | стандартный DataManager + `exitsOrCreateTable` / `dropTable` |
| **ConnectedCssStyleTable** | ORM правил link | то же |
| **ConnectedJsScriptTable** | ORM правил script | то же |

**OPTION_TYPE в БД:** `function` (вызов из ACTIONS), `regular-expression` (вырезание), `heading` (только UI, Main не вызывает).

**Eliminate scripts:** заголовок `ELIMINATE_SCRIPTS_THAT_BLOCK_DISPLAY` (`heading`); дети `ELIMINATE_SCRIPTS_GENERAL_JS` / `ELIMINATE_SCRIPTS_ASPRO_JS`. Перенос из `<head>` сразу после `<body>` в `<!--gps-rb-scripts-->`.

## Ключевые решения (код)

- **Разбиение `Main` (вариант A):** оркестрация в `Main`, домены — отдельные классы (см. таблицу структуры). Публичный API админки/install/ORM: `SettingsProvider`, `DeferredPresets` (не фасады на `Main`).
- Обработка только публичного HTML: пропуск admin, AJAX, CLI, ответов без `<head>` (`BufferGuard::shouldSkip`).
- Preload/`link` вставляются **сразу после** открывающего `<head>`, без затирания тега и атрибутов (`HtmlBuffer`).
- `async`/`defer` вешаются на весь открывающий `<script src="...">`, не на фрагмент `src`.
- Отложенный CSS: `media="print" onload="this.media='all'"` + `<noscript>` с исходным тегом; `media=print` не трогать повторно (`OptionActions`).
- Lazy / decoding: кандидат LCP = **первое осмысленное** `<img>` (не шум) — его не трогают; остальным осмысленным — атрибут. Шум: внутри `<noscript>`, пиксели счётчиков (`IMG_PIXEL_MARKERS`), нет/`data:` src, только `data-src`, `display:none` / `left:-NNNpx`, `width=1`+`height=1`. Обход через `mapImgTags` + диапазоны noscript.
- **Img-атрибуты (v1.3.x):** `addDecodingAsyncAttributeAllTagsImg` (+ lazy). Опция авто-`fetchpriority` **удалена** (v1.3.2): эвристика «первый img» ненадёжна (пиксели, неверный LCP); ensure удаляет `ADD_FETCHPRIORITY_HIGH_FIRST_IMG` из БД. Ensure: `OptionActions::ensureImgAttributeOptions()`.
- Опции типа `function`: реестр `OptionActions::ACTIONS` (имя → class::method); `unserialize` без объектов (`allowed_classes => false`).
- Правила (опции / link / script) кешируются в ManagedCache (`SettingsProvider`, `CACHE_DIR = tools_googlepagespeed`, TTL 3600); при сохранении — `SettingsProvider::clearCache()`.
- Робот PageSpeed: UA содержит `"Lighthouse"` (`RobotDetector::isPageSpeedRobot`). Область «только для робота» — осознанный компромисс, не включать «на всякий случай».
- Открывающие теги PHP — только `<?php` (короткие `<?` убраны).
- **Пресеты отложенной загрузки (вариант E, v1.1.0):** `ScriptDeferral` + опции `deferYandexMetrika` / `deferGoogleAnalytics` (`deferJivoChat` — закомментирован). Stub (ym/gtag) + runtime перед `</body>`: idle или первое взаимодействие. Строки в БД: `DeferredPresets::ensureOptions()` при открытии админки. Не путать с опциями «Вырезать…». Подробная карта вариантов A–F — раздел ниже «Отложенная загрузка (варианты)».
- **Скан скриптов страницы (v1.2.0):** вкладка «Тэг script» — HTTP GET публичного URL → список `<script src>`. Реестр в `ScriptScanCatalog`: **hide** только в `getHideRules()` (якоря на `/bitrix/js|cache|components|panel|themes|tools|resources|admin|modules/`, без `/bitrix/templates/`; вложенные `/local/.../bitrix/...` не матчятся). Метрика/GA/GTM — analytics. **presets** / **getScanUrlPresets**. Уже с `async`/`defer` скрываются. **Несколько URL:** запятая / `;` / перевод строки (макс. 5). **Loopback:** `localhost`/`127.0.0.1` для HTTP-запроса заменяются на `SERVER_NAME` сайта Bitrix (если он не loopback) — без привязки к Docker; пресеты URL берут `PageScriptScanner::getPublicOrigin()`. **publicPart:** свои пути — полный path без домена; внешние — `host+path`.

## Код (стиль)

- **Методы классов — рационально:** не плодить `getX` / `extractY` / deprecated-обёртки ради одной развилки. Один реестр + один matcher, если данных хватает (пример: hide только через `getHideRules` + `matchHide`). Новый метод — только при повторе, реальной ветке логики или требовании API; не «на будущее» и не дробить один список на три метода.

## Отложенная загрузка (варианты)

Обсуждение для доработок модуля. Модель: правка HTML в `OnEndBufferContent` + админ-переключатели. Термины: **stub** — заглушка API (`ym`/`gtag`) до подгрузки скрипта; **idle** — `requestIdleCallback` (простой браузера) + `timeout`, иначе скрипт всё равно стартует по таймеру.

### Сводная таблица

| Вариант | Что откладывает | Как / когда грузит | Как задают цели | Статус |
|--------|-----------------|--------------------|-----------------|--------|
| **A** idle / interaction | Внешние/inline `<script>` по URL (аналитика, теги, чаты) | Stub + лоадер; **жест** (scroll/touch/keydown) **или idle** (timeout ~5 с / fallback load+2.5 с) — что раньше | Ручной regex URL (как вкладка script) | Частично через **E** (тот же механизм) |
| **B** visible (viewport) | Скрипты/блоки ниже fold (карты, виджеты) | `IntersectionObserver` (+ rootMargin) | Ручной regex URL **и/или** кусок разметки (блок) | Не сделано |
| **C** facade / клик | Тяжёлые iframe (YouTube, иногда карта) | Заглушка (превью/кнопка); грузит **только по клику** | Правила на тип iframe / URL | Не сделано |
| **D** native lazy media | `<iframe>`, `<video>` | Атрибут `loading="lazy"` (решает браузер у viewport) | Галочки в опциях | Не сделано (в roadmap п.3) |
| **E** пресеты | Готовые наборы (Метрика, GA, …) | Внутри = **A** (иногда кусок **B**) | Чекбоксы без ручного regex | **Сделано:** Метрика, GA; Jivo закомментирован |
| **F** «только для робота» | Не способ загрузки | Область действия правила | Уже есть `LIMITATION` for-everyone / for-gps-robot | **Уже было** — не отдельная фича |

### Разница и пересечения

- **A vs E:** один фронтовый механизм; E — удобные чекбоксы с зашитыми паттернами/stubs; A — универсальные ручные URL.
- **A vs B:** A — по времени/жесту для любых URL; B — по **видимости** блока/скрипта на экране. Цели B: URL и/или фрагмент HTML.
- **C vs D:** одна задача для media («не грузить iframe сразу»), разная агрессия. **На одном iframe не включать оба:** C заменяет iframe до клика, D вешает lazy на существующий. В модуле — два режима на выбор.
- **F:** не новый способ отложить; это уже существующий селект области. Варианты A–E могут работать «для всех» или «только для робота».

### Runtime пресета E (как сейчас в коде)

1. Подходящие `<script src>` и inline убираются из первого кадра.
2. Stub: `ym` (Метрика), `dataLayer`+`gtag` (GA); у Jivo stub не планировался.
3. Один `<script data-gps-defer-runtime>` перед `</body>`: очередь URL + inline → `gpsRun`.
4. Триггеры `gpsRun`: interaction **или** idle(+timeout). Без жеста скрипт всё равно подгрузится через несколько секунд.
5. Не включать одновременно «Вырезать…» и «Отложить…» на одних посетителях — вырезание съест теги раньше.
6. На сайте-носителе шаблонный `isLighthouse()` может **не выводить** Метрику роботу — тогда модулю нечего откладывать; проверка пресета — в обычном Chrome (исходник + Network).

### Рекомендуемый порядок внедрения (если продолжать)

1. Допилить/стабилизировать **E** (уже база).
2. **D** — дешёвый native lazy iframe/video.
3. **A** как расширение вкладки script (стратегия idle/interaction рядом с async/defer).
4. **B** для карт/ниже fold.
5. **C** точечно под YouTube/жёсткий TBT.

## Админка (UX)

- Вкладки: **Опции**, **Тэг link**, **Тэг script**.
- На вкладках link/script — справочники (rel и defer/async) по макетам; кнопки «Добавить url» / «Применить» — классы `tools-gps-btn` + `adm-btn-save` (зелёный градиент Bitrix).
- Кастомный CSS на `.tools-gps-filed` не должен перебивать фон `adm-btn-save` у кнопок сохранения.

## Осторожности

- Отложенный CSS может давать FOUC — проверять на копии.
- Нестандартные сниппеты Метрики/GTM (компонент, сторонний плагин) могут не попасть под regexp.
- Опция «устранить скрипты, блокирующие рендеринг» когда-то ломала JS на сайте — в истории коммитов отключали; включать только осознанно и проверять.
- **v1.3.3 (2026-10-04):** `eliminateScriptsThatBlockDisplay` переписан: **не** вешает `defer` на все `<script src>`. Allowlist (`jquery*.js`, `speed.min.js`) **переносится из `<head>`** в начало body-кластера (перед `/bitrix/js/` / `template_*.js`), sync-порядок сохранён — иначе ломаются Aspro `template_*.js` и inline `$()`. Опция снова в install + `ensureEliminateScriptsOption()`; на pro-case включена через `local/php_interface/scripts/enable_gps_eliminate_scripts.php`.
- **v1.3.5 (2026-10-04):** «Вырезать Яндекс метрику» переведена с regexp на `cutYandexMetrika` (счётчик + `/bitrix/js/yandex.metrika/script.js` + dataLayer/counters). Старые regexp не ловили модуль Bitrix `yandex.metrika`. **Композит:** при `Cache` nginx отдаёт `html_pages` без PHP → GPS не отрабатывает; в `_docker/nginx/.../composite.conf` добавлен skip для UA Lighthouse/PageSpeed. На проде нужен такой же bypass + ACTIVE опции. `RobotDetector` расширен (Chrome-Lighthouse, PageSpeed, PTST…).
- **v1.3.4 (2026-10-04):** UI: «Устранить скрипты…» — **heading** без select; подпункты **Общий JS** (jquery) и **Aspro Js** (`speed.min.js`), каждый со своим ACTIVE/LIMITATION. Вставка сразу после `<body>` в блок `<!--gps-rb-scripts-->` (раньше перенос перед core.js ломал mid-body `CheckTopMenuDotted()`). `OPTION_TYPE=heading` в админке без чекбокса/select.
- Модуль правит уже собранный HTML regex’ами — хрупко при нестандартной разметке.

## Возможные доработки

В рамках модели модуля (правка HTML в `OnEndBufferContent` + админ-переключатели). Не трогать сюда: WebP/AVIF, критический CSS из сборки, минификация, CDN, переписывание PHP-шаблона.

### Приоритетный пакет (высокий эффект, мало риска)

1. **`crossorigin` + `type` для preload шрифтов** — в справочнике уже есть, в генерации `<link>` — нет.
2. ~~**`fetchpriority="high"` на первое/LCP `<img>`**~~ — снято (v1.3.2): авто-эвристика ненадёжна; для LCP лучше точечный preload / правка шаблона. `decoding="async"` на остальные — оставлено.
3. **`loading="lazy"` для `<iframe>` / `<video>`**.
4. **Исключения для отложенного CSS** — список URL/regex «не трогать» (критический CSS), меньше FOUC.
5. **Фиксированный `href` для hints** — явный `preconnect`/`dns-prefetch` без обязательного совпадения URL в HTML.

Сделано: пресеты отложенной загрузки Метрика / GA (idle+interaction); Jivo — код закомментирован; decoding для img (fetchpriority-опция снята). См. ключ. решения выше.

### Средний приоритет (нужна аккуратность)

6. ~~**Безопасный «defer всем скриптам»**~~ — частично: v1.3.3 relocate allowlist (jquery/speed) из head; полный defer+whitelist пока не делали.
7. **Вырезание других пикселей** (VK, FB, TikTok, calltracking) — «для всех / только робот».
8. **Область действия по URL** — правила только для выбранных страниц.
9. **Лучший детект робота** — не только `Lighthouse` в UA (`PageSpeed`, `Chrome-Lighthouse`, PSI и т.п.).
10. **Speculation Rules / `modulepreload`** — современная замена `prerender` и preload для ESM.

### Админка / DX

11. Справочник на вкладке «Опции» (как у link/script).
12. Превью / dry-run — «что изменится в HTML» без включения на всех.
13. Свои regexp-правила вырезания в UI (сейчас только зашитые при установке).
14. В селекте `as` добавить **`document`** (уже в примерах prefetch).
15. ~~Скан / discovery скриптов~~ — сделано (v1.2.0): HTTP-скан + каталог hide/presets.
16. Тот же сканер для вкладки **link** (stylesheet/font → preload).

Рекомендуемый следующий шаг при старте работ: пункты **1, 3–4**.

## Учёт времени (работы по модулю)

| Дата | Задача | Минуты | Старт–конец (MSK) |
|------|--------|--------|-------------------|
| 24.08–31.08.2026 | Ремонт модуля (шаги 1–10) | 150 | 11:35 – 10:14 |

**Итого по модулю:** 150 мин

**По дням:** 24.08 — ~90; 25.08 — ~45; 31.08 — ~15

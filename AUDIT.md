# Аудит пакета `efureev/laravel-support-db`

**Дата:** 2026-09-11 · **Аудируемая версия:** v4.0.0 (`32e3c9a`) · **Целевая:** v5.0.0

**Окружение проверки:** PHP 8.5.10 (CLI), `laravel/framework` v13.13.0, `orchestra/testbench`
v11.1.0, `phpunit/phpunit` 13.1.14, `phpstan/phpstan` 2.2.1.

**Как проверялось.** Прочитаны все 37 файлов `src/`, весь `tests/`, конфиги, CI, Docker, `readme.md`,
`CHANGELOG.md`, `.meta.php`. Сигнатуры всех переопределений сверены с **фактически установленным**
`laravel/framework v13.13.0` в `vendor/`, а не с документацией. Прогнаны `php -l` по всем файлам,
PHPStan level 6 и серия smoke-тестов генерации SQL без БД. Дефекты в разделе 2 помечены
«**воспроизведено**» — это значит, что приведённый SQL получен запуском, а не выведен из чтения кода.

---

## Что это за документ

Аудит пакета от 2026-09-11. **Реализованные пункты из него удаляются** — здесь остаётся только
то, что ещё предстоит сделать. История закрытых дефектов живёт в `CHANGELOG.md` и в
регрессионных тестах `tests/Unit/`.

---

## Оглавление

1. [Резюме](#1-резюме)
2. [Дефекты](#2-дефекты)
3. [Что Laravel 13 уже умеет сам](#3-что-laravel-13-уже-умеет-сам)
4. [Документация](#4-документация)
5. [Тесты и инфраструктура](#5-тесты-и-инфраструктура)
6. [Модернизация под PHP 8.5](#6-модернизация-под-php-85)
7. [Производительность и консистентность API](#7-производительность-и-консистентность-api)
8. [План развития](#8-план-развития)

---

## 1. Резюме

**Совместимость с Laravel 13 формально не сломана.** Все классы пакета загружаются под
Laravel 13.13.0, ни одно переопределение не нарушает LSP (сигнатуры проверены рефлексией),
`php -l` проходит по всем файлам под PHP 8.5.10, deprecated-конструкций уровня языка
(implicit nullable и т. п.) нет. Подъём минимума PHP до 8.5 не требует ни одной правки в `src/`.

**Но есть две группы проблем.**

**Первое — дублирование того, что Laravel 13 уже умеет сам.** Примерно треть кода пакета
повторяет функциональность фреймворка (раздел 3), и один из таких дубликатов —
`ServiceProvider::registerConnectionServices()` — уже **разошёлся** с оригиналом и не
регистрирует два биндинга из семи. Это системный риск: пакет копирует методы фреймворка
вместо того, чтобы их расширять, и каждая такая копия — мина к очередному мажору Laravel.

**Второе — отсутствие страховки.** PHPStan стоит на level 1 (второй снизу из 11), **вообще
не запускается в CI** и при этом уже красный — три ошибки в `ServiceProvider.php`.
PHPCS не сканирует `tests/`, из-за чего там живёт нарушение PSR-4. Именно слабость этого
контура позволила четырнадцати дефектам дожить до v4.0.0; unit-слой на `toSql()` с тех пор
появился, но статический анализ так и не enforced.

**Что в пакете хорошо.** Архитектура разделения на traits грамматики (`GrammarTable`, `GrammarTypes`,
`GrammarIndexes`, `GrammarViews`, `CompressionModifier`) читаемая и расширяемая. Тесты используют
современный attribute-based PHPUnit 13 без единой deprecated-аннотации. Функциональное ядро —
partial-индексы, views, `CREATE TABLE LIKE/AS SELECT`, PG-типы — это реальная ценность, которой
в Laravel 13 нет и не предвидится.

---

## 2. Дефекты

Открытых не осталось. История закрытых — в `CHANGELOG.md`; регрессии на каждый лежат
в `tests/Unit/`, в том числе `TypeVarianceTest` на сужение типов и
`ConnectionTest::connectionServicesAreNotReimplemented` на копирование методов фреймворка.

---

## 3. Что Laravel 13 уже умеет сам

### 3.1. Проверено и решено не удалять

Всё, что действительно дублировало фреймворк, уже удалено. Ниже — кандидаты, которые при
проверке оказались обоснованными; они оставлены сознательно, чтобы к ним не возвращались.

| Кандидат | Почему остаётся |
|---|---|
| ~~`Builder::hasView()`~~ — **удалять не нужно** | Нативный `Schema\Builder::hasView()` (`vendor/.../Schema/Builder.php:194`) читает `pg_views` и **не видит materialized views**, поэтому переопределение пакета обосновано. Доработать в нём стоит лишь разбор `schema.view` через `parseSchemaAndTable()` |
| ~~`Builder::getViewDefinition()`~~ — **удалять не нужно** | Нативный `compileViews()` читает только `pg_views` (`relkind = 'v'`), а materialized views лежат в `pg_matviews` (`relkind = 'm'`) — множества непересекающиеся. Та же причина, что у `hasView()` |
| ~~`Blueprint::ginIndex()`~~ — **удалять не нужно** | Нативный аналог `$table->index($cols, $name, 'gin')` есть, но это не копия фреймворка, а однострочный шорткат над `indexCommand()`. Стоит лишь добавить ему проброс `$operatorClass`, который нативный `index()` умеет |
| ~~`Connection::updateAndReturn()` / `deleteAndReturn()`~~ — **удалять не нужно** | Живой публичный API: 4 вызова в `src/`, 11 ассертов в тестах, `.meta.php` и readme. Схлопнуть можно разве что `affectingStatementArray()`, у которого нет внешних вызовов |

### 3.2. Что Laravel 13 добавил, а пакет ещё не использует

Стоит упомянуть в readme, чтобы пользователи не изобретали это поверх пакета:

- `IndexDefinition`: `nullsNotDistinct()`, `deferrable()`, `initiallyImmediate()`, `online()`
  (`CONCURRENTLY`), `algorithm()` — всё нативно в `compileUnique()`/`compileIndex()`
  (`PostgresGrammar.php:329-397`).
- `Blueprint::rawColumn($column, $definition)` (`Blueprint.php:1728`) — покрывает произвольный
  PG-тип без собственного `type*`-метода.
- `vector()`/`vectorIndex()` (`Blueprint.php:1535,713`), `tsvector()` (1548),
  `Blueprint::comment()` — комментарий к таблице (1739), `geometry($col, 'point'|'path', $srid)` (1498).
- `compileDropAllDomains()`, `compileTypes()`, `getCurrentSchemaListing()`.

### 3.3. Что остаётся уникальной ценностью

В Laravel 13 **нет** и, судя по roadmap фреймворка, не появится:

partial и unique-partial индексы с `WHERE` · views, включая materialized ·
`CREATE TABLE ... (LIKE ...)` · `CREATE TABLE ... AS SELECT` / `AS TABLE` ·
`DROP TABLE ... CASCADE` · `CREATE/DROP EXTENSION` · column `COMPRESSION` ·
`UPDATE/DELETE ... RETURNING` · шорткаты PG-типов (`bit`, `numeric`, `xml`, `cidr`,
`daterange`, `tsrange`, массивы, геометрия).

Это ядро, ради которого пакет существует. Оно должно остаться и быть доведено до ума.

---

## 4. Документация

Закрыто: неработающие примеры readme, пустой `## Description`, секция Requirements,
`.meta.php` (миксины для `Query\Builder` и `ColumnDefinition`, лишние `@method` убраны),
бэкфилл семи пропущенных версий CHANGELOG с датами и compare-ссылками, регулярка дат
в линтере, которая ломалась с 2030 года.

Осталось:

- Оглавление readme не перечисляет подсекции `#### Create views` / `#### Dropping views`
  и подобные — только их родителей.
- Мелочи разметки: голый URL на `readme.md` в секции Partial indexes (MD034), `-----`
  вместо `---` как тематический разделитель, `use \Php\Support\...` с ведущим слешем
  в примерах, висящие пробелы в конце строк.
- Английский: «store a list of string» → strings; «Creating will be without a data.»;
  «Copy only columns and a data.»; «recently added `lz4`» — lz4 появился в PG 14 в 2021.

---

## 5. Тесты и инфраструктура

### 5.1. Что сделано хорошо

93 теста / 434 ассерта, 0 падений. Полностью современный PHPUnit 13: 59 атрибутов `#[Test]`,
ноль методов `test*`, ноль `/** @test */`. Data-провайдеры — атрибутами, все `public static`,
возвращают `Generator`. Исчерпывающий поиск по `assertObjectHasAttribute`, `withConsecutive`,
`@dataProvider`, `expectDeprecation`, `prophesize` и прочим удалённым в PHPUnit 12/13 API дал
**ноль совпадений**. Здесь ничего чинить не надо.

### 5.2. Структурные пробелы

**Что не покрыто вообще:** `CompressionModifier::compileChange()`; materialized views (ни одного
вхождения `materialize` в `tests/`); ветка `bindValues()` для `ATTR_EMULATE_PREPARES`;
`$blueprint->temporary`; параметр `$algorithm`; `hasIndex($index, 'primary')`;
`wrapValue()` для float/bool/null; пустой `$cols` в `compileReturns()`.

**Тесты, которые ничего не проверяют:** `tests/Functional/CompressionTest.php:17-29` — единственный
тест сжатия, ассертит `Schema::hasTable()` и больше ничего; `pg_attribute.attcompression`
не проверяется никогда. `tests/Functional/Schemas/CreateIndexTest.php:51-58` и `:63-69` —
побайтово одинаковые тела под разными `#[Group]`, имена групп обещают различие по `search_path`,
которого нет.

### 5.3. Хрупкость

- **Нет транзакционной изоляции.** Ни `RefreshDatabase`, ни `DatabaseTransactions`.
  Изоляция держится на `artisan db:wipe` в `setUp()` (`AbstractTestCase.php:78`), результат
  которого не проверяется. `AbstractTestCase` не определяет `tearDown()` вообще.
- **`executionOrder="random"`** (`phpunit.xml:3`) при общей нетранзакционной БД и глобальных
  мутациях (создание/удаление `uuid-ossp` в `BuilderTest.php:19,31,41,47`) — порядко-зависимо
  по построению.
- **Позиционный доступ к неупорядоченной выборке.** `tests/Helpers/IndexAssertions.php:72` —
  `SELECT * FROM pg_indexes WHERE tablename = ?` **без `ORDER BY`**, а
  `CreateTableLikeTest.php:56-63` индексирует результат как `$srcList[0]`, `[1]`, `[2]`.
  PostgreSQL порядок здесь не гарантирует.
- **Точные строки PG-деparse.** `CreateIndexTest.php:56,68` с зашитым префиксом `public.`,
  при том что строка 86 в том же файле использует терпимую регулярку `(public.)?`.
  `CreateViewTest.php:32,46,56,76` зависят от pretty-printer'а PostgreSQL.
- **`tearDown`, который сам падает.** `CreateViewTest.php:18-23` делает
  `Schema::dropIfExists('test_table')` без cascade: если тест прервётся до `dropView`, зависимая
  view переживёт, и `tearDown` упадёт, замаскировав исходную ошибку. Соседние
  `CreateTableFromSelectTest.php:181` и `CreateTableLikeTest.php:96` делают это правильно —
  через `dropIfExistsCascade`.
- `tests/bootstrap.php` — мёртвый файл: `phpunit.xml:2` подключает `vendor/autoload.php`.
- `tests/Helpers/Helper.php:9-20` возвращает `null` для неизвестного класса, после чего
  `ColumnAssertions.php:46-48` вызывает `->phpType()` на `null` → фатал вместо читаемого провала.
- `tests/Models/TestModel.php:16` задаёт `$keyType = 'string'` для UUID-ключа, но не ставит
  `public $incrementing = false`.
- `tests/database/migrations/2021_11_15_000000_create_test_table.php:7` — именованный класс
  миграции в стиле до Laravel 9, `up()`/`down()` без типов.

### 5.4. Статический анализ

PHPStan стоит на level 5 с larastan, покрывает `src` и `tests`, запускается в CI и зелёный.
Исключения прописаны точечно и только для `tests/` — это поверхность расширения пакета
(фасад `Schema`, магия `Fluent`), которую анализатор не видит; `src/` разбирается без единого
исключения.

Осталось: **level 6+** добавляет ~90 находок, почти все `missingType.iterableValue` на сигнатурах,
форму которых диктует фреймворк. Отдельный проход.

### 5.5. PHPCS

`.phpcs.xml`:

- `Generic.Formatting.MultipleStatementAlignment` (строки 64-69, `error=true`) требует
  выравнивания `=` по вертикали — этого нет ни в PSR-12, ни в PER-CS 2.0, и php-cs-fixer
  с большинством IDE-форматтеров активно это ломают обратно.
- `PEAR.Functions.FunctionCallSignature` с `allowMultipleArguments=false` (строки 57-61)
  требует один аргумент на строку в любом многострочном вызове — прямое противоречие PER-CS 2.0.
- `Generic.PHP.ForbiddenFunctions` (строка 78) запрещает `is_null` с заменой `"null"` —
  сообщение «use null instead of is_null» бессмысленно, должно быть `=== null`.
- `Generic.ControlStructures.InlineControlStructure` объявлен дважды (строки 22 и 30-34).
- Нет `basepath`, `cache`, `parallel`, `colors`, `<config name="php_version">`.

### 5.6. CI

`.github/workflows/ci.yml` — 4 job'а, матрица `setup: [basic, lowest, stable] × php: [8.5]`.
Job `lint` помимо PHPCS гоняет unit-сьют (без БД).

- **Нет coverage.** `phpunit.xml:6-13` настраивает clover/html/text/xml, Dockerfile ставит pcov,
  `composer phpunit-cover` определён — но все job'ы ставят `coverage: none`, а `composer test`
  запускает `phpunit --no-coverage`. CodeClimate убран в 4.0.0 без замены.
- **`basic` и `stable` функционально идентичны:** `composer update --prefer-dist` против
  `composer update --prefer-dist --prefer-stable`; `minimum-stability` в `composer.json`
  не задан, значит stable и так дефолт. 1 из 3 job'ов — чистое дублирование.
- **`lowest` + PHP 8.5 скорее всего не резолвится:** `--prefer-lowest` тянет
  `orchestra/testbench` 11.0.0, чьё ограничение по PHP предшествует 8.5.
- **Одна версия PostgreSQL** (`postgres:18`) при том, что ассерты завязаны на PG-специфичный
  deparse. Регрессии на PG 15-17 будут не видны.
- PHPCS выполняется **7 раз**: один раз отдельным job'ом и ещё 6 внутри `composer test`.
- **Нет блока `permissions`.** Job `release` вызывает `softprops/action-gh-release@v2`
  с `GITHUB_TOKEN`; при read-only дефолте организации релиз не создастся. Нужен `contents: write`.
- **`release` срабатывает только на теги, оканчивающиеся на `.0`** (строка 135) — все патч-релизы
  (`v4.0.1`) молча остаются без GitHub-релиза.
- `on: [push, pull_request]` без `concurrency`-группы → PR из того же репозитория гоняет
  всю матрицу дважды.
- Ключ кэша по `hashFiles('**/composer.json')` при `composer update` и незакоммиченном
  `composer.lock` даёт невоспроизводимую резолюцию зависимостей.

### 5.7. Docker

`docker-compose.yml:6-7` монтирует `.:/app`, **затеняя собранный в образе `/app/vendor`**.
Поскольку `vendor` исключён в `.dockerignore:3`, на чистом клоне без локального
`composer install` bind mount подставит пустой `/app/vendor`, и `composer test` упадёт
на «vendor/bin/phpcs not found». Сейчас работает только потому, что на машине разработчика
`vendor/` собран локально.

Прочее:

- `.dockerignore:8` исключает `composer.lock` → `composer install` в образе деградирует
  до полного `update`. Сборки невоспроизводимы и не совпадают с локальной резолюцией.
- `.docker/Dockerfile:30` — `COPY . /app` **до** `composer install` на строке 32:
  любая правка исходника инвалидирует слой зависимостей.
- База образа `php:8.4-cli-alpine` — только 8.4, без `ARG PHP_VERSION`, тогда как CI
  тестирует 8.4 и 8.5.
- `docker-compose.yml:25-26` публикует 5432 на хост — конфликт с локальным PostgreSQL.
  Тестовым контейнерам публикация не нужна.
- `postgres:18-alpine` в compose против `postgres:18` (Debian) в CI: разные базовые образы,
  а значит потенциально разные locale/collation — ровно то, на чём завязаны строковые
  сравнения в `CreatePartialIndexTest`.
- Запуск от root без `COMPOSER_ALLOW_SUPERUSER=1`.

### 5.8. Зависимости и безопасность

`composer audit` чист и запускается в CI. Уязвимый `squizlabs/php_codesniffer` поднят
до `^3.13.6`. Остальные advisory приходили транзитивно через `orchestra/testbench` в dev
и на потребителей библиотеки не влияли.

### 5.9. Гигиена репозитория

- `.gitignore` в состоянии `MM`: в рабочем дереве игнорируется `phpunit.xml.dist`,
  в индексе — `phpunit.xml`. При этом `phpunit.xml` **отслеживается** git'ом, то есть
  коммит в текущем виде создаст отслеживаемый-но-игнорируемый файл. Правильная схема —
  `phpunit.xml.dist` в репозитории, `phpunit.xml` в `.gitignore`.
- `phpunit.xml:28-38` смешивает `<server>` (для `DB_CONNECTION`, `APP_ENV`, `APP_KEY`)
  и `<env>` (для реквизитов БД). Работает, но как соглашение — ловушка.
- `.dockerignore` не исключает `.github`, `readme.md`, `CHANGELOG.md` — мёртвый вес в образе.

---

## 6. Модернизация под PHP 8.5

**Требования vs реальность.** Осталось не использовано: `readonly`, promoted constructor
property (ни один класс в `src/` не объявляет конструктор), `never`, intersection type,
typed class constant, `final`, asymmetric visibility, property hook.

**Что используется современного:** `match(true)` (`Connection.php:48`), стрелочные функции,
union и nullable-типы, `static` как возвращаемый тип, вариадики, `match` в компиляторах,
backed enum для типов колонок, `#[\Override]` на всех 15 переопределениях, `declare(strict_types=1)`
во всех файлах.

### Конкретные точки приложения

| Замена | Где |
|---|---|
| `switch (true)` → `match (true)` | `src/Schema/Postgres/Blueprint.php:47` — единственный `switch` в пакете, и у него нет `default`, из-за чего `$defaultExpression` остаётся неопределённой и прикрывается `?? null` на строке 67 |
| Типы параметров | `Builder.php:62,75` (`$view`); `Blueprint.php:71,288,293,298` |

**Про сам минимум PHP.** Laravel 13 требует `php: ^8.3`. Подъём минимума пакета до `>=8.5` —
собственное решение, а не требование фреймворка, и оно отсекает большинство пользователей
Laravel 13. Технически ничто этого не требует: `php -l` под 8.5.10 проходит по всем файлам,
ни одна конструкция 8.4/8.5 не используется. Компромиссный вариант `^8.4 || ^8.5` дал бы
тот же уровень современности при вдвое большем охвате. Решение принято в пользу `>=8.5`
осознанно и зафиксировано здесь как сознательный trade-off.

---

## 7. Производительность и консистентность API

Закрыто: строки `RETURNING` идут через `Connection::prepared()`, то есть уважают настроенный
fetch mode и диспатчат `StatementPrepared`; регистр SQL выровнен под нижний; `compileCreate()`
делегирует родителю; схлопнуты дубли `createView`/`createViewOrReplace`, `partial`/`uniquePartial`,
`compileCreateView`/`compileCreateViewOrReplace`, `PartialBuilder`/`UniquePartialBuilder`;
текущая схема кэшируется вместо `show search_path` на каждый вызов.

Осталось:

- `Builder::createExtension()` / `createExtensionIfNotExists()` и
  `Connection::updateAndReturn()` / `deleteAndReturn()` — по две строки каждая пара,
  схлопывание изменит публичный API ради малого выигрыша.

---

## 8. План развития

### v5.0.0 — следующий релиз (BC break, без deprecation-периода)

**Блок 1. Документация — остаток.** Подсекции в оглавлении readme, мелочи разметки
и английского (см. §4).

### v5.1+ — функциональное развитие

- `CONCURRENTLY` для partial-индексов — Laravel 13 уже умеет `online()` для обычных.
- `nullsNotDistinct` для partial-unique — по аналогии с нативным.
- `orWhere*` в `WhereBuilderTrait`: сейчас есть `$boolean`-параметр, но нет удобных обёрток.

### Инфраструктура

- PHPStan level 6+ (аннотации типов массивов).

- Матрица PostgreSQL 15/16/17/18 — ассерты завязаны на PG-специфичный deparse.
- Coverage-репорт в CI; `permissions: contents: write` для `release`;
  `release` на любой `v*`-тег, а не только `*.0`; `concurrency`-группа;
  схлопнуть дублирующие `basic`/`stable`.
- `phpunit.xml.dist` в репозиторий, `phpunit.xml` — в `.gitignore`; развести расхождение
  индекса и рабочего дерева.
- Docker: анонимный volume на `/app/vendor` (или `composer install` в entrypoint),
  `composer.lock` внутрь образа, `COPY composer.json` перед `composer install`,
  `ARG PHP_VERSION`, убрать публикацию порта 5432.
- Обновить регулярку дат в `.github/workflows/lint/rules/changelog.js:16` — сейчас
  ломается с 2030 года.

### Стратегическая рекомендация

Главный системный риск пакета — **копирование методов фреймворка вместо расширения**.
`registerConnectionServices()`, `createBlueprint()`, `affectingStatement()`, `compileCreate()`,
`removeLeadingBoolean()` скопированы целиком; один из них уже разошёлся с оригиналом.
Каждое такое место — мина, которая сработает на очередном мажоре Laravel.

Правило на будущее: **не копировать — вызывать `parent::` и дописывать.** Образец есть
в самом пакете: `GrammarTable::compileDropIfExists()`. Плюс `#[\Override]` на всех
переопределениях, чтобы PHP сам ловил исчезнувшие родительские методы.

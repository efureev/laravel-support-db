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

Закрыто: `pg_indexes` читается с `ORDER BY`, а позиционный доступ к нему заменён сравнением
множеств (он ассертил порядок, которого PostgreSQL не обещает — правка это и вскрыла);
точные строки PG-деparse с зашитым `public.` заменены терпимыми регулярками во всех четырёх
местах; `tearDown` в `CreateViewTest` делает cascade и больше не маскирует исходную ошибку
зависимой view; результат `db:wipe` проверяется; `TestModel` объявляет `$incrementing = false`
для UUID-ключа; мёртвый `tests/bootstrap.php` и мёртвое исключение под него в `.phpcs.xml`
удалены.

Осталось:

- **Нет транзакционной изоляции.** Держится на `db:wipe` в `setUp()`. Перевод на
  `DatabaseTransactions` упрётся в `refreshMaterializedView(concurrently: true)`, который
  PostgreSQL запрещает внутри транзакционного блока, — потребуется исключение для этого теста.
- **`executionOrder="random"`** при общей нетранзакционной БД и глобальных мутациях
  (создание/удаление `uuid-ossp` в `BuilderTest`).

### 5.4. Статический анализ

PHPStan стоит на **level 6** с larastan, покрывает `src` и `tests`, запускается в CI и зелёный.
Исключения прописаны точечно и только для `tests/` — это поверхность расширения пакета
(фасад `Schema`, магия `Fluent`), которую анализатор не видит; `src/` разбирается без единого
исключения.

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

### 5.6. CI и Docker

Закрыто: матрица PostgreSQL 13–18 (все прогнаны локально перед тем, как попасть в конфиг),
PHPStan и `composer audit` в job `lint`, отдельный job `coverage` с выгрузкой отчёта артефактом,
`concurrency`-группа, `permissions: contents: write` и релиз на любой `v*`-тег вместо только
`v*.0`, `composer validate --strict`, дубль `stable` убран. В Docker: bind mount больше не затирает
собранный `vendor` (анонимный том), `composer.lock` попадает в образ, зависимости ставятся до
копирования исходников, версии PHP и PostgreSQL параметризованы, порт PG не публикуется на хост.

Осталось:

- Ключ кэша Composer построен на `hashFiles('**/composer.json')` при `composer update` — точность
  кэша невысока, но `composer.lock` для библиотеки не коммитится, так что это осознанный компромисс.

### 5.7. Зависимости и безопасность

`composer audit` чист и запускается в CI. Уязвимый `squizlabs/php_codesniffer` поднят
до `^3.13.6`. Остальные advisory приходили транзитивно через `orchestra/testbench` в dev
и на потребителей библиотеки не влияли.

### 5.8. Гигиена репозитория

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

- Транзакционная изоляция тестов вместо `db:wipe` в `setUp()`. Упрётся в
  `refreshMaterializedView(concurrently: true)`, который PostgreSQL запрещает внутри
  транзакционного блока, — потребуется исключение для этого теста.
- Ключ кэша Composer в CI построен на `hashFiles('**/composer.json')` при `composer update`.

### Стратегическая рекомендация

Главный системный риск пакета — **копирование методов фреймворка вместо расширения**.
`registerConnectionServices()`, `createBlueprint()`, `affectingStatement()`, `compileCreate()`,
`removeLeadingBoolean()` скопированы целиком; один из них уже разошёлся с оригиналом.
Каждое такое место — мина, которая сработает на очередном мажоре Laravel.

Правило на будущее: **не копировать — вызывать `parent::` и дописывать.** Образец есть
в самом пакете: `GrammarTable::compileDropIfExists()`. Плюс `#[\Override]` на всех
переопределениях, чтобы PHP сам ловил исчезнувшие родительские методы.

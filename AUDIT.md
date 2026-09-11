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

## Статус исправлений

Документ ниже описывает состояние на момент аудита (2026-09-11). Отдельными итерациями закрыты
**все дефекты D1–D14**; раздел 2 целиком читается как история, а не как текущее состояние кода.

| Дефект | Статус | Чем закрыт |
|---|---|---|
| D1 `addModifier()` теряет `Collate` | ✅ исправлен | `array_unshift()` + guard от дублей |
| D2 `compression()` + `change()` даёт невалидный SQL | ✅ исправлен | `modifyCompression()` молчит при `$column->change` |
| D3 deprecated `getChangedColumns()` | ✅ исправлен | `$command->column`; заодно ушло дублирование N² |
| D4 нет экранирования значений | ✅ исправлен | удвоение апострофа + честные типы вместо `(int)` |
| D5 `whereRaw()` падает на `%` | ✅ исправлен | пошаговая подстановка вместо `sprintf()` |
| D6 схлопывание двойных пробелов | ✅ исправлен | `array_filter` + `implode` вместо `str_replace` |
| D7 partial-индексы игнорируют prefix | ✅ исправлен | `wrapTable()` / `columnize()` / `wrap()` |
| D8 висячий `WHERE` в unique-partial | ✅ исправлен | `method_exists`-развилка в `__call` + guard в `compileUniquePartial` |
| D9 materialized views только создаются | ✅ исправлен | `drop materialized view`, union с `pg_matviews`, `LogicException` на `or replace` |
| D14 `compression 1` | ✅ исправлен | дефолт `pglz` + валидация идентификатора |
| D10 `hasIndex()` не то соединение | ✅ закрыт | метод **удалён** как избыточный, вызовы переведены на нативный `Schema::hasIndex()` |
| D11 массив в `recordsHaveBeenModified()` | ✅ исправлен | передаётся `!== []` |
| D12 мёртвые `@method ginIndex`/`algorithm` | ✅ закрыт | phpdoc удалён, вызовы бросают `BadMethodCallException` |
| D13 мёртвый `$algorithm` | ✅ исправлен | читается компиляторами, печатается как `using <method>` |

Попутно закрыты два пункта из §2.3: переобъявление `Fluent::$attributes` в `WhereBuilderTrait`
и `where()`, не принимавший числа. Второе всплыло как реальная ошибка: тест
`CreatePartialUniqueIndexTest` передаёт `int`, и это работало лишь потому, что
`call_user_func_array()` внутри `UniqueBuilder::__call` обходил `strict_types` вызывающего файла.

При закрытии D13 выяснилось, что дыра шире описанной в §2.2: позиционный `$algorithm` не
использовался нигде, зато флюентная форма `uniquePartial(...)->algorithm(...)` теряла алгоритм
всякий раз, когда задан предикат. Оба случая закрыты одним чтением атрибута.

Добавлен слой `tests/Unit/` (47 тестов, ~70 мс, **без БД**) — каждый критичный дефект закрыт
регрессионным тестом на `toSql()`. Плюс `tests/Functional/Schemas/MaterializedViewTest.php`
против живого PostgreSQL.

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

**Но есть три группы проблем.**

**Первая — дефекты, которые тихо портят SQL.** Их 14, и это не стилистика: `->collation()`
перестаёт работать у всех пользователей пакета; связка `->compression()` + `->change()` генерирует
синтаксически невалидный PostgreSQL; значения в partial-индексах подставляются без экранирования;
partial-индексы игнорируют table prefix. Самое опасное здесь — то, что все эти дефекты **молчаливые**:
либо SQL исполняется и делает не то, либо падает на этапе миграции, где ошибку легко списать
на окружение. Ни один из них не покрыт тестами.

**Вторая — устаревание относительно Laravel 13.** `CompressionModifier` опирается на
`Blueprint::getChangedColumns()`, помеченный в Laravel 13 как `@deprecated Will be removed in a
future Laravel version`. Это единственный настоящий upgrade-blocker: он сломается на следующем
мажоре фреймворка. Плюс примерно треть кода пакета дублирует то, что Laravel 13 уже делает сам
(раздел 3), и один из таких дубликатов — `ServiceProvider::registerConnectionServices()` — уже
**разошёлся** с оригиналом и не регистрирует два биндинга из семи.

**Третья — отсутствие страховки.** PHPStan стоит на level 1 (второй снизу из 11) и **вообще
не запускается в CI**. PHPCS не сканирует `tests/`. Ни одного unit-теста: вся генерация SQL
проверяется только сквозь живой PostgreSQL, поэтому логику компиляторов нельзя протестировать
в отрыве от БД — и она не тестируется. Именно поэтому 14 дефектов из раздела 2 дожили до v4.0.0.

**Что в пакете хорошо.** Архитектура разделения на traits грамматики (`GrammarTable`, `GrammarTypes`,
`GrammarIndexes`, `GrammarViews`, `CompressionModifier`) читаемая и расширяемая. Тесты используют
современный attribute-based PHPUnit 13 без единой deprecated-аннотации. Функциональное ядро —
partial-индексы, views, `CREATE TABLE LIKE/AS SELECT`, PG-типы — это реальная ценность, которой
в Laravel 13 нет и не предвидится.

---

## 2. Дефекты

### 2.1. Критичные

#### D1. `addModifier()` молча удаляет модификатор `Collate` — `->collation()` не работает

`src/Schema/Postgres/Grammar.php:30`

```php
$this->modifiers = [$value] + $this->modifiers;
```

`+` для массивов — это объединение **по ключам**, а не конкатенация. Родительский
`$modifiers` (`vendor/laravel/framework/src/Illuminate/Database/Schema/Grammars/PostgresGrammar.php:25`)
— список `['Collate','Nullable','Default','VirtualAs','StoredAs','GeneratedAs','Increment']`.
Значит `['Compression'] + [...]` **затирает** элемент с ключом `0`, а не сдвигает его.

**Воспроизведено:**

```
BEFORE: ["Collate","Nullable","Default","VirtualAs","StoredAs","GeneratedAs","Increment"]
AFTER : ["Compression","Nullable","Default","VirtualAs","StoredAs","GeneratedAs","Increment"]
LOST  : ["Collate"]
```

Следствие — `$table->string('a')->collation('C')` компилируется без `collate`:

```sql
-- с грамматикой пакета
create table "t" ("a" varchar(255) not null)
-- без addModifier (чистый Laravel)
create table "t" ("a" varchar(255) collate "C" not null)
```

**Область поражения — все пользователи пакета без исключения:** `addModifier('Compression')`
вызывается безусловно в `Connection::getDefaultSchemaGrammar()` (`src/Schema/Postgres/Connection.php:16`).

Путь `compileChange` не задет: Laravel вызывает `modifyCollate()` там явно, в обход `$this->modifiers`
(`PostgresGrammar.php:286`).

**Исправление:** `array_unshift()` либо `array_merge([$value], $this->modifiers)`, плюс защита
от дублей — метод публичный и может быть вызван повторно.

---

#### D2. `->compression()` вместе с `->change()` генерирует неисполнимый SQL

`src/Schema/Postgres/Grammar/CompressionModifier.php:13-29`

Два независимых дефекта в одном методе.

**(а) Невалидный синтаксис.** `'Compression'` лежит в `$this->modifiers`, поэтому родительский
`compileChange()` (`PostgresGrammar.php:288-298`) прогоняет по нему цикл и вызывает
`modifyCompression()`, чей вывод ` compression lz4` префиксуется как `alter column "a"`.
Получается `alter column "a"  compression lz4` — такой конструкции в PostgreSQL нет.

**(б) Дублирование N².** В Laravel 11+ каждая изменённая колонка порождает **свою** команду `change`
(`vendor/.../Schema/Blueprint.php:202`), то есть `compileChange()` вызывается по разу на колонку.
Но цикл на строке 17 проходит по **всем** изменённым колонкам при каждом вызове.

**Воспроизведено** (две колонки с `->compression()->change()`):

```
[0] alter table "t" alter column "a" type text, alter column "a"  compression lz4, ...   ← невалидно
[1] ALTER TABLE "t" ALTER "a" SET COMPRESSION "lz4"
[2] ALTER TABLE "t" ALTER "b" SET COMPRESSION "pglz"
[3] alter table "t" alter column "b" type text, alter column "b"  compression pglz, ...  ← невалидно
[4] ALTER TABLE "t" ALTER "a" SET COMPRESSION "lz4"                                      ← дубль [1]
[5] ALTER TABLE "t" ALTER "b" SET COMPRESSION "pglz"                                     ← дубль [2]
```

Миграция падает на statement `[0]`. Путь не покрыт тестами: `tests/Functional/CompressionTest.php`
только создаёт таблицу и проверяет `hasTable()`, а вариант с `lz4` закомментирован на строке 23.

**Исправление:** исключить `Compression` из цикла модификаторов на пути `change` (как Laravel делает
с `Collate`), использовать `$command->column` вместо обхода `getChangedColumns()`.

---

#### D3. Опора на deprecated-API Laravel 13 — главный upgrade-blocker

`src/Schema/Postgres/Grammar/CompressionModifier.php:17` вызывает `$blueprint->getChangedColumns()`.

В Laravel 13 метод помечен (`vendor/.../Schema/Blueprint.php:1985`):

```php
/**
 * @deprecated Will be removed in a future Laravel version.
 */
public function getChangedColumns()
```

Это единственная зависимость пакета от API, о котором фреймворк явно заявил об удалении.
Заменяется на `$command->column` — что заодно чинит D2(б).

---

#### D4. Отсутствие экранирования значений в partial-индексах

`src/Schema/Postgres/Compilers/WheresBuilder.php:116-122`

```php
protected static function wrapValue($value)
{
    if (is_string($value)) {
        return "'{$value}'";
    }
    return (int)$value;
}
```

Строка подставляется в SQL как есть. `$grammar` в этот момент доступен, но ни
`Grammar::escape()`, ни `quoteString()` не используются.

**Воспроизведено:**

```php
$table->partial('a')->where('a', '=', "x'); drop table users; --");
```
```sql
CREATE INDEX t_a_partial ON t (a) WHERE ("a" = 'x'); drop table users; --')
```

Контекст — DDL миграций, где значения обычно пишет разработчик, а не пользователь, поэтому
как вектор атаки это severity medium. Но как **дефект корректности** — высокий: любое легитимное
значение с апострофом (`O'Brien`, `it's`) ломает миграцию.

Вторая половина проблемы — ветка `return (int)$value`: float `3.14` превращается в `3`,
`null` → `0`, `DateTimeInterface` → `TypeError`, backed enum → `0`. Всё молча.

---

#### D5. `whereRaw()` падает на символе `%` в SQL

`src/Schema/Postgres/Compilers/WheresBuilder.php:13-22`

Метод превращает сырой SQL в формат для `sprintf`, заменяя `?` на `%s`. Любой `%`, уже
присутствующий в SQL, интерпретируется как спецификатор формата.

**Воспроизведено:**

```php
$table->partial('a')->whereRaw("name like 'a%b'");
// ArgumentCountError: 2 arguments are required, 1 given
```

То есть `LIKE`-паттерн — самый естественный кейс для partial-индекса — приводит к фатальной ошибке.

---

#### D6. Двойные пробелы в значениях по умолчанию схлопываются

`src/Schema/Postgres/Compilers/CreateCompiler.php:38`

```php
return str_replace('  ', ' ', trim($compiledCommand));
```

Косметический хак для чистки собственной форматной строки, применённый ко **всему** SQL.

**Воспроизведено:**

```php
$table->string('weird')->default('hello  world');
// → "weird" varchar(255) not null default 'hello world'
```

Значение в БД будет отличаться от заданного. То же произойдёт с любым `fromSelect()`,
содержащим двойной пробел.

**Исправление:** убрать `str_replace` совсем, собрав формат через `array_filter` + `implode`.

---

#### D7. Partial-индексы игнорируют table prefix и не квотируют идентификаторы

`src/Schema/Postgres/Compilers/PartialCompiler.php:21,27,35` ·
`src/Schema/Postgres/Compilers/UniqueCompiler.php:27,28`

Оба компилятора берут `$blueprint->getTable()` и `implode(',', $columns)` напрямую, вместо
`$grammar->wrapTable($blueprint)` и `$grammar->columnize($columns)`.

**Воспроизведено** при `'prefix' => 'pref_'`:

```sql
-- выдаёт
CREATE INDEX t_a_partial ON users (name) WHERE ...
-- должно быть
create index "pref_t_a_partial" on "pref_users" ("name") where ...
```

Индекс создаётся на **несуществующей** таблице → миграция падает. Плюс колонка с зарезервированным
именем (`order`, `user`) сломает SQL.

**Почему не ловится тестами:** `tests/AbstractTestCase.php:41` задаёт `'prefix' => ''`.

---

#### D8. Висячий `WHERE` в unique-partial индексе

`src/Schema/Postgres/Builders/Indexes/Unique/UniqueBuilder.php:11-16` ·
`src/Schema/Postgres/Compilers/UniqueCompiler.php:24`

`UniqueBuilder::__call()` полностью подменяет `Fluent::__call()`: **любой** вызов метода создаёт
новый `UniquePartialBuilder` и записывает его в `constraints`. Для не-where методов это означает
объект с нулём условий, который `GrammarIndexes::compileUniquePartial()` всё равно отправляет
в `UniqueCompiler` — а тот безусловно печатает `WHERE`.

**Воспроизведено:**

```php
$table->uniquePartial('email')->algorithm('btree');
// → CREATE UNIQUE INDEX t_email_unique ON t (email) WHERE
```

`PartialCompiler` от этого защищён (`PartialCompiler.php:23`), `UniqueCompiler` — нет.

---

#### D9. Materialized views: создаются, но не удаляются и не находятся

`src/Schema/Postgres/Grammar/GrammarViews.php:31-49,51-54`

Три связанные проблемы:

1. `compileCreateViewOrReplace()` при `$materialize = true` собирает
   `CREATE OR REPLACE MATERIALIZED VIEW` — **такого синтаксиса в PostgreSQL не существует**.
   Воспроизведено: `create or replace materialized view "v" as select 1`.
2. `compileDropView()` (строка 53) всегда печатает `drop view`. `DROP VIEW` на materialized view
   в PostgreSQL — ошибка. Созданное пакетом удалить средствами пакета невозможно.
3. `Builder::hasView()` (`src/Schema/Postgres/Builder.php:62`) опрашивает `information_schema.views`,
   где materialized views **отсутствуют** по стандарту → всегда `false`.

При этом `createView($v, $sql, materialize: true)` документирован в `readme.md:189`.
Тестов на materialized views нет ни одного (`grep -r materialize tests/` → пусто).

---

### 2.2. Важные

#### D10. `hasIndex()` обращается не к тому соединению

`src/Schema/Postgres/Blueprint.php:206-209`

```php
return Schema::hasIndex($this->getTable(), $index, $type);
```

Фасад `Schema` резолвит **соединение по умолчанию**. Внутри
`Schema::connection('replica')->table(...)` метод молча опросит основную БД.
Должно быть `$this->connection->getSchemaBuilder()->hasIndex(...)`.

#### D11. В `recordsHaveBeenModified()` передаётся массив вместо bool

`src/Schema/Postgres/Connection.php:88-90`

Laravel хранит значение как есть (`vendor/.../Connection.php:1229`) в свойство,
объявленное `protected $recordsModified = false`. После `updateAndReturn()` там окажется массив,
и проверка sticky-соединения на `Connection.php:1307` начнёт работать на типе, которого не ожидает.
Нужно `count($list) > 0`.

#### D12. Документированные, но не работающие модификаторы колонок

`src/Schema/Definitions/ColumnDefinition.php:11-12`

```php
 * @method $this ginIndex(string $indexName = null) Add an index with GIN algo
 * @method $this algorithm(string $algo) Add an index with custom algorithm
```

Ни один компилятор не читает атрибуты `ginIndex` и `algorithm` с колонки
(проверено против `Blueprint::addFluentIndexes()`, где список — `primary, unique, index,
fulltext, fullText, spatialIndex, vectorIndex`). `$table->string('x')->ginIndex()` не делает **ничего**,
но IDE предлагает этот метод.

#### D13. Мёртвый параметр `$algorithm`

`src/Schema/Postgres/Blueprint.php:255,275`

Третий аргумент `partial()` и `uniquePartial()` сохраняется во Fluent и **никогда не читается**
ни `PartialCompiler`, ни `UniqueCompiler`. `->partial($cols, $name, 'gin')` молча игнорирует `'gin'`.
При этом `.meta.php:28-29` его рекламирует.

#### D14. `compression()` без аргумента даёт `compression 1`

`src/Schema/Definitions/ColumnDefinition.php:10` документирует дефолт `'pglz'`, которого в коде нет.
`Fluent::__call()` без аргументов присваивает `true`.

**Воспроизведено:** `$table->string('b')->compression()` → `"b" varchar(255) compression 1 not null`.

Плюс несогласованное квотирование между двумя путями: на CREATE значение подставляется сырым
(`CompressionModifier.php:39`), на CHANGE — оборачивается как идентификатор (строка 23).
Whitelist (`pglz|lz4|default`) отсутствует.

---

### 2.3. Латентные

**Сужение типов в динамически вызываемых методах.** Laravel диспатчит `type*`, `modify*`,
`compile*` через строковые имена, передавая базовые `Illuminate\Database\Schema\Blueprint`
и `Illuminate\Support\Fluent`. Пакет сузил параметры до собственных подклассов:

| Файл | Сужено до |
|---|---|
| `GrammarTypes.php:24-91` (11 методов) | `Definitions\ColumnDefinition` |
| `GrammarViews.php:12,31,51` | `Postgres\Blueprint` |
| `GrammarIndexes.php:16,26` | `Postgres\Blueprint`, `PartialBuilder`/`UniqueBuilder` |
| `CompressionModifier.php:34` | `Postgres\Blueprint` — при том, что соседний метод на строке 13 корректно использует базовый `BaseBlueprint` |

Работает только потому, что `Blueprint::addColumn()` всегда конструирует пакетный подкласс.
Любой сторонний `blueprintResolver`, `BlueprintState` или макрос → фатальный `TypeError`.
PHPStan на level 1 этого не видит.

**Переобъявление свойства фреймворка.** `src/Schema/Postgres/Builders/WhereBuilderTrait.php:14` —
`protected $attributes = [];` дублирует `Illuminate\Support\Fluent::$attributes`.
Легально ровно до того момента, как Laravel добавит там тип (`protected array $attributes`) —
тогда это станет фатальной ошибкой совместимости.

**`where()` не принимает числа.** `WhereBuilderTrait.php:21` типизирует `string $value`
под `declare(strict_types=1)`, поэтому `->where('sort', '>', 5)` бросает `TypeError` —
хотя `wrapValue()` целые числа явно поддерживает, а `@method` в `PartialDefinition`/`UniqueDefinition`
обещают нетипизированный `$value`. Код и документация противоречат друг другу.

**`removeLeadingBoolean()` без якоря.** `WheresBuilder.php:129-132` —
`preg_replace('/and |or /i', '', $value, 1)` вырезает первое вхождение **где угодно** в строке,
а не только в начале. Скопировано из Laravel вместе с тем же изъяном. Сейчас не стреляет,
потому что булев оператор всегда идёт первым, но якорь `^` стоит добавить.

**`GrammarTable::compileCreate()` не вызывает `parent::`**
(`src/Schema/Postgres/Grammar/GrammarTable.php:13`) — полностью заменяет компиляцию CREATE TABLE.
Любое изменение Laravel в этом методе теряется молча, без ошибки и без предупреждения.

**`CreateCompiler::compile()` с дефолтным `$commands = []`** (строки 19-24) обращается
к `$commands['like']`, `['fromSelect']`, `['fromTable']`, `['ifNotExists']` без `??`.
Вызов с дефолтом — «Undefined array key» ×4. Метод публичный и статический.

---

## 3. Что Laravel 13 уже умеет сам

### 3.1. Кандидаты на удаление

| Что удалить | Чем заменяется в Laravel 13 |
|---|---|
| `src/Schema/ConnectionFactory.php` целиком + `ServiceProvider::registerConnectionServices()` | Базовый `ConnectionFactory::createConnection()` **уже сам** проверяет `Connection::getResolver($driver)` первым делом (`vendor/.../Connectors/ConnectionFactory.php:270-283`). Достаточно одного `Connection::resolverFor('pgsql', fn(...) => new Postgres\Connection(...))` в `register()` |
| ✅ `Blueprint::hasIndex()` — **удалён** | Нативный `Schema\Builder::hasIndex()` (`vendor/.../Schema/Builder.php:450`) |
| ~~`Builder::hasView()`~~ — **отменено**, см. статус выше | Нативный `Schema\Builder::hasView()` (`vendor/.../Schema/Builder.php:194`) читает `pg_views` и **не видит materialized views**. После починки D9 переопределение пакета обосновано и остаётся; из него стоит доработать лишь разбор `schema.view` через `parseSchemaAndTable()` |
| `Builder::getViewDefinition()` (`Builder.php:75`) | `Schema::getViews()` уже возвращает поле `definition` (`vendor/.../Schema/Grammars/PostgresGrammar.php:108`) |
| `Blueprint::ginIndex()` (`Blueprint.php:288`) | Нативный `$table->index($cols, $name, 'gin')` — `compileIndex()` печатает `using gin` (`PostgresGrammar.php:379`) |
| 10 классов `src/Schema/Postgres/Types/*` + `AbstractType` | Backed enum. `phpType()`/`postgresType()` **не вызываются из `src/` вообще** — только из `tests/Helpers/ColumnAssertions.php:48-49`. Продакшн-код использует лишь константу `TYPE_NAME`. У `IntArrayType`/`TextArrayType`/`UuidArrayType` переопределение `phpType()` дословно повторяет собственный `TYPE_NAME` — no-op копипаст |
| `LikeDefinition`, `PartialDefinition`, `UniqueDefinition`, `ViewDefinition` | Ни разу не инстанцируются нигде в `src/` — чистые phpdoc-заглушки, на которые ссылаются только `@return`-теги, лгущие о реальном типе. `PartialDefinition` и `UniqueDefinition` вдобавок дословно дублируют друг у друга 12 строк `@method` |
| `UniquePartialBuilder` | Байт-в-байт копия `PartialBuilder` с точностью до имени класса |
| `Connection::updateAndReturn()` / `deleteAndReturn()` | Идентичные тела, обе — однострочные делегаты к `affectingStatementArray()` |
| `@method ginIndex` / `@method algorithm` в `ColumnDefinition` | Мёртвый phpdoc, см. D12 |

Ориентировочный эффект: минус ~20 файлов и ~400 строк из 1629 без потери функциональности.

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

### 4.1. `readme.md` — примеры, которые не работают

| Строка | Проблема |
|---|---|
| `readme.md:207` | `Schema::dropViewIfExists('active_users')` — **метода не существует** ни в `src/`, ни в Illuminate. `BadMethodCallException` |
| `readme.md:73` | В секции «Geo Path» показан `$table->geoPoint(...)` вместо `geoPath()`. Копипаст из предыдущей секции — создаст колонку неверного типа |
| `readme.md:55` | `$table->bit(string $column, int $length = 1)` — дефолта в коде нет (`Blueprint.php:21`), `$table->bit('col')` даёт `ArgumentCountError` |
| `readme.md:345` | `'select gen_random_uuid() as id, * ' . $tbl` — **пропущено `from`**, невалидный SQL |
| `readme.md:130` | `uuid_generate_v5()` вызван без обязательных аргументов (namespace, name) |
| `readme.md:133` | `uuid_generate_v2()` — такой функции в `uuid-ossp` не существует (есть v1, v1mc, v3, v4, v5) |
| `readme.md:120,123` | Комментарии обещают колонку `cid`, код создаёт `id` и `fk_id` |
| `readme.md:335,343` | `self::TGT_TABLE` — неопределённая константа, перенесённая дословно из теста |
| `readme.md:195` | Пример вставляет `createView()` внутрь `Schema::create('users', ...)`, что дополнительно сгенерирует `create table "users" ()`. Реальный паттерн — `Schema::table(...)`, как в `tests/Functional/Schemas/CreateViewTest.php:65` |

### 4.2. `readme.md` — пробелы

- `readme.md:10` — заголовок `## Description` **пустой**. У пакета нигде нет описания в одну фразу.
- Нет секции Requirements. Минимальные версии PostgreSQL разбросаны по тексту и нигде не сведены:
  `gen_random_uuid()` требует **PG >= 13**, `COMPRESSION` — **PG >= 14**.
- Не документированы: `ginIndex()`, `numeric()`, `hasIndex()`, `hasView()`, `getViewDefinition()`,
  `Blueprint::dropView()`, второй параметр `primaryUUID()`.
- Не сказано главного архитектурного факта: пакет **подменяет connection factory**, и все
  `pgsql`-соединения становятся `Php\Support\...\Postgres\Connection`. Это важно для всех,
  у кого стоит другой пакет, делающий то же самое.
- Не описано расхождение: Eloquent-макрос `updateAndReturn` автоматически добавляет `updated_at`
  (`ServiceProvider.php:40`), путь через `toBase()` — нет.
- `readme.md:43` — в оглавлении «deleted» вместо «updated» (копипаст соседней строки).
- Нет секций Contributing и License, хотя `LICENSE` (MIT) в репозитории есть.
- `readme.md:273` — «PostgresSQL» (лишняя `S`).
- `readme.md:5` — Codacy-бейдж на мёртвом хосте `api.codacy.com` и ссылка на устаревшую схему
  URL `/manual/`.

### 4.3. `.meta.php`

- Строки 40-49: блок mixin для `Illuminate\Database\Query\Builder` **закомментирован**.
  Из-за этого основная документированная форма `Model::toBase()->updateAndReturn(...)`
  (`readme.md:364,374`) не имеет подсказок в IDE вообще.
- Нет mixin для `Illuminate\Database\Schema\ColumnDefinition` → `->compression()` невидим
  в IDE на любой стандартной колонке. Это ровно та задача, ради которой `.meta.php` и существует.
- В списке `@method` для `Blueprint` отсутствуют ~20 публичных методов (все типы колонок,
  `fromSelect`, `fromTable`, `dropPartial`, `dropUniquePartial`, `ginIndex`, `hasIndex`).
  Частично компенсируется `@mixin` на строке 34 — что делает строки 26-32 избыточным дублированием.
- Строки 28-31 обещают возврат `PartialDefinition`/`UniqueDefinition`/`ViewDefinition`;
  в реальности возвращаются `PartialBuilder`/`UniqueBuilder`/голый `Fluent`.
- Строки 28-29 рекламируют мёртвый параметр `$algorithm` (см. D13).

### 4.4. `CHANGELOG.md`

Актуален для последнего релиза: все заявления в `[4.0.0] - 2026-06-04` проверены и верны,
`[unreleased]` пуст корректно.

Проблемы накопленные:

- **Нет записей для 7 выпущенных тегов:** `v0.0.2`, `v1.0.1`, `v1.3.1`, `v1.4.1`, `v1.6.1`,
  `v2.2.0`, `v2.2.1`.
- Расхождение дат: `[2.0.0] - 2024-03-13` против тега `v2.0.0` от **2024-04-07** (25 дней);
  `[1.1.0]` и `[1.8.0]` — на день.
- Из-за пропущенных версий compare-ссылки перескакивают релизы: `[3.0.0]` сравнивает
  `v2.1.0...v3.0.0`, пропуская 2.2.0 и 2.2.1.
- Висячее определение ссылки `[0.0.2]` на строке 207 без соответствующей секции.
- `.github/workflows/lint/rules/changelog.js:16` — регулярка дат зашита как `20[12][0-9]`,
  то есть начиная с 2030 года любой заголовок будет отвергаться.

---

## 5. Тесты и инфраструктура

### 5.1. Что сделано хорошо

93 теста / 434 ассерта, 0 падений. Полностью современный PHPUnit 13: 59 атрибутов `#[Test]`,
ноль методов `test*`, ноль `/** @test */`. Data-провайдеры — атрибутами, все `public static`,
возвращают `Generator`. Исчерпывающий поиск по `assertObjectHasAttribute`, `withConsecutive`,
`@dataProvider`, `expectDeprecation`, `prophesize` и прочим удалённым в PHPUnit 12/13 API дал
**ноль совпадений**. Здесь ничего чинить не надо.

### 5.2. Структурные пробелы

**Ни одного unit-теста.** Весь `tests/` — `Functional`. Каждый компилятор (`CreateCompiler`,
`PartialCompiler`, `UniqueCompiler`, `WheresBuilder`) достижим только через живой PostgreSQL.
Именно поэтому дефекты D1, D4-D8 дожили до релиза: все они видны при чистой проверке
`$blueprint->toSql()` без БД, что и было сделано в этом аудите за несколько минут.

**Нарушение PSR-4.** `tests/Functional/Types/ArrayOfTextTest.php:5` объявляет
`namespace Functional\Types;` вместо `Php\Support\Laravel\Database\Tests\Functional\Types`.
Проверено:

```
$ composer dump-autoload --optimize --classmap-authoritative
Class Functional\Types\ArrayOfTextTest ... does not comply with psr-4 autoloading standard. Skipping.
Class CreateTestTable ... does not comply with psr-4 autoloading standard. Skipping.
```

Сейчас тест проходит только потому, что PHPUnit подхватывает файлы напрямую.

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

`phpstan.neon`: **level 1** (второй снизу из 0-10 + max), только `src`, без baseline, без larastan.
**В CI не запускается вообще** — хотя скрипт `composer phpstan` определён (`composer.json:39`).

**`composer phpstan` красный прямо сейчас, на самом низком уровне.** Проверено на текущем HEAD:

```
src/ServiceProvider.php:40: Call to an undefined method Illuminate\Database\Query\Builder::addUpdatedAtColumn().
src/ServiceProvider.php:40: Call to an undefined method Illuminate\Database\Query\Builder::toBase().
src/ServiceProvider.php:47: Call to an undefined method Illuminate\Database\Query\Builder::toBase().
[ERROR] Found 3 errors
```

Причина — макросы на `Eloquent\Builder` (`ServiceProvider.php:37-49`): внутри замыканий PHPStan
выводит `$this` как `Query\Builder`. Лечится larastan либо явным `@var` в замыкании. Важен сам
факт: команда падает, и этого никто не замечает ровно потому, что она не в CI.

На level 6 — **142 ошибки**:

| Количество | Идентификатор |
|---|---|
| 58 | `missingType.iterableValue` |
| 34 | `missingType.generics` |
| 16 | `missingType.parameter` |
| 14 | `method.notFound` |
| 5 | `return.type` |
| 4 | `argument.templateType` |
| 3 | `missingType.return` |
| 2 | `staticClassAccess.privateMethod` |
| 2 | `property.notFound` |
| по 1 | `instanceof.alwaysTrue`, `isset.property`, `deadCode.unreachable`, `argument.type` |

Содержательные среди них: `argument.type` в `Connection.php:89` — это ровно D11;
`return.type` ×4 на `Blueprint.php:216,261,281` и `UniqueBuilder.php:15` — те самые
лгущие `@return`-теги; `property.notFound` в `CompressionModifier.php:18,22` — сужение типов.
Остальные `method.notFound` — ожидаемые следствия расширения фреймворка и лечатся
через larastan + честные типы.

### 5.5. PHPCS

`.phpcs.xml`:

- Строка 5: `<file>src</file>` — **`tests/` не проверяется**. Отсюда и нарушение PSR-4,
  и закомментированные блоки кода в тестах. Исключение для `tests/bootstrap.php`
  на строках 95-97 — мёртвая конфигурация.
- Строки 2-3: ruleset называется «PSR2» и описан как «The PSR2 coding standard»,
  а на строке 4 подключает `PSR12`.
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

`.github/workflows/ci.yml` — 4 job'а, матрица `setup: [basic, lowest, stable] × php: [8.4, 8.5]`.

- **Нет шага PHPStan.** Статический анализ не enforced.
- **Нет coverage.** `phpunit.xml:6-13` настраивает clover/html/text/xml, Dockerfile ставит pcov,
  `composer phpunit-cover` определён — но все job'ы ставят `coverage: none`, а `composer test`
  запускает `phpunit --no-coverage`. CodeClimate убран в 4.0.0 без замены.
- **`basic` и `stable` функционально идентичны:** `composer update --prefer-dist` против
  `composer update --prefer-dist --prefer-stable`; `minimum-stability` в `composer.json`
  не задан, значит stable и так дефолт. 2 из 6 job'ов — чистое дублирование.
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

`composer audit` на текущей резолюции показывает **22 advisory в 4 пакетах**:

| Пакет | Advisories | Тяжесть для пакета |
|---|---|---|
| `league/commonmark` | 10 (DoS, XSS в `AttributesExtension`) | транзитивная dev-зависимость через `laravel/framework` |
| `guzzlehttp/guzzle` | 9 (обход host-проверок, утечка cookie/Proxy-Authorization) | транзитивная dev-зависимость |
| `guzzlehttp/psr7` | 2 (host confusion, CRLF-инъекция) | транзитивная dev-зависимость |
| `squizlabs/php_codesniffer` | 1 — CVE-2026-67434, OS command injection, затронуты `<3.13.6` | **прямая dev-зависимость**, в `composer.json` стоит `^3.11` |

На потребителей библиотеки это не влияет: в `require` только `illuminate/database` и `ext-pdo`,
все перечисленные пакеты приходят через `orchestra/testbench` → `laravel/framework` в dev.
Но `squizlabs/php_codesniffer` стоит поднять до `^3.13.6` явно — сейчас `^3.11` разрешает
уязвимую версию, а CI делает `composer update` без lock-файла, то есть резолюция каждый раз
непредсказуема. Заодно стоит добавить шаг `composer audit` в CI.

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

**Требования vs реальность.** `composer.json` требует PHP >= 8.4, но код написан
в диалекте PHP 7.4/8.0. В пакете **нет ни одного**: `enum`, `readonly`, promoted constructor
property (ни один класс в `src/` вообще не объявляет конструктор), first-class callable syntax,
`never`, intersection type, typed class constant (8.3), `#[\Override]` (8.3), `final`,
asymmetric visibility (8.4), property hook (8.4).

Особенно обидно отсутствие `#[\Override]`: именно он поймал бы устаревшие копии родительских
методов — то есть D3 и рассинхрон `registerConnectionServices()`.

**Что используется современного:** `match(true)` (`Connection.php:48`), стрелочные функции,
union и nullable-типы, `static` как возвращаемый тип, вариадики, `declare(strict_types=1)`
в 35 файлах из 37.

### Конкретные точки приложения

| Замена | Где |
|---|---|
| `switch (true)` → `match (true)` | `src/Schema/Postgres/Blueprint.php:47` — единственный `switch` в пакете, и у него нет `default`, из-за чего `$defaultExpression` остаётся неопределённой и прикрывается `?? null` на строке 67 |
| `[$this, 'wrap']` → `$this->wrap(...)` | `src/Schema/Postgres/Grammar.php:25` |
| `fn($item) => $this->wrap($item)` → `$this->wrap(...)` | `src/Query/Grammars/PostgresGrammar.php:13` |
| `call_user_func_array('sprintf', array_merge(...))` → `sprintf($fmt, ...$args)` | `src/Schema/Postgres/Compilers/WheresBuilder.php:15-21` |
| `call_user_func_array([$command, $method], $parameters)` → `$command->$method(...$parameters)` | `src/Schema/Postgres/Builders/Indexes/Unique/UniqueBuilder.php:14` |
| `call_user_func($this->resolver, ...)` → `($this->resolver)(...)` | `src/Schema/Postgres/Builder.php:18` |
| `collect()->map(closure)->toArray()` → `array_map` | `WheresBuilder.php:109-113`, `136-148` — Collection-round-trip ради того, что делает `array_map` |
| `Types\*` (10 классов) → backed enum | вся директория `src/Schema/Postgres/Types/` |
| `#[\Override]` | 14 реальных переопределений (перечень в разделе 3.1 и 2) |
| `declare(strict_types=1)` | добавить в `src/Query/Builder.php` и `src/Query/Grammars/PostgresGrammar.php` — единственные два файла без него |
| Типы возврата | `Query/Builder.php:20,42`; `UniqueBuilder.php:11`; `Builder.php:29`; `CompressionModifier.php:13`; `WheresBuilder.php:116` |
| Типы параметров | `Builder.php:41,48,62,75`; `Blueprint.php:71,288,293,298` |

**Про сам минимум PHP.** Laravel 13 требует `php: ^8.3`. Подъём минимума пакета до `>=8.5` —
собственное решение, а не требование фреймворка, и оно отсекает большинство пользователей
Laravel 13. Технически ничто этого не требует: `php -l` под 8.5.10 проходит по всем файлам,
ни одна конструкция 8.4/8.5 не используется. Компромиссный вариант `^8.4 || ^8.5` дал бы
тот же уровень современности при вдвое большем охвате. Решение принято в пользу `>=8.5`
осознанно и зафиксировано здесь как сознательный trade-off.

---

## 7. Производительность и консистентность API

**`updateAndReturn()` возвращает не то, что весь остальной Laravel.**
`Connection::associateStatement()` (`src/Schema/Postgres/Connection.php:97-100`) жёстко задаёт
`PDO::FETCH_ASSOC` и обходит `Connection::prepared()`. В результате:

- `select()` возвращает массив `stdClass` (у Laravel `protected $fetchMode = PDO::FETCH_OBJ`),
  а `updateAndReturn()` — массив массивов. Разные формы данных у соседних методов одного соединения.
- Настроенный пользователем fetch mode игнорируется.
- Событие `StatementPrepared` не диспатчится — пакеты, которые на него подписаны
  (в том числе для установки кастомного fetch mode), не сработают.

**`GrammarTable::compileCreate()` не вызывает `parent::`** — полностью заменяет компиляцию
CREATE TABLE. Любое улучшение Laravel в этом методе теряется без ошибки и без предупреждения.
Соседний `compileDropIfExists()` (строка 31) сделан правильно: вызывает `parent::` и дописывает
`cascade`. Это образец для первого.

**Регистр SQL разъезжается.** Laravel генерирует SQL в нижнем регистре. Пакет:
`" RETURNING ..."` (`Query/Grammars/PostgresGrammar.php:15`), `"CREATE INDEX"` / `"CREATE UNIQUE INDEX"`
(`PartialCompiler`, `UniqueCompiler`), `"ALTER TABLE ... SET COMPRESSION"` (`CompressionModifier.php:20`),
`"as TABLE"` (`CreateCompiler.php:64`) — в верхнем, а `GrammarViews` в том же пакете — в нижнем.
Это не косметика: тесты сравнивают точные строки, и любое приведение к единому стилю их сломает,
что и делает такую унификацию работой для мажора.

**Дублирование, которое стоит схлопнуть:** `compileCreateView` / `compileCreateViewOrReplace`
(15 строк, разница в одной строковой константе); `createView` / `createViewOrReplace`
в `Builder`; `updateAndReturn` / `deleteAndReturn` в `Connection`; `uniquePartial` / `partial`
в `Blueprint` (различаются только классом билдера и префиксом имени индекса);
`createExtension` / `createExtensionIfNotExists`; `PartialBuilder` / `UniquePartialBuilder`.

**Микро:** `Builder::hasView()` и `getViewDefinition()` каждый раз вызывают
`getCurrentSchemaName()`, который выполняет запрос `show search_path`. Незначительно
(схема вызывается в миграциях), но кэшируемо.

---

## 8. План развития

### v5.0.0 — следующий релиз (BC break, без deprecation-периода)

**Блок 1. Версии.** PHP `>=8.5`, CI и Docker на 8.5. *(выполнено в этой итерации)*

**Блок 2. Дефекты.** Исправить D1-D14. Порядок по риску: D1 (`Collate`) → D2/D3 (compression +
deprecated API) → D7 (table prefix) → D4/D5 (экранирование и `whereRaw`) → D6 (двойные пробелы)
→ D8 (висячий WHERE) → D9 (materialized views) → остальное. Каждый фикс сопровождается
unit-тестом на `toSql()` без БД.

**Блок 3. Удаление избыточного** (раздел 3.1): `ConnectionFactory` и
`registerConnectionServices()` → один `Connection::resolverFor('pgsql', ...)`;
`Builder::hasView()`, `getViewDefinition()`, `Blueprint::hasIndex()`, `ginIndex()` → нативные
аналоги Laravel 13; `Types\*` → backed enum; четыре `*Definition`-заглушки и
`UniquePartialBuilder` → удалить. Ожидаемо −20 файлов, −400 строк.

**Блок 4. Типобезопасность.** Расширить сужённые типы до базовых `Blueprint`/`Fluent`
(раздел 2.3); убрать переобъявление `Fluent::$attributes`; проставить `#[\Override]`
на все 14 переопределений; добить недостающие типы параметров и возврата.

**Блок 5. Качество.** Починить три текущие ошибки `composer phpstan` (5.4); PHPStan level 6
+ larastan, **в CI**; `composer audit` в CI и явный `squizlabs/php_codesniffer: ^3.13.6` (5.8); PHPCS на `src` и `tests`;
unit-тесты на все компиляторы (без БД) как основной слой, functional — как интеграционный;
`#[CoversClass]` и `failOnWarning`/`failOnDeprecation`/`failOnRisky` в `phpunit.xml`;
починить PSR-4 в `ArrayOfTextTest`.

**Блок 6. Документация.** Переписать `readme.md`: заполнить Description, добавить Requirements
(PHP, Laravel, минимальные PG для `gen_random_uuid()` и `COMPRESSION`), починить все неработающие
примеры из 4.1, задокументировать пропущенные методы и факт подмены connection factory,
добавить раздел «что теперь умеет сам Laravel 13» (3.2). Восстановить блок `Query\Builder`
и добавить mixin `ColumnDefinition` в `.meta.php`. Забэкфиллить 7 пропущенных версий в CHANGELOG.

### v5.1+ — функциональное развитие

- `dropViewIfExists()`, `dropMaterializedView()`, `refreshMaterializedView()`
  (последнее — с `CONCURRENTLY`). Закрывает D9 и обещание `readme.md:207`.
- `CONCURRENTLY` для partial-индексов — Laravel 13 уже умеет `online()` для обычных.
- Проброс `$algorithm` в `PartialCompiler`/`UniqueCompiler` (закрывает D13).
- `nullsNotDistinct` для partial-unique — по аналогии с нативным.
- `orWhere*` в `WhereBuilderTrait`: сейчас есть `$boolean`-параметр, но нет удобных обёрток.
- Расширение `where()` до `string|int|float|bool|BackedEnum|DateTimeInterface` (закрывает
  вторую половину D4).

### Инфраструктура

- Матрица PostgreSQL 15/16/17/18 — ассерты завязаны на PG-специфичный deparse.
- PHPStan и coverage-репорт в CI; `permissions: contents: write` для `release`;
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

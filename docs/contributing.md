# Testing & contributing

[← Documentation](readme.md) · [Package readme](../readme.md)

---

Running the suite, and the gate a pull request has to pass.

## Testing

The package targets PostgreSQL, so the functional suite needs a running server.

**With Docker** — nothing local required:

```bash
composer test:docker

# both versions are overridable
POSTGRES_VERSION=15 composer test:docker
PHP_VERSION=8.5 composer test:docker
```

**Locally** — connection settings come from the environment, defaulting to `forge`/`forge`/`forge`
on `localhost:5432`:

```bash
composer test         # PHPCS + the whole suite
composer test-cover   # with coverage
composer phpunit-unit # the unit suite alone — asserts on generated SQL, needs no database
```

> The unit suite asserts on generated SQL and never opens a connection, so it runs anywhere. The
> functional suite reads the catalogue back from a real server, which is the only way some of
> these features can be checked at all.

## Contributing

Issues and pull requests are welcome. Before opening one, run the gate CI runs:

```bash
composer phpcs      # PSR-12 over src and tests
composer phpstan    # level 6 with larastan
composer test       # PHPCS + the whole suite, needs PostgreSQL
```

New behaviour wants a test that fails without it — the suite is checked by mutation, not by
coverage percentage. Break the code, and something should go red.

| Check | Standard |
|---|---|
| PHPCS | PSR-12 over `src` and `tests` |
| PHPStan | Level 6 with larastan; `src` is analysed without a single exemption |
| PHPUnit | Fails on warnings, notices, deprecations, risky tests and output |
| Matrix | PostgreSQL 13 – 18, plus a lowest-dependencies run |

## Design decisions

Four things in this package look like duplicated framework code and are kept deliberately. They
were each checked against Laravel 13 and found justified; the reasons are here so the question
does not get reopened.

| Kept | Why it is not framework duplication |
|---|---|
| `Builder::hasView()` | The framework's reads `pg_views`, which is `relkind = 'v'`. Materialized views live in `pg_matviews` (`relkind = 'm'`) and the two sets are disjoint, so the native method cannot see them |
| `Builder::getViewDefinition()` | Same reason: native `compileViews()` reads `pg_views` only |
| `Blueprint::ginIndex()` | A shorthand over `indexCommand()`, not a copy. It also carries an operator class, which the framework accepts on spatial and vector indexes only |
| `Connection::updateAndReturn()` / `deleteAndReturn()` | A live public API with callers in `src/`, in the tests, in `.meta.php` and in the documentation |

`Builder::createExtension()` / `createExtensionIfNotExists()` and the two `…AndReturn()` methods
are each two lines apart from their sibling. Collapsing them would change a public API for a very
small gain, so they stay as they are.

**The PHP floor is a deliberate choice, not a requirement.** Laravel 13 itself needs only
`php: ^8.3`, and nothing in `src/` uses an 8.4 or 8.5 construct — `^8.4 || ^8.5` would reach twice
as many applications at the same level of modernity. `>= 8.5` was chosen anyway, and this is the
record of that trade-off.

**The rule that matters most: do not copy framework methods — call `parent::` and add to it.**
Five methods had been copied wholesale at one point, and one of them had already drifted out of
sync, silently dropping two service bindings. None remain. `GrammarTable::compileDropIfExists()`
is the pattern to follow, and `#[\Override]` belongs on every override so PHP itself catches a
parent method that disappears.

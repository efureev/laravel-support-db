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

## Contributing

Issues and pull requests are welcome. Before opening one, run the gate CI runs:

```bash
composer phpcs      # PSR-12 over src and tests
composer phpstan    # level 6 with larastan
composer test       # PHPCS + the whole suite, needs PostgreSQL
```

New behaviour wants a test that fails without it — the suite is checked by mutation, not by
coverage percentage.

# Views

[← Documentation](readme.md) · [Package readme](../readme.md)

---

Plain and materialized views — creating, refreshing, inspecting and dropping them, including in another schema.

```php
Schema::createView('active_users', 'select id, email from users where is_active');
Schema::createViewOrReplace('active_users', 'select id, email, role from users where is_active');

// third argument makes it MATERIALIZED
Schema::createView('order_totals', 'select user_id, sum(total) from orders group by 1', true);
```

```sql
create view "active_users" as select id, email from users where is_active
create or replace view "active_users" as select id, email, role from users where is_active
create materialized view "order_totals" as select user_id, sum(total) from orders group by 1
```

The same methods exist on the blueprint, if you would rather create a view alongside its table:

```php
Schema::table('users', static function (Blueprint $table) {
    $table->createView('active_users', 'select id from users where is_active');
});
```

**Refreshing a materialized view.**

```php
Schema::refreshMaterializedView('order_totals');
Schema::refreshMaterializedView('order_totals', true);   // CONCURRENTLY
```

```sql
refresh materialized view "order_totals"
refresh materialized view concurrently "order_totals"
```

> `CONCURRENTLY` keeps the view readable while it rebuilds, but PostgreSQL requires the view to
> carry a unique index and to have been populated at least once. It is also refused inside a
> transaction block.

**Inspecting.** Both of these see materialized views, which the framework's own `hasView()` does
not — it reads `pg_views`, where materialized views do not appear.

```php
Schema::hasView('active_users');            // bool
Schema::getViewDefinition('active_users');  // the SELECT, or '' if there is no such view
```

**Other schemas.** Every view method takes a `schema.view` reference, the lookups included:

```php
Schema::createView('reporting.active_users', 'select id from users where is_active');

Schema::hasView('reporting.active_users');           // true
Schema::hasView('active_users');                     // false — a different view
Schema::getViewDefinition('reporting.active_users');
Schema::dropView('reporting.active_users');
```

Without a schema the connection's own is used. A three-part reference is rejected.

**Dropping.** A materialized view must be dropped as such — `DROP VIEW` fails on one:

```php
Schema::dropView('active_users');
Schema::dropViewIfExists('active_users');

Schema::dropView('order_totals', true);          // drop materialized view
Schema::dropViewIfExists('order_totals', true);
```

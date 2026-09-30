# Persistence

Models, repositories, migrations and factories. The database is PostgreSQL 17 — locally and in CI
(`postgres:17-alpine`), and PostgreSQL is the target for every deployed environment.
`config/database.php` defines `pgsql` and `sqlite`.

## Repositories

**DB-01 — A repository is the only place that touches Eloquent for its domain.** Services do not
query; controllers, listeners, jobs and commands certainly do not. A controller never references a
model at all, not even as a type: it reads what it needs through the service or the framework's
contracts (`Authenticatable`).

**DB-02 — A repository returns entities, never Eloquent models.** The model is a persistence
detail; letting one escape the repository means the rest of the code depends on the table shape and
on the model's lazy loading.

**DB-03 — Repository method names are identical to the service methods that call them** — see the
vocabulary table in [`entities.md`](entities.md). The service delegates; two different names for
one operation is a lie about what the code does. A service method that only calls its repository
carries the repository method's name. A repository serving several services of its
domain has no single caller to name its methods after, so it uses the `store` / `get` / `delete`
vocabulary instead.

**DB-04 — A repository interface is injected only into services of its own domain** (DA-04).

Repository classes are `{Noun}Repository` and `{Noun}RepositoryInterface`, beside each other
in `Repositories/` ([PHP-17](php.md)).

## Models

**DB-05 — Every model carries a `toEntity()` method.** That method is the single mapping between
the table and the domain, and the place round-trip fidelity (ENT-04) is won or lost.

**DB-06 — Every model carries `@property` PHPDoc** for its columns. Without it nothing can check a
property access, and the reader has to open a migration to learn what a model holds.

**DB-07 — Eloquent casts use `protected $casts = [];`.** The `casts()` method works too, but every
model here uses the property, and one style is what lets a reader find a model's casts without
looking twice.

**DB-08 — Prefer `Model::query()` over the `DB` facade.** Use typed Eloquent relationship methods
rather than raw queries or manual joins, and eager-load to avoid N+1.

**DB-09 — Every new model gets a factory and a seeder** — except a model backing an external store
the test suite never reaches, which is always mocked.

## Factories

**DB-10 — Factories live flat in `database/factories/`.** Never nested in
`database/factories/Domains/` or any other subdirectory — Laravel's factory resolution expects the
flat layout, and a nested one works only until someone relies on the convention.

In tests, use factories, and check for an existing custom state before adding one.

## Migrations

**DB-11 — Strongly correlated migrations belong in one file.** An `invoices` table with its `invoice_lines` and `invoice_tags` pivot is a single migration. Keep them separate only
when they are logically independent, or when they may genuinely need to be rolled back
independently.

**DB-12 — Never convert a `json` column to `jsonb`.**

This one is worth understanding rather than memorising. `jsonb` discards the original text and
re-sorts object keys. Anything whose meaning depends on key order — an ordered form definition, a list of options stored
as an object — depends on `json` preserving it, and the breakage is **silent**: no query fails, no error is logged, the data simply
comes back in a different order and the UI renders wrongly. It is also a direct violation of
round-trip fidelity (ENT-04).

**DB-13 — Write migrations against PostgreSQL.** It is the engine in every environment; there is no
MySQL to accommodate. Where a change is destructive or needs a backfill, it is a delicate release —
see [`git.md`](git.md) and the release stage of `/ship`.

## Console commands and the scheduler

**DB-14 — A command using `ConfirmableTrait` must pass `--force` when scheduled.** Without it the
command silently aborts on every scheduled run in production, because there is no TTY to confirm
against. The flag goes in the command **string**, not in the parameters array.

```php
$schedule->command('invoices:purge-drafts --force')->hourly();
```

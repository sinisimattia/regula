# Entities

An entity is the shape a piece of domain data has **in our code**, independent of how it is stored
or serialised. Getting entities right is what makes the rest of the architecture hold: they are the
only data allowed to cross a domain boundary (DA-01), so they carry the meaning.

An entity, a value object and a model are named for the concept and take no suffix — `User`, not `UserEntity` or `UserModel`. That is deliberate: [PHP-17](php.md) suffixes framework classes and
leaves the ones that name a concept — entities, models, enums, events — alone.

## Entities are typed objects

**ENT-01 — Strongly typed entities, not free arrays.** `array` and `stdClass` are forbidden as the
type of domain data in any service, repository or API Resource signature.

```php
// No. The caller has to guess what is in here, and nothing catches a typo.
public function storeUser(array $user): array;

// Yes.
public function storeUser(User $user): User;
```

Arrays are permitted for two things: a genuinely homogeneous collection, documented with its
element type (`/** @return Comment[] */`), and a configuration payload that really is arbitrary
key-value data.

The cost of an array is paid later, by someone else: no autocomplete, no static check, no single
place to add a field, and a typo that surfaces as `null` three layers away.

**ENT-06 — Entities are pure PHP.** No framework dependencies — no Eloquent, no facades, no
container. An entity you can construct in a test without booting Laravel is an entity you can
reason about.

**ENT-07 — Prefer a full entity reference over a primitive ID.**

```php
public User $reporter;        // yes
public int $reporterUserId;   // no
```

The deliberate exception is a typed ID (`UserId $userId`) used to avoid a circular reference
or to keep a heavy object out of a light one. That exception is a judgement call about the specific
entities involved, so read the surrounding entities before deciding — and before flagging one in
review.

**ENT-05 — Entity ID value objects extend `App\Domains\Core\Contracts\Identification`, and a
typed ID is trusted.**

An ID travels as its own type — `UserId`, `InvoiceId` — never as a bare `int`, `string` or `mixed`
parameter that merely happens to be named like one:

```php
public function getUserById(UserId $userId): User;   // yes
public function getUserById(mixed $userId): User;    // no
```

That type is the contract. **Code that receives an `Identification` child treats the ID as correct
and does not re-validate it.** `Identification::$value` is declared `mixed`, and that is the
wrapper's business rather than its readers' — a reader that re-checks the value is asserting
a suspicion the type is supposed to have settled, and makes the type mean less rather than more.

Where a value is wrapped **into** a typed ID from something that could not carry the type — a cache
round-trip, a queue payload — normalise it at that one construction point — the method that rebuilds the ID from the payload. One
cast where the ID is built, not a validation
everywhere it is read.

A **nullable** ID is a different question and still needs answering. `?UserId` may be null
because the entity was never persisted, and code that requires one says so rather than discovering
it downstream.

## The persistence vocabulary

**ENT-02 — The base triad is `store` / `get` / `delete`**, plus domain-specific methods where
genuinely needed. **Service and repository method names are identical** — the service delegates to
the repository, and two different names for one operation is a lie about what the code does.

| Intent | Name | Notes |
|---|---|---|
| Fetch one by ID | `get{Entity}ById(...)` | **Throws** when missing; return type **not** nullable |
| Fetch a collection | `get{Entity}s...(...)` | Returns an array, element type in PHPDoc |
| Create or update | `store{Entity}(...)` | One method, never a create/update pair |
| Delete | `delete{Entity}(...)` | |
| N-to-M relations | `attach{X}To{Y}(...)` / `detach{X}From{Y}(...)` | |

`find*` is wrong: it implies a nullable return where this codebase wants an exception.
`associate` / `dissociate` is wrong: use `attach` / `detach`.

Laravel's own vocabulary — `create`, `update`, `save`, `find` — is what drifts in first. Rename it
as you touch it.

**ENT-03 — `store{Entity}(Entity $e): Entity` decides insert or update from the entity itself.**
No id means insert; an id present means update.

```php
public function storeUser(User $user): User;
```

One method, not a `create`/`update` pair. The caller usually does not know or care which it is —
it has an entity and wants it persisted — and making it choose means every caller implements the
same `if`.

Where a table carries a **unique natural key** and an **idempotent writer** stores through it — a
seeder re-running, an importer replaying — a `store` with no id may resolve by that key and update
the row holding it, inserting only when the key is free. The key stops being identity the moment the
entity offers one: an id present always wins, and a code that has moved is a **rename of that row**,
not a new record.

Both halves of that matter, and the second is the one that bites. A repository that resolves purely
by natural key turns a renamed code into a fork: the entity carries an id but a changed code, finds
nothing under the new code, and inserts a second row — everything already attached to the old row
stays there, new writes go to the new one, and nothing is raised. The narrow licence above exists for
seeders and importers that replay null-id records under keys that already exist; it is not a general
licence to write by natural key.

**ENT-08 — `get*` throws, it does not return null.** A missing record is a domain exception
(`UserNotFoundException`), not a null the caller may forget to check.

**ENT-04 — Round-trip fidelity.** The entity `store` returns is rebuilt from the persisted record,
and must not diverge from the entity that went in beyond **server-assigned fields** — the id, the
timestamps.

```php
$storedUser = $userRepository->storeUser($user);
// $storedUser is $user, as the database now holds it: same values, plus an id.
```

If a field comes back changed, truncated, reordered or missing, that is a bug in the entity or its
mapping — not something for the caller to work around. This is why a `json` column is never
converted to `jsonb`: `jsonb` re-sorts object keys, so an entity stored and re-read comes back
different, silently.

The practical test: store an entity, read it back, and compare. Anything that differs and is not
server-assigned is a defect.

# Domain architecture

How a domain module under `app/Domains/` is shaped, what it may expose, and what it may depend on.

This is the file that exists because a domain drifted and nothing caught it. Read it in full
before creating or restructuring anything under `app/Domains/`.

## What a domain is

**DA-05 — A domain is a self-contained Laravel application.**

Its structure is whatever `app/` legitimately contains — `Broadcasting`, `Casts`, `Console`,
`Events`, `Exceptions`, `Facades`, `Http`, `Jobs`, `Listeners`, `Mail`, `Models`, `Notifications`,
`Observers`, `Policies`, `Providers`, `Rules`, `Traits` — **plus our own concepts on top**:

| Folder | Holds |
|---|---|
| `Services/` | Business logic, and the interfaces of the services other domains may call |
| `Repositories/` | Data access, and their interfaces |
| `Entities/` | Pure PHP domain objects and value objects |
| `Enums/` | Enumerations that belong to the domain's data |
| `Contracts/` | Interfaces this domain declares for **other** domains to implement |
| `Docs/` | The domain's business rules, in plain language |
| `Helpers/` | Shared utilities. Never call this folder `Support` |
| `Integrations/` | Third-party API clients |

If you know Laravel, you can find your way around a domain. That is the whole idea.

**DA-06 — Domain-specific folders are legitimate, but they must be documented.**

A domain that genuinely needs, say, `Strategies/` for pluggable authentication schemes or
`Products/` for a family of sellable items may have them. The requirement is documentation, not
permission: any folder outside the standard-Laravel-plus-our-concepts set is explained in that
domain's `README.md` **and** `Docs/` — what it holds, and why the standard structure did not fit.

**An undocumented novel folder is the violation. The folder itself is not.**

**DA-07 — State the intent before inventing a folder.** An assistant introducing a new folder kind
says what it is for and confirms first. It never invents one silently, and never writes the
justification afterwards to rationalise a decision already taken.

**DA-08 — `Subscribers/` is legitimate.** `Event::subscribe` registers one class that handles many
events; a `Listener` handles one. That is a real distinction, not two names for the same thing.
Use `Listeners/` for single-event handlers and `Subscribers/` for multi-event classes. Both
carry their suffix — `{...}Listener`, `{...}Subscriber` (PHP-17).

**DA-09 — Console commands live in `Console/Commands/`.**

**DA-18 — Every domain has a `README.md` and a `Docs/` folder, correct as of the change that
touched it.**

- `README.md` is the front door: what the domain owns, its core concepts, its public surface, how
  it relates to other domains. Readable in five minutes.
- `Docs/` is the depth: one file per rule-area, plain language with worked examples.
  `app/Domains/Auth/Docs/` is the model to copy.

Updating them is **part of the change that altered the behaviour**, not a follow-up. A domain whose
documentation describes last quarter's rules is worse than a domain with none, because people trust
it.

`Docs/` is where reasoning lives, whatever its length (PHP-19). What a page is held to
is in [`README.md`](README.md#what-a-documentation-page-is-held-to).

## The public surface

A domain is a boundary. These four rules are what make it one.

**DA-01 — What may cross a domain boundary. Closed list:**

- service **interfaces**
- **contracts**
- entities
- enums
- events
- exceptions

Nothing else.

**DA-02 — What is domain-internal, and must never be referenced from outside:** concrete service
classes, **any service with no interface**, repositories and their interfaces, Eloquent models,
everything under `Http/`, jobs, listeners, traits, helpers, console commands.

**DA-03 — A service used outside its domain must have an interface**, and consumers depend on the
interface. A service with **no** interface is by definition domain-internal, and nothing outside
the domain may reference it.

Both kinds are legitimate. What is illegitimate is a bare concrete class crossing the boundary —
that is how a domain's internals become somebody else's dependency, and how you discover six months
later that you cannot change them.

**DA-04 — What a class may inject:**

| Class | May inject |
|---|---|
| A service | Its own domain's repository interfaces; other domains' service interfaces; contracts |
| Anything else | Service interfaces and contracts. **Never** a repository |

A repository interface goes only into services of its own domain — never a controller, listener,
middleware, command, job, or a service of another domain.

This is the rule most often broken out of convenience, and the one that quietly dissolves the
boundary. When a class needs data owned by another domain, it injects **that domain's service
interface**.

**DA-20 — A domain's `Http/` is never imported by another domain.** Controllers, Form Requests,
middleware — none of it crosses. An endpoint is one domain's way of answering one caller; borrowing
it couples two domains through a shape neither of them owns.

**The single exception: another domain's API Resource may be used to compose a response.** That is
what Resources are for — a Resource is a shape, not behaviour, and a response assembled from several
domains' shapes is an ordinary thing to build. Nest one inside your own Resource, or return one from
a controller; both are composing a response.

The exception covers `Http/Resources/` **and nothing else**. Controllers, Form Requests, middleware
and exceptions from another domain never cross, in either direction.

**DA-21 — `Http/Exceptions/` must not exist. Anywhere.**

A domain's exceptions live in its `Exceptions/` folder, in the domain's own vocabulary. Errors
crossing into HTTP are wrapped by the controller in `App\Exceptions\HttpApplicationException`
(HTTP-04). Those are the only two places, and between them they cover everything.

An `Http/Exceptions/` folder is a sign of a third thing that should not exist: a class that
translates domain exceptions into HTTP ones, sitting where an exception should be. That translation
belongs in the controller doing the wrapping — which is the one place that already knows what the
failure means over HTTP.

A useful tell: if the class is not `Throwable`, it is not an exception, and the folder name is
telling you so.

**DA-22 — Filament triggers domain behaviour through service interfaces.**

Reading models to fill a table or a form is what Filament is, and that stays. But **anything that
changes state goes through the domain's service interface** — `handleRecordCreation`,
`handleRecordUpdate`, `handleRecordDeletion` and every custom action.

The reason is not boundary hygiene, it is having one implementation of each rule. A user deleted
through the admin panel must fire the same events, respect the same invariants and revoke the same
tokens as one deleted through the API. The moment the admin panel manipulates a model
directly, there are two implementations of that rule and only one of them is tested.

By interface, not by concrete class — otherwise the admin panel pins the domain's internals in place
and the domain cannot change them.

**That clause is enforced**, by `FilamentDomainBoundary` rather than by `CrossDomainBoundary`, and
`FilamentStateChanges` catches a model written to directly from the panel.
`CrossDomainBoundary` treats any reference to another domain's `Models/` as a crossing, which is
right everywhere except here — most of what an admin panel imports from a domain is exactly the
model reads this rule blesses. So the panel gets its own check with the model exemption built in,
which is the only way to hold it to the service half without excusing the reads wholesale.

The exemption follows the class, not the folder depth. A model four segments down, such as
`Billing\Integrations\Erp\Models\Invoice`, is still a model, and a relation manager reading it is
doing what this rule permits.

## Contracts

**DA-10 — `Contracts/` holds interfaces — or abstract bases — a domain declares for *other* domains
to implement.** The
declaring domain defines the shape; someone else supplies the behaviour. This is an inverted
dependency, and it is how a domain asks a question of the rest of the system without depending on
the answer.

The live case in this codebase is `Core`:

```
Core\Contracts\Identification   ← declared by Core
    extended by Auth\Entities\UserId
```

Core implements nothing. It states the shape every typed ID has and lets each domain supply its own.
The shape generalises — a `Billing` domain that needs to know how many seats a customer uses would
declare `Billing\Contracts\SeatCounter`, and each domain that holds seats would implement it.

**`Core` is the domain for types every other domain builds on**, and nothing else. The bar for
admission is that *every* domain depends on the type, or will the moment it has entities — a typed
ID base class passes; a helper two domains happen to share does not. Core holds no business logic,
no services and no models. If a candidate fails the bar it belongs to the domain that owns the
concept, reached through that domain's public surface (DA-01).

**DA-11 — The test is who implements it.** If the declaring domain implements the interface, it is
not a contract — it belongs beside its implementation in `Services/` or `Repositories/`.

**DA-12 — Contracts are public surface** by definition: other domains must see them to implement
them. They are exempt from the `*Interface` suffix, because they name a **role**
(`Identification`), not a service.

## Services

**DA-13 — One operation, one call.** A consumer must never chain several service calls to achieve
one outcome. The service exposes the whole operation and guarantees it.

If callers are orchestrating — call this, then that, then check the result — the orchestration
belongs inside the service. Every caller that has to know the sequence is a caller that can get it
wrong, and they will each get it wrong differently.

**DA-14 — Anything a consumer does not need is `private` or `protected`.** The public surface of an
exported service is exactly its interface, no more. A public method that is not on the interface is
either a missing interface entry or a helper that should not be public.

**DA-15 — Errors are reported by throwing domain exceptions.** Never a null return, a boolean, or a
status code. The caller should not be able to ignore a failure by forgetting to check.

**DA-16 — Classes in `Services/` are named `{Noun}Service`**; the ones other domains call add
`{Noun}ServiceInterface`, sitting beside the implementation. Not in `Contracts/` — that folder is
for something else (DA-10).

The exception is a **contract implementation**. A class implementing another domain's contract takes
the name of the *role* it fills, because that is what it is:

```php
// Seats/Services/AssignedSeatCounter.php
final class AssignedSeatCounter implements SeatCounter
```

Calling that `AssignedSeatCounterService` would add a word that tells the reader nothing and
hide the one that tells them everything.

**DA-17 — Every service and repository interface is explicitly bound** to its implementation in the
domain's own `{Domain}ServiceProvider`:

```php
public function register(): void
{
    $this->app->bind(AccountServiceInterface::class, AccountService::class);
    $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
}
```

Relying on the container to autowire a concrete class is a violation, even though it works. The
provider is the one place a reader can see what a domain actually offers; a service that resolves
by autowiring is invisible there.

A **contract implementation** is bound by the domain that implements it, not the one that declared
it. When several domains implement one contract, tagging the implementation counts as binding it.

**DA-19 — Domain events are dispatched with the `event()` helper, from inside the service method.**
Never `EventClass::dispatch()`, and never from a controller or anywhere else in the HTTP layer.

The reason is behavioural, not stylistic: a service method called from a console command, a queued
job or another service must produce the same side effects as one called from a controller.
Dispatching in the controller means the event silently does not fire for every other caller.

**DA-23 — A listener that enriches an event in place is synchronous, and registered before every
queued listener that reads what it adds.** A test asserts the registration order directly.

A queued listener does not receive the event: it receives a copy — a clone taken when the
dispatcher reaches it, serialised on the way to the queue. Anything written onto the event after
that moment — by a listener registered later, or by one that is itself queued and so works on a copy
of its own — never reaches it. Nothing throws and nothing is logged; the queued side simply works on the event as it was. A
listener that stamps a price onto an event ahead of a queued one that records it is the typical
case: reversed, every record arrives without the price. Almost nothing depends on listener order, so
nobody reading the array expects it to matter — which is exactly why the one that does needs a test
to hold it. The test resolves the dispatcher's listeners for the event and asserts their order.

Method naming for services and repositories is in [`entities.md`](entities.md). Class naming for
everything else a domain holds — controllers, Form Requests, API Resources, jobs, listeners,
subscribers, commands, notifications, policies, exceptions — is [PHP-17](php.md), and it applies
inside a domain exactly as it does anywhere else.

## Consumers outside `app/Domains/`

**The public surface rules bind every consumer, not only other domains.** DA-01 to DA-04 and DA-10 to
DA-12 protect the domain being reached into, so they bind every file in `app/` outside that domain,
`app/Http/` and `app/Models/` included, exactly as they bind another domain. A consumer may call a
domain's service interface and use its contracts, entities, enums, events and exceptions. None may import a domain's models, repositories, jobs, helpers or
interface-less services. **API Resources are the exception** — DA-20 makes composing a response out
of another domain's Resource legal outright.

They do not bind framework wiring, and the test is whether a file names a class **to wire it or to
call it** — a registry names classes so the framework can find them, and consumes nothing.
`app/Console/Kernel.php` naming a command **or a job** to schedule it, `app/Http/Kernel.php` naming
middleware in `$middlewarePriority` and `$middlewareAliases`, `routes/` naming middleware and the
controllers its endpoints dispatch to, a service provider binding an interface or mapping an event
to its listeners, and a Filament resource naming the model it is built on are all wiring. Those are
not business logic crossing a boundary. A provider that calls into another domain's model or
repository from `boot()` is, and the rules apply to it.

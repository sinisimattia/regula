# Coding standards

This is the single authority on how code is written in this repository. It is written for people
first and for AI assistants second — both read it, and both are held to it.

If something here contradicts a comment, an agent, a README, or a habit, **this wins.** If you
think a rule is wrong, see [Changing a rule](#changing-a-rule). Do not route around it.

## Where things are written down

Five places, each with a test for what belongs in it.

| Location | Holds | Test |
|---|---|---|
| `docs/standards/` (here) | How we write code — form | "Violating this is a defect in the code's shape" |
| `docs/business-logic/` | How the **platform** behaves; concepts no single domain owns | "If you deleted any one domain, this would still be true" |
| `app/Domains/X/README.md` and `X/Docs/` | How **that** domain behaves, business rules included | "You'd need this to work in that domain" |
| `docs/infrastructure/` | How code that belongs to **no domain** works: configuration, drivers, framework extensions | "This explains `config/`, a driver or a framework override, not a business rule" |
| `docs/in-development/` | Work not yet shipped | "This describes work in flight" |

Account rules, token lifetimes, admin access and the like are **domain** business logic and live in
that domain's `Docs/`. `docs/business-logic/` is only for what spans the whole platform, and does
not exist until the platform has such a concept — create it the day it does.

The first four are permanent. `docs/in-development/` is not: a design or a task breakdown lives
there while the work is in flight, and when the work ships, whatever in it is still true moves into
the domain's `Docs/` and the folder goes. The same is true of the working records beside it —
`docs/tasks/` holds breakdowns waiting to be published, `docs/audits/` holds what an audit found on
the day it ran. None of the three is documentation, none of them is maintained once the work is
done, and **no comment in `app/` or `tests/` may cite one** (PHP-13). A comment pointing at a
document that has since been deleted is worse than no comment. Code that *writes* such a report is a
different thing and is fine.

### What a documentation page is held to

A page is finished when someone who has never opened the domain can read it and correctly predict
what a given request will do. Until then it is a draft, whatever its length.

- **Lead with the question the reader arrived with** — "why was my login refused?", "what happens
  to my tokens when I refresh?", "when is an account really gone?" — not with a list of classes.
- **Explain the rule, then show it failing.** A rule on its own reads as arbitrary; the scenario
  that motivated it is what makes it stick.
- **Use a table for anything with more than two dimensions.** Prose describing a grid is how people
  misread a grid.
- **Say what happens, not what the class is called.** Names change; behaviour is what a reader
  needs to predict. Where a name genuinely matters, link it rather than describing it (PHP-20).
- **Record the alternatives that were rejected**, briefly, so nobody spends an afternoon
  rediscovering why the obvious one is worse.

## The files

| File | Covers |
|---|---|
| [`php.md`](php.md) | Types, promotion, naming and class suffixes, PHPDoc, comments |
| [`domain-architecture.md`](domain-architecture.md) | Domain structure, the public surface, services |
| [`entities.md`](entities.md) | Entity design, `store` / `get` / `delete` semantics |
| [`http.md`](http.md) | Controllers, Form Requests, API Resources, routes, exceptions |
| [`persistence.md`](persistence.md) | Models, repositories, migrations, factories |
| [`testing.md`](testing.md) | Test conventions |
| [`tasks.md`](tasks.md) | Writing tasks and mapping them onto tracker fields |
| [`git.md`](git.md) | Branching, commits, pull requests, the Boy Scout rule |

## What this governs

| Area | Governed by |
|---|---|
| `app/Domains/` | Everything, `domain-architecture.md` included |
| `app/Http/` | `php.md`, `http.md`, `testing.md`, `git.md`, plus `domain-architecture.md`'s **public-surface** rules and DB-01 (no Eloquent in controllers). It is a shared layer with a defined remit — see [`http.md`](http.md) |
| `app/Filament/` | `php.md`, `git.md`, and **DA-22** — it may read a domain's models, but triggers behaviour through service interfaces. Excluded from test coverage by project policy |
| `app/Models/` | `php.md`, `git.md`, `domain-architecture.md`'s **public-surface** rules, and `persistence.md`'s model rules (DB-05 to DB-07, DB-09, DB-10): a model is a model wherever it lives |
| Everything else in `app/` | `php.md`, `git.md`, and `domain-architecture.md`'s **public-surface** rules |

**One exception cuts across that table.** `domain-architecture.md` contains two different kinds of
rule, and they have different reach:

- **Structural rules** — folder shape, service naming, provider bindings (DA-05 to DA-09, DA-16,
  DA-17) — describe how a *domain module* is built, and apply only inside `app/Domains/`.
- **The public surface** — DA-01 to DA-04 and DA-10 to DA-12 — protects the domain being reached
  *into*, not the code doing the reaching. It therefore binds **all of `app/`**: a boundary that
  holds against other domains but not against the rest of the codebase is not a boundary. Where
  the consumer lives has never been the question — a controller that injects an interface-less
  service pins that service's internals exactly as firmly as another domain would.
- **Listener ordering** — DA-23 — binds wherever listeners are registered, which is
  `app/Providers/EventServiceProvider.php` as much as any domain's provider: the order it protects
  is written there.

It does **not** bind framework wiring, and the test is whether the file **names a class to wire it
or to call it**. A registry names classes so the framework can find them; it consumes nothing.
A service provider binding an interface, or mapping an event to its listeners, is a registry; the
same provider calling into another domain's model or repository from `boot()` is not.
`app/Console/Kernel.php` must name a domain's command **or job** class to schedule it, and dispatches
jobs with `$schedule->job(...)`. `app/Http/Kernel.php` is the same thing for middleware: a domain's
middleware is named in `$middlewarePriority` and `$middlewareAliases`, and it can never stop being
so. `routes/` is a registry by the same
test, naming middleware and the controllers each endpoint dispatches to. A Filament resource is
built on an Eloquent model by definition — but that is a model *exemption*, not a blanket one, and
`app/Filament/` is held to DA-22 by its own check for everything that is not a model read.

None of those is business logic reaching across a boundary, and flagging them would teach people to
ignore the report. Stating the test rather than listing the files matters: an exclusion that is a
list gets read as an accident of whatever the tool happens to scan, and the next registry to appear
has no principle to be judged against.

Knowing what is **not** governed matters as much as knowing what is. Code outside the
structural canon's reach is not a backlog of violations to fix on sight.

## Rule IDs

Every rule has a permanent ID — `DA-03`, `ENT-07`, `GIT-02` — built from its file's prefix.

| Prefix | File |
|---|---|
| `PHP` | `php.md` |
| `DA` | `domain-architecture.md` |
| `ENT` | `entities.md` |
| `HTTP` | `http.md` |
| `DB` | `persistence.md` |
| `TEST` | `testing.md` |
| `TASK` | `tasks.md` |
| `GIT` | `git.md` |

Cite the ID in review comments, audit findings and tickets, so a disagreement is a lookup rather
than an argument. IDs are never reused: a withdrawn rule keeps its number and is marked withdrawn
with the date and the reason.

## The non-negotiables

The rest of this directory is detail. These are the ones that cost the most when they are missed.

1. **A service crossing a domain boundary has an interface.** No interface means the service is
   internal to its domain and nothing outside may reference it. — [DA-03](domain-architecture.md)
2. **Every service and repository interface is explicitly bound** in its own domain's service provider.
   Autowiring hides the wiring. — [DA-17](domain-architecture.md)
3. **A repository is injected only into its own domain's services.** Nothing else, ever. —
   [DA-04](domain-architecture.md)
4. **Entities, not arrays**, for domain data. — [ENT-01](entities.md)
5. **`store` / `get` / `delete`** is the persistence vocabulary. — [ENT-02](entities.md)
6. **Events are dispatched from inside the service method**, never the HTTP layer. —
   [DA-19](domain-architecture.md)
7. **Never commit unless you were asked to.** — [GIT-02](git.md)
8. **Never modify anything under `vendor/`.** No exceptions.
9. **A docblock says what a thing is, never why it was built that way** — and usually says nothing,
   because the name already did. Reasoning lives in the domain's `Docs/`. —
   [PHP-12](php.md), [PHP-18](php.md), [PHP-19](php.md)
10. **The application's name lives only in `composer.json`.** Everything else derives it, and
    renaming the application is a one-line change. — [PHP-22](php.md)
11. **Code never names a backend.** Queues, files, cache, logs and mail are reached through logical
    names, and env picks the driver, so AWS, self-hosted and local run the same code. —
    [PHP-23](php.md)
12. **A rule a machine can check is enforced by a machine, not by review.** Its check lives in
    `php artisan canon:check` (or php-cs-fixer, when the fix is automatic) and runs in CI. People
    and agents review only what needs judgement. — [How the rules are enforced](#how-the-rules-are-enforced)
13. **Colours and fonts live only in `config/theme.php`.** Every view, email and the admin panel
    derives them, and re-branding the application is an edit to that one file. — [PHP-26](php.md)

## How the rules are enforced

Three layers, each owning something the others cannot do.

| Layer | Owns | Command |
|---|---|---|
| **PHP-CS-Fixer** | Formatting. Auto-fixes, so you never review it. | `composer lint:fix` |
| **PHP Insights** | Aggregate quality — typing, complexity, architecture. Thresholds ratchet upward and never down. | `composer insights` |
| **Canon check** | Every rule a machine can check, one violation at a time. Fails the build on any single divergence. | `php artisan canon:check` (or `composer canon:check`) |

All three run on every pull request, and CI also checks the branch name (GIT-01).

**If a rule can be checked mechanically, it is.** A check is consistent where a reviewer is not,
costs nothing to run, and fails the build before anyone reads the change. A rule and its check are
added together. Only what genuinely needs judgement is left to a person or to
`code-standards-specialist`.

The canon check exists because a score cannot catch one bad name: a single new violation does not
move a percentage across a whole codebase. `php artisan canon:check` lists every rule it enforces,
with its ID. It is dev tooling: it lives in `insights/`, ships only with dev dependencies, and is
never part of a production build. To prove a new check, run it against a fixture tree with
`--root=<dir>`.

Debt can be **grandfathered per rule** in `config/insights.php`, and every entry carries a comment
naming what it is. The lists start empty and are meant to stay that way. Adding to one is a decision
— take it deliberately, and raise the Technical Task that clears it.

## Changing a rule

A canon nobody can change is a canon people work around — and a canon people work around soon
exists in several drifted copies, none of them authoritative.

The path is deliberately short:

1. **Flag it.** Whoever hits the rule says so — in the pull request, or by stopping and asking.
   An AI assistant that finds a rule wrong for the task must stop and say so rather than
   improvise a variation.
2. **Decide.** The maintainer decides. Not the assistant, and not by silent precedent.
3. **Change it here, in the same pull request as the code that prompted it**, with the reason
   recorded in the rule itself. A rule whose reason is written down survives the person who
   wrote it.
4. **Add or update its check in the same pull request**, if a machine can check it (non-negotiable
   12). A new rule without its check is only half done.

Nothing else is required. No proposal document, no separate branch.

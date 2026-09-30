# CLAUDE.md

Guidance for Claude Code (claude.ai/code) working in this repository.

This file is a **router**, not a rulebook. The rules live in `docs/standards/`, which humans and
assistants both read — one copy, one place to change it.

## Read this before writing code

| You are about to | Read |
|---|---|
| Write or change any PHP | [`docs/standards/README.md`](docs/standards/README.md) — index and non-negotiables |
| Name a new class | [`docs/standards/php.md`](docs/standards/php.md) — PHP-17, the suffix each kind carries |
| Work inside `app/Domains/` | [`docs/standards/domain-architecture.md`](docs/standards/domain-architecture.md) — **in full** |
| Touch entities or service signatures | [`docs/standards/entities.md`](docs/standards/entities.md) |
| Add or change an endpoint | [`docs/standards/http.md`](docs/standards/http.md) |
| Touch migrations, models, repositories | [`docs/standards/persistence.md`](docs/standards/persistence.md) |
| Style a view, an email or the admin panel | [`docs/standards/php.md`](docs/standards/php.md) — PHP-26, colours and fonts come only from `config/theme.php` |
| Write tests | [`docs/standards/testing.md`](docs/standards/testing.md) |
| Branch, commit, or release | [`docs/standards/git.md`](docs/standards/git.md) |
| Write or publish tasks | [`docs/standards/tasks.md`](docs/standards/tasks.md) |

The canon is the authority. Where your judgement differs from it, **the file wins**. If a rule seems
wrong for the task, say so and stop — do not improvise a variation. `docs/standards/README.md`
explains how a rule gets changed.

## Project Overview

A Laravel 13 PHP backend with domain-driven design, a Filament
admin panel, Sanctum API authentication, and AWS CDK infrastructure. It starts deliberately empty:
the foundation, the canon and the tooling are in place, and the product is built on top of them.

## Common Commands

All commands run inside the `app` service container.

```bash
docker compose up -d --build     # Start development environment
docker compose exec app bash     # Enter the app container

composer test                    # Run tests without coverage
composer test:coverage           # Coverage reports in coverage/html and coverage/xml
./vendor/bin/phpunit --filter TestClassName
./vendor/bin/phpunit tests/Unit/Application/Path/ToTest.php

composer lint:check              # Check code style
composer lint:fix                # Auto-fix code style (never use Pint)
composer insights                # Static analysis — aggregate quality, ratchets upward
php artisan canon:check          # Hard gate: every mechanical rule in docs/standards/, per violation

php artisan migrate
php artisan tinker
```

## Architecture

**Domains** (`app/Domains/`) — the DDD modules. Structure, the public surface and service rules are
in [`docs/standards/domain-architecture.md`](docs/standards/domain-architecture.md).

Active:

- **Core** — foundational types every domain builds on, and nothing else. Today that is
  `Contracts/Identification`, the base class for typed entity IDs (ENT-05).
- **Auth** — registration, login and logout, the dual Sanctum token pair and its refresh, email
  verification, password reset, account deletion, and who may enter the admin panel.

**Key integrations** — Sanctum (API tokens), Spatie permission (admin roles; `Super Admin` bypasses
every gate), Filament 5 admin (`app/Filament/`, Livewire 4), AWS SQS and CloudWatch (queues and
logs, provisioned by `cloud/aws/`), PostgreSQL 17 locally and in CI.

## Agent delegation

Agents are **subject-matter specialists**. Each understands an area and serves any phase of work —
building, reviewing, exploring, debugging, or answering a question. Reach for the one whose subject
matches; asking a specialist how something works is a legitimate use, not a misuse.

| Subject | Specialist |
|---|---|
| The HTTP layer — endpoints, controllers, Form Requests, API Resources, routes, middleware | `http-specialist` |
| Domain modules — structure, services, the public surface, creating a new domain | `domain-specialist` |
| The database — migrations, models, relationships, factories, seeders, query performance | `database-specialist` |
| `app/Filament/` | `filament-specialist` |
| Tests — writing, auditing, running, fixing | `test-specialist` |
| Docker and container infrastructure | `docker-specialist` |
| Breaking down work, writing tasks, publishing them to the tracker | `task-specialist` |
| Reviewing PHP against the standards | `code-standards-specialist` |

**AWS CDK / TypeScript under `cloud/aws/`** has no specialist — handle it directly, and skip the PHP
review agents entirely. Validate with `npm run build`, `npm run lint`, `npx cdk synth` / `npx cdk diff`.
`cloud/aws/test/` is empty by design.

### Reviewing work

Run `composer lint:fix` first — it auto-fixes formatting, so never review those rules by hand.
Then `php artisan canon:check`, which enforces every rule a machine can check: never review those
by hand either. A rule that can be checked mechanically gets a check, not a review comment
(non-negotiable 12 in `docs/standards/README.md`).

Then `code-standards-specialist` for the standards review, and `test-specialist` after any feature or
fix.

**The instance that built something never reviews it.** A fresh instance, or a different specialist,
does the review pass.

Skip the review entirely when the change is trivial and mechanical — a comment, a typo, a config
value, a single-line edit with no behavioural effect.

`app/Filament/` gets `code-standards-specialist` only. Never write, delegate or run tests for it.

## Reach for these without being asked

People describe work in ordinary language and will not know these exist. Recognise the intent and
act on it — do not wait to be asked for a skill by name, and do not explain the skill before using
it. Say which one you are using in a single line, then use it.

| They say something like | Do |
|---|---|
| "I need to work on <KEY>-1234" · pastes a ticket key · "let's pick this up" | **Use `/ship`** — it handles intake, questions, planning, build and release |
| "let's build X" · "I want to add Y" with no ticket | Brainstorm and design first. Offer `/ship` once there is a ticket |
| "review the Auth domain" · "check this branch" · "is this code alright?" | **Use `/audit`** |
| "break this down" · "turn this into tickets" · "plan this epic" | **Use `task-specialist`**, then `/create-task` to publish |
| "what's our convention for X?" · "where should this live?" · "how do we name Y?" | **Answer from `docs/standards/`**, citing the rule ID. Never answer from memory |
| They are about to write PHP | The specialists and the review flow above |

Two judgement calls worth getting right:

- **Use it directly when the request plainly matches.** Asking "shall I use a skill?" puts the
  burden back on someone who does not know what the skills are.
- **Offer, don't impose, when the match is partial.** "This looks like a `/ship` job — want me to
  run it properly?" is better than silently starting a five-stage workflow they did not ask for.

If a skill is unavailable, carry out the work yourself rather than stopping. The gates in
`docs/standards/` are what matter; the skills are how we reach them reliably.

## Operational rules

These are about how to operate here, not how code should look. The rest is in `docs/standards/`.

- **Never modify anything under `vendor/`.** No exceptions. It is Composer-managed and edits are lost
  on the next install. If a vendor package needs changing, use an application-level override — a
  service provider, a decorator, a middleware wrapper — or open a PR on the package.
- **Never commit unless explicitly asked** (GIT-02). Not after finishing a task, not after tests pass.
- **Run tests inside the docker `app` container** (TEST-07).
- **Don't create documentation files unless asked** — except a domain's `README.md` and `Docs/`,
  which are required (DA-18).

---

<laravel-boost-guidelines>
> **Manually trimmed.** `php artisan boost:install` regenerates this block in full;
> re-apply this trim afterwards. Generic Laravel/PHP boilerplate and anything now covered by
> `docs/standards/` was removed — only project-relevant and version-specific rules are kept.

## Stack versions

php 8.4 · laravel/framework 13 · filament/filament 5 · livewire/livewire 4 · laravel/sanctum 4
spatie/laravel-permission 6 · phpunit/phpunit 12

## Conventions

- Match existing code: before writing anything, find similar functionality and replicate its
  patterns, structure and style. New code must be indistinguishable from what is already there.
- Check sibling files for structure and naming; reuse existing components before writing new ones.
- Stick to the existing directory structure; don't add base folders or change dependencies without
  approval.
- Be concise in explanations.

## Laravel Boost MCP tools

Boost is an MCP server for this application — use it instead of guessing:

- **`search-docs`** — version-specific docs for the installed packages. Use it **before** other
  approaches for anything Laravel-ecosystem. Pass multiple broad topic queries at once
  (`['rate limiting', 'routing']`); don't include package names or versions, they're sent
  automatically. Supports auto-stemming, AND logic across words, and `"quoted phrases"`.
- **`tinker`** — execute PHP to debug or query Eloquent. **`database-query`** — read-only DB access.
- **`database-schema`**, **`list-artisan-commands`** (check parameters before calling a command),
  **`list-routes`**, **`get-config`**, **`last-error`**, **`read-log-entries`**, **`browser-logs`**.
- **`get-absolute-url`** — use whenever sharing a project URL.

## Framework specifics

This app runs Laravel 13 but **uses the pre-11 application skeleton**, deliberately. Don't reach for the Laravel 11+ layout you'd expect from the version number — the old files are the real
ones here:

- `bootstrap/app.php` only creates the app instance and binds the kernels. It is **not** the
  configuration entry point Laravel 11+ documents. Middleware → `app/Http/Kernel.php`, exceptions →
  `app/Exceptions/Handler.php`, commands/schedule → `app/Console/Kernel.php`, rate limits and route
  groups → `app/Providers/RouteServiceProvider.php`.
- Eloquent casts use `protected $casts = [];` in every model (DB-07). The Laravel 11+ `casts()` method
  works, but nothing here uses it — match the surrounding code rather than introducing a second style.

## Filament 5

Server-driven UI in PHP on Livewire 4 + Alpine + Tailwind. Resources live in `app/Filament/Resources/`,
pages under `<Resource>/Pages/`. Generate with the Filament Artisan commands (`list-artisan-commands`),
always `--no-interaction`. The full v5 playbook is in the `filament-specialist` agent — the essentials:

- **Forms are Schemas.** `form(Schema $schema): Schema` returning `->components([...])`
  (`Filament\Schemas\Schema`). `Filament\Forms\Form`/`Infolists\Infolist` are gone.
- **Namespaces:** layout components (`Section`, `Grid`, `Fieldset`, `Tabs`) are under
  `Filament\Schemas\Components`; fields under `Filament\Forms\Components`; columns under
  `Filament\Tables\Columns`.
- **⚠️ Layout:** `Section`/`Grid`/`Fieldset` default to ONE column in v5. Put `->columns(1)` on the
  **form root** to stack top-level sections full-width (what the panel does), or `->columnSpanFull()`
  on a section in a deliberately multi-column form. Never touch nested sections inside a `->columns(2|3)` grid.
- **Static nav props** must match the v5 base type: `protected static string | \BackedEnum | null
  $navigationIcon`; `string | \UnitEnum | null $navigationGroup` (else a fatal at boot).
- **Code editor** is native: `Filament\Forms\Components\CodeEditor` + `->language(Language::Yaml|Html)`.
- **Assets are tracked** in `public/js|css/filament`. After a version bump, or if the panel JS breaks
  (Alpine `isProcessing is not defined`), run `php artisan filament:assets` and commit. Deploy:
  `filament:optimize` / `filament:cache-components`.
- `->relationship()` for relation-backed fields:
  `Select::make('user_id')->label('Author')->relationship('author')->required()`.
- Production access requires the `FilamentUser` contract.
- **No tests** — `app/Filament/` is excluded from coverage (TEST-05); the gate is manual QA.

## Livewire 4

Only relevant here as the layer underneath Filament; this app does not author standalone Livewire
components. If you ever do: components live in `App\Livewire`, use `wire:model.live` for real-time
binding (`wire:model` is deferred by default), `$this->dispatch()` for events, and `wire:key` inside
loops. Alpine ships with Livewire — don't include it separately. Validate and authorize inside
Livewire actions: they are ordinary HTTP requests.
</laravel-boost-guidelines>

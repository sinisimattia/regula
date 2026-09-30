# PHP

Language-level conventions. These apply to **every** PHP file in the repository, including the
areas that sit outside the structural canon.

## What you never have to think about

`composer lint:fix` (PHP-CS-Fixer, configured in `.php-cs-fixer.dist.php`) already corrects
`declare(strict_types=1)`, PSR-12 and brace style, single-quoted strings, unused imports,
`get_class()` → `::class`, blank lines and array indentation, superfluous `@param`/`@return` tags
(PHP-10) and the position of `@throws` (PHP-25).

Run it; don't review it. Nothing in this file repeats what the formatter fixes for you.

**Never use `vendor/bin/pint` or Pint directly.** PHP-CS-Fixer is the formatter.

## Types and signatures

**PHP-01 — Every method and named function has an explicit return type.** Closures and arrow
functions are exempt: they are local, and Filament and the container inject some of them by
parameter name.

**PHP-02 — Every parameter of a method or named function has a type hint.** Closures are exempt,
as under PHP-01.

**PHP-03 — Nullable (`?Type`) only where null is genuinely reachable.** If the method throws
instead of returning null, the return type is not nullable and the PHPDoc carries `@throws`. A
nullable return that can never be null forces every caller to write a check that can never fire.

```php
// The method throws when the user is missing, so the type is not nullable.
public function getUserById(UserId $userId): User;
```

**PHP-04 — Annotate what the type system cannot express.** PHP has no generics, so an
`array` return type tells the reader nothing. Say what is in it.

```php
/** @return Invoice[] */
public function getInvoicesForCustomer(CustomerId $customerId): array;

/** @param UserId[] $userIds */
public function deleteUsers(array $userIds): void;

/** @var Customer|Supplier $counterparty */
```

Array shapes are worth writing where a payload has a fixed structure.

**PHP-05 — Use constructor property promotion.**

```php
public function __construct(
    private readonly UserRepositoryInterface $userRepository,
    private readonly InvoiceServiceInterface $invoiceService,
) {
}
```

No empty `__construct()` with zero parameters unless it is deliberately private.

## Naming

**PHP-06 — A name says what it holds, in complete words.** This applies to variables, properties,
parameters, database columns, route parameters and request/response fields alike.

- **Specific:** the name explains itself without the surrounding lines. `$previousComment` not
  `$previous`. `$storedComment` not `$stored`. `$customerInvoiceMap` not `$map`. `$mentionedUserId`
  not `$id`. `$userModels` not `$models`.
- **Unabbreviated:** `preferredLanguage` not `prefLang`, `description` not `desc`, `configuration`
  not `cfg`, `quantity` not `qty`, `message` not `msg`. The only exceptions are conventional
  single letters with a tiny scope (`$e` in a `catch`, `$i` in a counting loop) and acronyms that
  are words in their own right (`id`, `url`, `html`).
- **Same word in every layer:** a concept keeps one full name from the request field to the
  column. Only the casing changes: `preferred_language` in the request and the column,
  `$preferredLanguage` in PHP.

The test: read the line on its own, out of context. Do you know what the name holds, without
decoding it?

**PHP-07 — Enum cases are TitleCase**: `FavoritePerson`, `Monthly`, `AwaitingReview`.

**PHP-08 — Prefer named arguments for calls with two or more arguments.** Constructing one of our
own classes with two or more arguments always uses them; the canon check enforces that part.

```php
new Reservation(counter: $counter, amount: $amount, actor: $actor);
```

A single obvious argument is fine positional. The point is the call site that reads correctly
without opening the constructor — and, in passing, a call that does not silently break when
someone reorders parameters.

**PHP-21 — A method that sets something and hands the object back is `with{Noun}(...): self`.**

Not `apply{Noun}()`, not `set{Noun}()`, and not `void`. The house flavour is fluent and it is
the Laravel idiom — `withHeaders()`, `withToken()`, `withAttributes()` — and the house follows it, so
a second spelling for the same idea costs a reader a lookup to find out
whether it returns anything.

```php
// No.
public function applyDiscount(Discount $discount): void;

// Yes.
public function withDiscount(Discount $discount): self;
```

**This is about setters on an object, not operations a service performs.** The test is whether the
method's only job is to set state on the object you called it on — if it is, it returns that object.
`applyRefund()` on an `InvoiceService` and `applyDelta()` on a repository are out of scope:
they act on something else, and `withRefund(): self` would be nonsense.

`self` covers both shapes, and which one you get follows from the class rather than the name: a
mutable class sets the property and returns `$this`; a `readonly` one returns a new instance
(`Money::withAmount()`). Callers that chain behave the same either way. Callers that rely on
the *caller's* instance changing do not — so where in-place mutation is the mechanism rather than a
convenience, say so on the method, because the name alone cannot. On a non-`final` base with
subclasses, `static` is the more precise annotation and is preferred.

**PHP-17 — A framework class carries the suffix its kind is known by.** The name says what the
class *is* before anyone opens it, and it makes a whole kind findable in one search. `php artisan
make:` (PHP-16) already produces most of these correctly — keep the name it gives you.

| Kind | Name | Example |
|---|---|---|
| Controller | `{Noun}Controller` | `AuthController` |
| Form Request | `{Action}Request` | `ResetPasswordRequest` |
| API Resource | `{Noun}Resource`, `{Noun}ResourceCollection` | — |
| Service | `{Noun}Service`, `{Noun}ServiceInterface` | `AccountService` (DA-16) |
| Repository | `{Noun}Repository`, `{Noun}RepositoryInterface` | `UserRepository` (DB-01) |
| Job | `{Verb}{Noun}Job` | — |
| Listener | `{WhatItDoes}On{WhatHappened}Listener` | — |
| Subscriber | `{Noun}Subscriber` | — |
| Console command | `{Verb}{Noun}Command` | — |
| Notification | `{WhatHappened}Notification` | `CustomResetPasswordNotification` |
| Mailable | `{WhatHappened}Email` | `VerificationEmail` |
| Policy | `{Model}Policy` | `UserPolicy` |
| Observer | `{Model}Observer` | — |
| Service provider | `{Domain}ServiceProvider` | `AuthServiceProvider` |
| Exception | `{WhatWentWrong}Exception` | `UserNotFoundException` |
| Broadcast channel | `{Noun}Channel` | — |
| Validation rule | `{WhatItChecks}Rule` | — |
| Cast | `{Noun}Cast` | — |
| Test | `{Subject}Test` | `AccountServiceTest` |

A dash means the repository has no example yet — the pattern still holds for the first one.

Mailables are `*Email` here rather than Laravel's unsuffixed default. That is the house name and it
stays; the point of the rule is that every mailable is spelled the same way, not that we match the
framework's own choice everywhere.

**The kinds that deliberately take no suffix.** These name the thing itself, and a suffix would
subtract from them. Do not "fix" one.

| Kind | Why | Example |
|---|---|---|
| Entity, value object, model | The name *is* the concept. A `UserModel` sitting beside a `User` entity helps no one; the folder already says which is which | `User`, `UserId` |
| Enum | Same reason. `TokenAbility` reads at the call site the way a type should | `TokenAbility`, not `TokenAbilityEnum` |
| Event | A past-tense statement of what happened | `UserRegistered` |
| Middleware | Laravel's own read as instructions, and ours match | `SetLocale`, `RedirectIfAuthenticated` |
| Trait | Reads as a capability where it is `use`d | `UsesOrderedQueue` |
| Contract in `Contracts/` | Names a **role**, and is exempt from `*Interface` for the same reason (DA-12) | `Identification` |
| Contract implementation | Takes the role's name, not `*Service` (DA-16) | — |
| Filament page | Keep whatever the Filament generator produced | `Dashboard` |

`php artisan canon:check` enforces this both ways and fails the build on a single new violation: a
framework kind without its suffix, by folder or by parent class, and a no-suffix kind that carries
one (`StatusEnum`, `UserEntity`, `AuditTrait`).

An exception to the rule is grandfathered in `config/insights.php`, file by file, each entry with a
comment naming what it is. It is debt, not precedent: rename one when a change already has you in
that file (GIT-05), and leave it when renaming would spill past the change (GIT-06).

## Documentation and comments

**PHP-09 — PHPDoc contracts belong on the interface, never on the implementation.** `@throws`
above all, plus anything the signature cannot express. The interface is the contract; the
implementation inherits it.

When you find a docblock on an implementation method, look at the interface. If the documentation
is missing there, move it. If it is already there, delete the duplicate — two copies drift, and
the reader has no way to tell which is current.

**PHP-10 — `@param` and `@return` are superfluous when they only restate the signature. Omit
them.** They add a second place to be wrong.

```php
// Noise — the signature already says all of this.
/**
 * @param UserId $userId
 * @return User
 */
public function getUserById(UserId $userId): User;

// Worth writing — none of this is in the signature.
/**
 * @return Invoice[]
 * @throws CustomerNotFoundException
 */
public function getInvoicesForCustomer(CustomerId $customerId): array;
```

**PHP-25 — `@throws` is the last tag in a docblock.** Whatever else the docblock holds comes first,
so a reader always finds what can go wrong in the same place.

```php
/**
 * @param  array<string, mixed>  $config
 *
 * @throws InvalidArgumentException
 */
```

**PHP-11 — Private and protected helpers document on the method itself.** There is no interface
and no external caller; only the class cares, so the documentation belongs where the method is.

**PHP-12 — A docblock says *what* a thing is. It never says why it was built that way.**

One plain sentence naming the thing, or no docblock at all. Not why this class rather than another,
not why it lives here, not which alternative was rejected, not what constraint forced the shape.

```php
// No. Every line after the first justifies a decision.
/**
 * Somebody created an account.
 *
 * Fired from the service rather than the controller, because the admin panel creates
 * users too. Queued listeners read it, so it carries the entity rather than the
 * model.
 */
class UserRegistered

// Yes. The name is already the sentence.
class UserRegistered
```

**Why not "comments explain *why*".** That licence is what produces four-paragraph docblocks arguing
for their own code — on classes whose names already say everything a reader needs. Rationale on a
class attracts more rationale; it is also the part that goes stale first, because the constraint
that forced a decision changes without the decision changing. The reasoning is worth keeping. It is not worth keeping *there*.

**What a docblock may still carry**, because none of it is rationale — it is fact the signature
cannot express: `@throws`, the element type of a collection (PHP-04), `@var`, and a `{@see}` link
(PHP-20).

**Inline comments keep a narrow licence.** A `//` at the line it concerns may say why, where the
line is genuinely non-obvious and the reason is not reconstructable from the code. Rare, and one or
two sentences. The test is whether a reader who deleted the comment would make the wrong change.

```php
// Good — sits on the line it explains, and the code cannot say it.
// Not guarded: a disk that cannot produce a URL is a misconfiguration and must fail loudly.

// Useless — restates the line below it.
// Loop over the users
```

Anything longer than that, and any reasoning that belongs to a design rather than to a line, goes
in the domain's `Docs/` (DA-18) — which under this rule is the only home reasoning has.

Do not write comments to brief an AI assistant. A comment that reads like a prompt is worse for the
person debugging at 2am than no comment at all.

**PHP-13 — No ticket references in source.** Never a ticket key (`<KEY>-1234`), a Jira
or GitHub URL, a sprint, or a task name in a docblock, an inline comment, a string literal or a
test.

Ticket IDs rot. The reader has the code, not the tracker, and a stale key is worse than no key —
it sends someone to a closed ticket that explains nothing. Write the *reason* instead, so the
comment stands on its own.

The same applies to anything else that only exists while the work is in flight: a release number
("R3"), an epic, or a path into `docs/in-development/`. Those documents are deleted when the work
ships, and a comment pointing at one sends the reader to a file that is no longer there. Link to
the domain's `Docs/` instead, which is written to outlive the work (PHP-20).

The one exception is outstanding work, where a key is encouraged because it points at something
still open:

```php
// TODO(<KEY>-1234): drop this shim once the billing API returns the new shape
```

**PHP-18 — A docblock has to earn its place. The default is not to write one.**

Write one where it says something the name, the signature and the folder cannot: an `@throws`, the
element type of a collection, an invariant a caller would otherwise have to find by reading the
body. Anything else is noise — a reader skims past it, and the next author has to keep it true.

A non-abstract entity, event, enum or exception class whose name already states what it is takes no
docblock.
`UserRegistered`, `UserNotFoundException`, `TokenAbility` — the name is the sentence, and a
docblock repeating it in longer words earns nothing.

The temptation is strongest on the public surface, which is exactly where it costs most. A class
called `UserNotFoundException`, sitting in `Exceptions/`, does not need a docblock explaining that
a user was not found.

**PHP-19 — One or two sentences is the ceiling, wherever the comment sits.**

A comment is a signpost, not an essay. Thirty lines above a class is a documentation page filed
where nobody looks for one, indexed by nothing and reviewed by nobody — and under PHP-12 most of
what would fill those lines does not belong in the file at all.

Reasoning goes in the domain's `Docs/` (DA-18), or in `docs/infrastructure/` for code that belongs
to no domain: configuration, drivers, framework extensions. Not "goes there when it gets long": a
length limit on rationale simply produces compressed rationale. A `{@see}` link or a named page is
what the code carries instead; the reader who wants the argument follows it.

The test: read the file with every comment deleted. What you then have to guess at is what the
comment should say, and nothing beyond it.

`config/` is held to this like any other code, with two exceptions kept as upstream ships them: the
`|---|` banner blocks above each option, and the config files a package published (listed in
`config/insights.php`).

**PHP-20 — Point at the other code; do not retell it.** When a comment names another class, method,
contract or document, link it — `{@see \App\...\Thing::method()}` in a docblock, the path in an
inline comment — instead of describing what it does.

```php
// Good — one hop, and it cannot go stale.
/** Bound over Filament's own joiner in {@see \App\Providers\AppServiceProvider}. */

// Bad — a second description of that binding, which nobody will come back to update.
```

A paraphrase is a copy, and copies drift apart. A link stays right because there is only one of it,
and an IDE will follow it.

## Framework

**PHP-14 — Curly braces always**, even for a single-line body.

**PHP-15 — `env()` is called only inside `config/`.** Everywhere else read `config('app.name')`.
Configuration is cached in production; `env()` outside `config/` returns null once it is.

**PHP-23 — Code names what it needs, never the backend behind it.** The same code runs on AWS, on a
self-hosted box and on a laptop. The only thing that differs between them is env, so the choice of
backend belongs in `config/` and nowhere else.

| Service | Code uses | Chosen in `config/` by |
|---|---|---|
| Queues | the default connection, or `high`, `low`, `default-ordered` | `QUEUE_DRIVER` |
| Files | the default disk (`Storage::put(...)`) | `FILESYSTEM_DISK` |
| Cache | the default store (`Cache::get(...)`) | `CACHE_STORE` |
| Logs | the default channel (`Log::info(...)`) | `LOG_STACK` |
| Mail | the default mailer | `MAIL_MAILER` |
| Broadcasting | the default connection | `BROADCAST_CONNECTION` |

```php
// Good — lands wherever QUEUE_DRIVER points: sync on a laptop, the jobs table self-hosted, SQS on AWS.
ExportReportJob::dispatch($report)->onConnection('low');

// Bad — works on AWS only, and a self-hosted install cannot fix it from env.
ExportReportJob::dispatch($report)->onConnection('sqs');
Storage::disk('s3')->put($path, $contents);
Log::channel('cloud')->error($message);
```

A driver-specific class (`SqsClient`, `S3Client`) is allowed only inside the drivers that wrap one,
`app/Queue/` and `app/Logging/Drivers/`, and in the `extend()` calls that register a driver. When
code genuinely needs a second store or disk, add a named entry to `config/` whose driver comes from
env, and name that.

**PHP-24 — Ask the application which environment it is in; never compare an environment name.** Use
`app()->isProduction()`, `app()->isLocal()`, `app()->runningUnitTests()` and the other `is…`
methods. Code never compares an environment name or passes one to `app()->environment(...)`. A
string that merely spells one, such as an enum case `Production = 'production'` in a deployment
domain, is a domain word and is fine. `config/` is where the name is set, so it is
the one exception. When there is no `is…` method for the question, such as "is this staging?", add a
config flag for it.

```php
// Good
if (app()->isProduction()) { ... }

// Bad — a typo here is a silent `false`, and a renamed environment breaks nothing visibly.
if (app()->environment('production')) { ... }
if (config('app.env') === 'local') { ... }
```

Tests may set the environment they need; the rule is about application code.

**PHP-16 — Generate Laravel files with `php artisan make:`**, always with `--no-interaction` and
the right options. Check `list-artisan-commands` for the parameters rather than guessing.

**PHP-22 — The application's name is written in exactly one place: the vendor part of the `name`
in `composer.json`.** Renaming the application is a one-line change. Everything else derives the
name:

| Where | How it gets the name |
|---|---|
| PHP | `config('app.name')`, which defaults to the `composer.json` vendor |
| AWS CDK | `cloud/aws/bin/app-aws.ts` reads `composer.json` |
| docker compose | uses the project directory's name |
| Documentation | writes `{app}` |

No code, config, infrastructure, test or document spells the name out. None of them describes
the project as coming from another codebase either: it stands on its own. The only exceptions are
generated vendor assets (`public/*/filament`) and lockfiles.

`php artisan canon:check` enforces this. It reads the name from
`composer.json` and fails on any other occurrence.

**PHP-26 — Colours and fonts are written in exactly one place: `config/theme.php`.** Re-branding the
application is an edit to that file. Every view, stylesheet, email and panel derives its colours and
fonts from it:

| Where | How it gets the tokens |
|---|---|
| Browser views: the welcome page, the error pages, the admin panel | `<x-theme />` in the `<head>` declares one `--theme-*` variable per token and loads the fonts; styles then write `var(--theme-primary)`, `var(--theme-font-sans)` |
| The admin panel's palettes and fonts | `AdminPanelProvider` passes `config('theme.*')` to `->colors()`, `->font()` and `->monoFont()` |
| Emails | `config('theme.*')` inlined at render, because email clients ignore CSS variables |

No view, stylesheet or PHP file under `app/` writes a hex colour, a colour function such as
`rgb()`, a named colour, a font family or a Filament palette such as `Color::Blue`. Semantic names are fine: a
component's `color="danger"` and `color-mix(in srgb, var(--theme-paper) 20%, transparent)` both
derive from the tokens. The only exception is `resources/css/coverage.css`, which PHPUnit copies
verbatim and which therefore cannot read config. How theming works is in
[`docs/infrastructure/theme.md`](../infrastructure/theme.md).

`php artisan canon:check` enforces this across `resources/views/`, `resources/css/` and `app/`.

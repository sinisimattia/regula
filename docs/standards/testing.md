# Testing

PHPUnit only. If you find a Pest test, convert it.

## Writing tests

**TEST-01 — Create tests with `php artisan make:test --phpunit {name}`.** Most tests should be
feature tests; pass `--unit` only for genuine unit tests.

**TEST-02 — Every change needs a test, new or updated.** Cover the happy path, the failure paths,
and the edge cases that motivated the change. Drivers and the framework stack are the exception
(TEST-14), and so is a class that declares no methods of its own: it only configures the framework
class it extends, such as a middleware overriding `$except`.

**TEST-14 — Don't test drivers or the framework stack; test the code that uses them.** A queue
connector, a log handler factory, a filesystem adapter or a config file that picks a backend is
infrastructure. Laravel's abstractions are what make it swappable, and they are Laravel's to test. If
a driver breaks, the tests of the code that dispatches, logs or stores through it break with it, and
that is where the failure should surface. A test that only proves a driver is wired the way it is
written adds nothing, and it breaks every time the backend is swapped.

**TEST-03 — Use factories, and check for an existing custom state before writing a new one.**

**TEST-04 — Never delete a test or a test file without approval.** A test that has become
inconvenient is usually a test that has found something.

## Never test Filament

**TEST-05 — Never write tests for code under `app/Filament/`.** Resources, pages, relation
managers, widgets and custom admin actions are a thin UI shell over domain services; the services
carry the real logic and already have coverage.

Do not author them yourself, and do not delegate Filament test work to a test specialist. If an
admin bug surfaces a domain-layer issue, the test belongs on the **service**.

## Never assert on real configuration

**TEST-06 — A test may never assert on the shape or values of a real file under `config/`.**

Config holds product and operational decisions — which features exist, how long a token lives, what
a limit is. They change often, made by people who are not changing behaviour and who
must not have to repair tests to do it. A test that fails because somebody renamed a feature or shortened a token's lifetime protects
nothing: it taxes configuration edits and teaches people to edit tests
until they go green, which is how a real regression gets waved through.

**Forbidden:** an entry count or `assertCount()` over a config array; an assertion on a real limit, lifetime, threshold or class name; iterating a real config array and asserting per element.

**Required instead:** the test builds its own fixture — an injected config repository, or
`config()->set(...)` with a hand-built array.

**The one permitted assertion against a real config file is that it is valid**: it parses, and it
passes its validator. That stays true when somebody adds a well-formed entry, which is exactly the
point.

## Running tests

**TEST-07 — Run tests inside the docker `app` container.** Host PHP is usually capped well below
what the suite needs, and the container has the extensions and the `testing` database the suite
expects.

```bash
docker compose exec app ./vendor/bin/phpunit --filter SomeTest
```

**TEST-08 — Run the smallest scope that covers your change** — the modified test file, then its
directory. Run the **full suite** only for genuinely cross-cutting changes: the base `TestCase`,
service providers, container bindings, shared traits or middleware, migrations, or `config/`.

**TEST-09 — Verify that the tests covering your change pass before calling the work done.** If a
test cannot be made to pass, explain in detail why. A **pre-existing** failure unrelated to your
change is reported, not fixed.

**TEST-10 — Two test runs at once are safe, but they queue.** `tests/bootstrap.php` rebuilds the
schema from nothing on every invocation, so it holds an exclusive lock for the life of the process:
a second suite started while one is running waits rather than dropping the tables the first is still
using. The lock lives in the bootstrap rather than a wrapper script because every way of starting a
suite passes through it: `composer test`, `vendor/bin/phpunit`, an IDE, CI. The operating system
releases it however the process ends, so a killed run leaves nothing stale behind.

Nothing to remember day to day — run tests however you like. If you genuinely need two suites in
parallel, give one its own database (`DB_DATABASE=testing_other ./vendor/bin/phpunit ...`), which
takes a different lock. **A run that seems to hang at startup is waiting for another one to
finish.**

**TEST-11 — Never let a background agent run the full suite.** It holds the schema lock for the
duration and blocks everything else.

## Don't write throwaway verification

**TEST-12 — Don't write verification scripts or reach for `tinker` when a test would prove the same
thing.** The test is the artifact that keeps proving it.

## External services

**TEST-13 — No test reaches an external service; anything without a local equivalent is mocked.**
This holds in feature tests too. A backend that runs locally is used for real: Postgres, the
`database` and `sync` queue drivers, the local disk, the `array` cache, the `log` and `array`
mailers. A backend that exists only outside the machine is replaced: SQS, S3, CloudWatch, SES and any
third-party HTTP API. Use a Laravel fake (`Queue::fake()`, `Storage::fake()`, `Mail::fake()`,
`Http::fake()`) or a mocked SDK client. `Http::preventStrayRequests()` in `tests/TestCase.php` turns
an unfaked HTTP call into a failure rather than a network request. It does not cover AWS SDK clients,
which use their own HTTP handler. `phpunit.xml` keeps those off by forcing `QUEUE_DRIVER=sync`,
`MAIL_MAILER=array` and `BROADCAST_CONNECTION=null`, and anything that builds a client directly
mocks it.

A test that needs the network is slow, flaky and charged to someone's AWS bill. It also fails the day
the service is down, which says nothing about our code.

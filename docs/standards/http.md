# HTTP layer

Controllers, Form Requests, API Resources, routes and the exceptions that cross between them.

Each of those classes carries its suffix — `{Noun}Controller`, `{Action}Request`,
`{Noun}Resource` — while middleware deliberately takes none: `SetLocale`, not `SetLocaleMiddleware`
([PHP-17](php.md)).

## Where the HTTP layer lives

**HTTP-01 — `app/Http` holds only what belongs to no domain**: the base `Controller`, kernel-level
middleware, and platform-wide endpoints that are not about any single domain's data — a health check, say, or a
notifications inbox.

**HTTP-02 — Anything about a domain's data lives in that domain's `Http/`** — its controllers, its
Form Requests, its API Resources. If the endpoint is about accounts, it belongs to the Auth domain.

## Controllers

**HTTP-03 — Controllers are thin.** They accept a validated request, call **one** service method
(DA-13), and return a Resource. No business logic, no orchestration across services, no repository
access (DA-04).

**HTTP-04 — Controllers translate domain exceptions into HTTP responses** by wrapping them in
`App\Exceptions\HttpApplicationException`, with the original attached as `previous`:

```php
try {
    $this->invoiceService->refundInvoice($invoiceId);
} catch (InvoiceAlreadyRefundedException $e) {
    throw new HttpApplicationException(
        code: Response::HTTP_CONFLICT,
        message: 'The invoice has already been refunded.',
        previous: $e,
    );
}
```

The domain throws in its own vocabulary; the controller decides what that means over HTTP. Keeping
the original as `previous` is what makes the trace usable later.

**HTTP-05 — Events are never dispatched from a controller** (DA-19).

**HTTP-06 — A domain's `Http/` never crosses a domain boundary** (DA-20). The one exception is
`Http/Resources/`: another domain's API Resource may be used to compose a response, nested in your
own Resource or returned from a controller. Controllers, Form Requests and middleware never cross.

**HTTP-07 — There is no `Http/Exceptions/` folder** (DA-21). Domain exceptions live in the domain's
`Exceptions/`; everything crossing into HTTP is wrapped in `HttpApplicationException` by the
controller, which is also where the translation between the two belongs.

## Form Requests

**HTTP-08 — Validation lives in a Form Request class, never inline in a controller.** Include
rules **and** custom messages, and match the array-versus-string rule style of the sibling requests
in the same directory.

**HTTP-09 — Form Requests validate format and type only** — `required`, `string`, `integer`,
`max`, `array`. Business checks belong in the service layer, which throws domain exceptions.

**HTTP-10 — No `exists:` or `unique:`.** Existence and uniqueness are the service's job, and it
answers with a domain exception: not-found for existence (ENT-08), a conflict for uniqueness. A
validation rule that hits the database duplicates a lookup the service is about to do anyway, and
returns a 422 where a 404 or a 409 is the truth.

**HTTP-11 — No `in:a,b,c` for enum-backed fields.** Validate as `string` and convert with
`Enum::tryFrom()` in the service, which throws when the value is invalid. Listing the values in the
rule duplicates the enum, and the copy is the one that goes stale.

**HTTP-12 — Entity extraction belongs on the Form Request.** Where a request maps onto a domain
entity, the request exposes an `extractEntity()` (or `extractSteps()`, etc.) method that builds it.
The controller then hands a typed entity to the service rather than an array (ENT-01).

## API Resources

**HTTP-13 — Responses are built with API Resources**, not hand-assembled arrays. The one exception is
a health check: a liveness probe answers infrastructure, not an API client.

**HTTP-14 — Dates are Unix timestamps.**

```php
'created_at' => $entity->createdAt->timestamp,   // yes
'created_at' => $entity->createdAt->toIso8601String(),   // no
```

**HTTP-15 — Extend an existing Resource before writing a parallel one.** When a response needs more
detail, add an optional constructor argument to the Resource that already exists rather than
creating a `*DetailResource` beside it. Two resources for one entity drift, and the client cannot
tell which shape it will get.

## Routes

**HTTP-16 — Routes go in the file that matches their subject:**

| File | Holds |
|---|---|
| `routes/api.php` | General API, under `/api` — the health check, and anything platform-wide |
| `routes/auth.php` | Authentication — register, login, tokens, password reset, verification, account deletion |
| `routes/web.php` | Browser-facing routes — the welcome page and the email verification link |
| `routes/console.php` | Closure-based console commands |

A new subject gets its own file, registered in `app/Providers/RouteServiceProvider.php`, rather than
growing `api.php` into a catch-all.

**HTTP-17 — Link with `route()` and a named route**, never a hand-built path.

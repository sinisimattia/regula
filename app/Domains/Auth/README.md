# Auth Domain

## What This Domain Represents

The Auth domain establishes and maintains trust between the platform and its users. It answers the
question: "Who are you, and are you allowed to be here?" It covers how users prove their identity,
how their sessions are kept secure, and what happens when an account is created or removed.

This domain does **not** decide what a signed-in user may do inside the product. Authorization for
features belongs to the domains that own those features. The one exception is the admin panel's
front door, described below.

---

## Key Concepts

### Identity and Credentials

A user's identity is anchored to their email address and a password. An email address belongs to
one account at most, whatever its letter case. Until the address is confirmed, the account exists
and can sign in, but it is not verified.

Login and password reset never reveal whether an email has an account; registration does, on
purpose. See [`Docs/protecting-accounts.md`](Docs/protecting-accounts.md).

### Sessions via Dual Tokens

A signed-in session is a pair of tokens: a short-lived **access token** for API calls, and a
longer-lived **refresh token** whose only power is to issue a new pair. The difference is enforced
by `TokenAbility`. **A new authenticated route goes in the `authenticated` middleware group**, which
admits access tokens only. See [`Docs/tokens.md`](Docs/tokens.md).

### Email Verification

Registering queues a verification email carrying a signed, time-limited link. Opening it marks the
address as verified; opening it again changes nothing. It can redirect the user to a URL supplied at
registration. A user who lost the email can ask for a new one; nothing is sent once the address is
verified.

### Where Emailed Links May Point

The password reset page and the post-verification redirect are caller-supplied URLs that end up in
an email, so they are accepted only on an origin listed in `ALLOWED_REDIRECT_ORIGINS`. See
[`Docs/protecting-accounts.md`](Docs/protecting-accounts.md).

### Password Reset

A user who forgot their password asks for a reset link. The email points at the frontend's reset
page, carrying a one-time token. Submitting a new password with that token changes the password and
signs the user out everywhere, because every existing token is revoked.

### Account Deletion

Deleting an account happens in two phases. The request locks the account out immediately: it is
marked pending deletion, its tokens are revoked, its roles removed, and a confirmation email is
queued. The row itself is removed afterwards by a queued listener, retried up to five times, with a
persistent failure landing in `failed_jobs`. See
[`Docs/accounts-and-deletion.md`](Docs/accounts-and-deletion.md).

### Admin Panel Access

Reaching the admin panel takes a verified email and at least one role, or the Super Admin role,
which bypasses every authorization check in the application. See
[`Docs/admin-panel-access.md`](Docs/admin-panel-access.md).

---

## Core Business Rules

- One account per email address, compared case-insensitively.
- An unverified account can sign in.
- Issuing a new token pair invalidates every previously issued token for that user.
- A refresh keeps the session's lifetime: "remember me" is chosen at login and never changed after.
- Resetting a password revokes every token the user holds.
- A login failure and a password reset request never reveal whether the email has an account.
- An account pending deletion cannot sign in, obtain tokens or receive a reset link.
- An emailed link only ever points at an origin listed in `ALLOWED_REDIRECT_ORIGINS`.
- A refresh token can only mint a new token pair; it cannot call any other route.
- Account deletion is irreversible. The physical delete is deferred and retried up to five times,
  and a persistent failure lands in `failed_jobs` with its real cause.
- Admin panel access requires a verified email and at least one role. The Super Admin role bypasses
  that check and every other authorization check in the application.

---

## What This Domain Owns

- User registration and the email verification lifecycle
- Password login and logout
- Token issuance, renewal and revocation
- The password reset flow
- Account deletion, both the request and the physical removal
- Admin panel access control: who may enter, not what they may do once inside

---

## Public Surface

| Type | Kind | What other code uses it for |
|---|---|---|
| `Entities\User`, `Entities\UserId` | entities | Referring to a user from any other domain |
| `Entities\UserAccount` | entity | A user plus the credentials and preferences only Auth handles (what registration takes) |
| `Entities\TokenPair` | entity | The access and refresh tokens issued together |
| `Enums\TokenAbility` | enum | The abilities tokens carry; the `authenticated` middleware group demands `access-api` |
| `Services\AccountServiceInterface` | service | Registering, verifying and deleting accounts |
| `Services\AuthServiceInterface` | service | Signing in and out, issuing tokens, resetting passwords |
| `Events\UserRegistered` | event | Reacting to a new account (it drives the verification email) |
| `Events\UserDeleted` | event | Cleaning up anything a user owned before their row is removed |

---

## Relationships to Other Domains

**Core domain**: `UserId` extends Core's `Identification`, like every typed ID.

No other domain exists yet. A domain that needs to clean up after a user listens for `UserDeleted`.
It does not reach into Auth's models: every interface above takes and returns entities and typed
IDs, and `App\Models\User` stays behind the repository.

---

## Further Reading

| | |
|---|---|
| [`Docs/tokens.md`](Docs/tokens.md) | The token pair, what each token may do, how to declare an authenticated route, and how refresh keeps a session's lifetime |
| [`Docs/protecting-accounts.md`](Docs/protecting-accounts.md) | What does and does not reveal who has an account, where emailed links may point, and case-insensitive emails |
| [`Docs/accounts-and-deletion.md`](Docs/accounts-and-deletion.md) | What each phase of a deletion does, and why the physical delete is a retried job, with a worked example |
| [`Docs/admin-panel-access.md`](Docs/admin-panel-access.md) | Who can get into the admin panel, why the Super Admin bypass reaches further than the door, and how roles and permissions are seeded |

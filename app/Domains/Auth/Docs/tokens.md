# Tokens and Sessions

**The question this page answers:** what a signed-in session is made of, what each token is
allowed to do, and how long a session lasts.

---

## A session is a pair of tokens

Signing in (or registering) returns two Sanctum tokens:

| Token | Lifetime | Ability | The only thing it can do |
|---|---|---|---|
| Access token | `sanctum.access_token_expiration` (an hour), or a day with "remember me" | `access-api` | Call authenticated API routes |
| Refresh token | `sanctum.refresh_token_expiration` (a week), or a year with "remember me" | `issue-access-token` | Call `POST /api/auth/refresh_token` to get a new pair |

The abilities are `TokenAbility` cases, and they are enforced by the routes, not just intended. A
leaked refresh token cannot read anything, log the user out or delete their account. A leaked access
token dies quickly.

Every new pair replaces every token the user held before, so there is never more than one live
session per user. Logging out, resetting the password and requesting deletion all revoke every
token too.

---

## Declaring an authenticated route

Put it in the `authenticated` middleware group (`app/Http/Kernel.php`):

```php
Route::group(['middleware' => ['authenticated']], function () {
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
});
```

The group is `auth:sanctum` plus `abilities:access-api`. Using `auth:sanctum` alone would let a
refresh token in, which is exactly what the pair exists to prevent. `refresh_token` is the one route
that declares its own middleware instead, because it demands the other ability.

---

## Refreshing keeps the session's lifetime

A refresh issues a pair of the same kind as the one it replaces. A remembered session stays
remembered; a standard session stays standard. The choice is made once, at login, and a refresh
cannot change it — so a stolen seven-day refresh token can never be traded for a one-year one.

The repository recognises a remembered pair by its refresh token's name, and since a user only ever
holds one pair, that name is the session's whole history.

---

## Worked example

Maria signs in with "remember me" at 09:00 on Monday.

| When | What happens |
|---|---|
| Mon 09:00 | She receives an access token valid for a day and a refresh token valid for a year. |
| Tue 09:05 | Her access token has expired. Her frontend calls `refresh_token` with the refresh token and gets a new remembered pair: another day, another year. The old pair is gone. |
| Tue 09:06 | The frontend mistakenly calls `logout` with the refresh token. It is refused with `403`: a refresh token can only refresh. |

Had she signed in without "remember me", every refresh would have handed her another standard pair,
and the session would end the first time her refresh token expired unused.

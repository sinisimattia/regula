# Protecting Accounts

**The question this page answers:** what stops a caller from learning who has an account, from
hijacking an emailed link, or from opening a second account on the same address?

---

## Who has an account: what leaks and what does not

**Login does not leak.** An unknown email, a wrong password and an account pending deletion all get
the same `401 Invalid credentials.` They also take the same time: on an unknown email the service
still checks the password against a fixed bcrypt hash and throws the result away, so the answer
cannot be told apart by how long it took.

**Forgot-password does not leak.** It answers `204 No Content` for every email. For an unknown
email, an account pending deletion or a repeat within the throttle window it sends nothing. For a
real account it queues the email rather than sending it inside the request, so the response is not
slower, and a mail outage cannot turn it into a `500` that only real accounts would get.

**Registration does leak, on purpose.** Registering an address that already has an account answers
`422 User with this email already exists.` A sign-up form that hid this would leave people unable
to tell why they cannot register. It is a product decision, not an oversight, and the protection
above is only worth what it is: login and password reset cannot be used to probe for accounts.

---

## Where emailed links may point

Two URLs arrive from the caller and end up inside an email: the password reset page and the
post-verification redirect. Both are accepted only when their origin (scheme, host and port) is
listed in `ALLOWED_REDIRECT_ORIGINS`; anything else is a `422`.

Without this, anyone could request a reset for someone else's email with a link pointing at their
own site. The victim would receive a genuine email that hands the reset token to the attacker.

`AllowedRedirectUrlRule` compares origins the way a browser would reach them. A URL with a username
or password in it (`https://someone@app.example.com`) or with a backslash anywhere
(`https://evil.example\@app.example.com`) is refused outright: PHP's URL parser and a browser can
disagree about which host such a URL points at, and the browser is the one that follows the link.

---

## One account per email address, whatever the case

Email addresses are stored and looked up in lower case, in the repository and nowhere else. So
`Maria@Example.com` and `maria@example.com` are the same account: registering the second is refused,
and either spelling signs in.

---

## Worked example

Someone wants to know whether `maria@example.com` has an account.

| They try | They get |
|---|---|
| Logging in with a guessed password | `401 Invalid credentials.`, in the same time as for any other address |
| Asking for a password reset | `204 No Content`, exactly as for an address nobody owns |
| Asking for a reset with `password_reset_page_url=https://evil.example/steal` | `422`: the origin is not allowed, and no email is sent |
| Registering the address | `422 User with this email already exists.` — the one door deliberately left open |

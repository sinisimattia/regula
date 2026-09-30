# Account Deletion

**The question this page answers:** what actually happens when an account is deleted, and why
does the account still exist for a moment after the request succeeds?

Today there is one way an account gets deleted: its user asks, through `DELETE /api/auth/delete_account`.
Nothing in the platform deletes accounts on its own.

---

## The two phases of a deletion

Deletion is never one step. The request is acknowledged and the session is torn down immediately.
The row itself is removed afterwards, on a queue.

**Phase one, synchronous.** This runs inside the request, before the response goes back:
- The account is marked as pending deletion (`deletion_requested_at`). From this moment it is locked
  out: a login fails with the same `401 Invalid credentials.` as a wrong password, no new token pair
  can be issued, and a password reset request is silently ignored.
- The user is logged out. Every access and refresh token is revoked.
- Every admin-panel role and permission is detached.
- `UserDeleted` is dispatched, carrying the user as an entity rather than a model.
- A deletion confirmation email is sent.

**Phase two, queued.** A queued listener, `DeleteUserAccountOnUserDeletedListener`, picks up
`UserDeleted` and removes the user row. How soon that happens depends on the queue connection.
`QUEUE_DRIVER` defaults to `sync`, and on `sync` the listener runs inside the same request, right
after phase one. On a real queue such as SQS it runs on a worker moments later, which is the case
the rest of this page describes.

On a real queue the second phase retries on failure. It makes up to five attempts, and the wait
between them grows: a minute, then five, then fifteen, where it is capped because the SQS queue
connection refuses a longer delay. A persistent failure lands in `failed_jobs` with its real cause, where a human can see
it and retry it. Until then the row stays, still locked out. The job is also safe to run twice: if the row is already gone, it treats the work as done and
does not fail.

**Deletion is irreversible.** Once phase one has run there is no path back. The session is gone,
the account can no longer sign in, and phase two is a matter of when, not if.

---

## Why the event carries an entity, not a model

By the time a queued listener runs, the row may already be gone, for example when a retry follows
a delete that succeeded but timed out. A listener handed an Eloquent model would fail trying to
reload it. `UserDeleted` carries a plain `User` entity instead: its ID, display name and email,
captured when deletion was requested. That is everything a listener needs to clean up after the
user.

A domain that owns something belonging to a user listens for `UserDeleted` and cleans up its own
data. It never queries Auth's tables to find out what the user had.

---

## Worked example

Maria deletes her account at 10:00, on an environment whose queue is SQS.

| When | What happens |
|---|---|
| 10:00:00 | Her request arrives. Her account is marked pending deletion, her tokens are revoked, her roles detached, `UserDeleted` dispatched and the confirmation email queued. She gets a `204 No Content`. |
| 10:00:01 | Any request carrying her old access token is rejected with `401`, and so is a fresh login with her correct password. |
| 10:00:02 | A worker picks up the queued listener and deletes her row. |

Now suppose the database is briefly unavailable at 10:00:02. The job fails and is retried at
10:01:02. If it fails again, it waits five minutes, then fifteen, then fifteen again. Her tokens have
been dead and her login refused since 10:00:00 throughout, so nobody can use the account while the
delete catches up — and if every attempt fails, it stays that way until someone retries the job
from `failed_jobs`.

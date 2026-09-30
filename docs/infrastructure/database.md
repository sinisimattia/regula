# Database

## Why do reads and writes have separate hosts?

On AWS the database is an Aurora PostgreSQL cluster, which has two endpoints:

| Endpoint | Accepts | Set in |
|---|---|---|
| Cluster (writer) | reads and writes | `DB_HOST` |
| Reader | reads only, served by replicas | `DB_READ_HOST` |

`config/database.php` gives the `pgsql` connection a `read` host and a `write` host, so Laravel
sends each `SELECT` outside a transaction to the reader, and everything else to the writer:
writes, anything inside a transaction, and locking reads such as `lockForUpdate()`. Everywhere else
(locally, self-hosted, in CI) there is a single Postgres, and `DB_READ_HOST` is left empty.

## What happens when `DB_READ_HOST` is empty?

Reads go to `DB_HOST`, so a single database needs no extra setting.

That is why the config reads `env('DB_READ_HOST') ?: env('DB_HOST')` rather than
`env('DB_READ_HOST', env('DB_HOST'))`. `env()` returns its default only when a variable is
**missing**. An empty `DB_READ_HOST=` in `.env` is present, and comes back as an empty string. With a
default argument the read host would be `''` and every read would fail. `?:` treats the empty string
as "not set".

## Can a read miss something the same request just wrote?

No. Replicas lag the writer slightly, so a read on a replica straight after a write could miss it:

1. A request stores a user through the writer.
2. It reads that user back through a replica, before the replica has caught up.
3. The user seems not to exist.

`'sticky' => true` prevents this. Once a request has written, the rest of that request reads from
the writer too. A request that only reads still uses the replicas.

## What `sticky` does not cover

Stickiness lasts for one request. A queued job or listener that the request dispatched, or the
client's next request, starts fresh and reads from a replica. If it runs within the replica lag, it
can miss the write. Work that has to see a write immediately should either receive the data it needs
in its payload, or read inside a transaction, which goes to the writer.

## Rejected alternatives

- **One host for everything.** Simpler, but on Aurora it sends every read to the writer and leaves
  the replicas idle.
- **A second, read-only connection that code picks by name.** Every call site would have to
  remember to choose it, and forgetting sends the read to the writer without any error. `sticky`
  also works only within one connection, so a read on the second connection could still miss the
  request's own write. The split stays in config, and code keeps using the default connection.

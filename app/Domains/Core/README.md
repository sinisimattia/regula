# Core Domain

## What This Domain Represents

Core holds the handful of building blocks that every other domain is built on. It has no business
rules of its own. It answers only one question: "what does every domain need to share so the
others can speak the same language?"

Today that is exactly one type: `Identification`, the base class for typed entity IDs.

---

## What Belongs Here

A type earns a place in Core only if **every** domain depends on it, or is expected to. Being
useful to two domains is not enough; that is a type one domain owns and exposes.

Core never holds business logic. It has no services, no models, no routes and no service
provider. The day it needs a service, it is probably not Core any more. Ask first whether the
behaviour belongs to a real domain.

| Belongs in Core | Does not belong in Core |
|---|---|
| A base class every entity ID extends | A `User` entity (Auth owns it) |
| A contract every domain implements | A helper two domains happen to share |
| | Anything that reads the database or calls an API |

---

## Public Surface

| Type | Kind | Used for |
|---|---|---|
| `Contracts\Identification` | abstract class | The base of every typed entity ID, e.g. `App\Domains\Auth\Entities\UserId` |

See [`Docs/identification.md`](Docs/identification.md) for how typed IDs are meant to be used.

---

## Relationships to Other Domains

Core depends on no other domain. Every other domain may depend on Core.

# Typed IDs

Every entity's ID is its own small class, and every one of those classes extends
`App\Domains\Core\Contracts\Identification`. A user's ID is a `UserId` and never a bare `int`,
so a function that expects a user's ID cannot be handed some other entity's ID by mistake.

```php
namespace App\Domains\Auth\Entities;

use App\Domains\Core\Contracts\Identification;

class UserId extends Identification {}
```

`Identification` carries one thing: `$value`. It is declared `mixed` on purpose, because some IDs
are integers, some are UUID strings, and some come in as strings from a URL.

## The rule: a typed ID is trusted

Once a value has been wrapped in its ID class, the rest of the code treats it as correct. It does
not check it again (ENT-05). The type **is** the promise. The check, if one is needed, happens once,
where the raw value enters the system: a route constraint, a Form Request, a repository.

When the value needs to be a particular scalar type, cast it plainly. Don't validate it, and don't
fail closed.

### Worked example

A repository receives a `UserId` and looks the user up. This is Auth's `UserRepository`:

```php
// Right: the ID is trusted, so it goes straight into the query.
$userModel = UserModel::query()->findOrFail($userId->value);
```

Where something genuinely needs a particular scalar, such as a strictly typed `int` parameter, a
plain `(int) $userId->value` is all it takes.

```php
// Wrong: re-validating what the type already promises.
if (!is_int($userId->value) || $userId->value <= 0) {
    throw new InvalidUserIdException();
}
```

The second version looks careful, but it is dangerous. A `UserId` built from a route parameter or
a JSON payload can hold the string `"42"` rather than the integer `42`. The "careful" check rejects
a perfectly good ID and turns a working request into an error.

## Where the checking does happen

- **At the edge.** A route constraint or a Form Request checks that the raw value looks like an ID
  before it is wrapped. Auth's verification link, for example, is registered with
  `->whereNumber('userId')`, so its controller only ever wraps digits into a `UserId`.
- **At the lookup.** A repository that cannot find the row throws that domain's `NotFound`
  exception. A missing record is a real, expected outcome. A malformed ID is not.

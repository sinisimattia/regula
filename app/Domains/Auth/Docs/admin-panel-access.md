# Admin Panel Access

**The question this page answers:** who can get into the admin panel, and why did someone lose
access when their role was taken away?

## The rule

Getting into the admin panel at `/admin` takes one of two things being true:

| | Verified email | Has at least one role | Can access the panel |
|---|---|---|---|
| Super Admin | doesn't matter | doesn't matter | ✅ always |
| Anyone else, with a role | ✅ | ✅ | ✅ |
| Anyone else, with a role | ❌ | ✅ | ❌ |
| Anyone else, no role at all | — | ❌ | ❌ |

That is the whole rule. **Super Admin bypasses everything. Anyone else needs a verified email and
at least one role.** It does not matter which role, or which permissions it carries. A role with
no permissions is still enough to get in. Permissions govern the individual resources and actions
inside the panel; the door itself only checks that a role exists.

Taking someone's last role away locks them out immediately, even if their email is still verified,
because there is nothing left for the check to see.

## Super Admin bypasses more than the door

Super Admin is not a special case of the panel-access check. It is a blanket authorization bypass,
registered once in the Auth service provider (`Gate::before`), that answers **every** permission and
policy check in the application before that check runs, inside the panel or not. A Super Admin does
not merely get through the door. They pass every gate and policy behind it too, with nothing further
to configure.

## Roles and permissions

Roles and permissions use `spatie/laravel-permission`. Their tables carry an `admin_` prefix, so
that "role" stays free to mean something else in the product later without the two being confused.

There is no roles screen in the panel yet. Roles and permissions are created by the seeders:

- `RolesSeeder` runs in every environment and is safe to re-run. It creates the `Super Admin` role
  and the permissions the admin policies check (`view`, `create`, `update` and `delete`, on `User`,
  `Role` and `Permission`).
- `UserSeeder` runs outside production only. It creates a handful of fake users and one Super Admin,
  whose email comes from `ADMIN_EMAIL` and whose password is `password`.

In production, nobody is granted Super Admin automatically. It is only ever assigned deliberately,
for example through `php artisan tinker`.

### Worked example

On a fresh local database, `php artisan migrate:fresh --seed` with `ADMIN_EMAIL=me@example.com` gives
you a verified `me@example.com` holding `Super Admin`. Sign in at `/admin` with the password
`password`. Give a colleague the role `support` with no permissions at all and verify their email:
they can open the panel, but every resource guarded by a policy turns them away until the role
gains the matching permission.

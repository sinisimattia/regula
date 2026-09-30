---
name: coding-standards
description: "Use before writing or reviewing any PHP here, and whenever someone asks how this project does something — \"where should this live\", \"what do we call this\", \"how do we handle errors\", \"is this allowed\". Loads the project coding standards: class naming and suffixes, domain boundaries and the public surface, entities, HTTP layer, persistence, testing, tasks and git. Answer from these rather than from memory, and cite the rule ID."
user-invocable: true
---

# Coding standards

The standards live in `docs/standards/`. This skill is an index — it holds no rules of its own, so
there is only ever one copy to keep current.

**Read the file that matches what you are doing. Do not work from memory or from surrounding code.**

| Doing | Read |
|---|---|
| Anything in PHP | `docs/standards/README.md` — index, non-negotiables, how to change a rule |
| Naming a class, typing a signature, writing a comment | `docs/standards/php.md` — class suffixes are PHP-17 |
| Working in `app/Domains/` | `docs/standards/domain-architecture.md` — **in full** |
| Entities, service signatures, persistence vocabulary | `docs/standards/entities.md` |
| Endpoints, Form Requests, API Resources, routes | `docs/standards/http.md` |
| Migrations, models, repositories, factories | `docs/standards/persistence.md` |
| Tests | `docs/standards/testing.md` |
| Branching, commits, releases, the Boy Scout rule | `docs/standards/git.md` |
| Writing or publishing tasks | `docs/standards/tasks.md` |

## How to use them

- The canon is the **authority**. Where your judgement differs, the file wins.
- Every rule has an ID (`DA-03`, `ENT-04`, `GIT-06`). Cite it when you raise something.
- If a rule seems wrong for the task in front of you, **say so and stop.** Do not improvise a
  variation. `docs/standards/README.md` explains how a rule gets changed.
- What governs where is in `docs/standards/README.md` — `app/Domains`, `app/Http` and
  `app/Filament` are each governed by a different subset, and code outside the structural
  canon's reach is not a backlog to fix on sight.

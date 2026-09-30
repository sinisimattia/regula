# Git

Branching, commits, pull requests, and how much unrelated tidying belongs in a change.

## Branches

**GIT-01 — Branch off `main`, named by work type:**

| Prefix | For |
|---|---|
| `feat/<KEY>-<slug>` | A feature with a ticket |
| `fix/<KEY>-<slug>` | A bug fix with a ticket |
| `chore/<slug>` | Maintenance without a ticket |
| `refactor/<slug>` | Restructuring without a ticket |

Dependent tickets **stack on each other**, not on `main` — a branch whose work needs an unmerged
branch starts from that branch, so the diff shows only its own change.

Pull `main` before branching. A branch cut from a stale `main` produces a pull request full of other
people's commits.

## Commits

**GIT-02 — Never commit unless you were explicitly asked to.** Not after finishing a task, not
after tests pass, not because the work looks complete. This applies to AI assistants without
exception.

**GIT-03 — Announce every commit** with its branch and its hash. The author pushes and notifies the
reviewer; an assistant does neither unless told to.

**GIT-04 — One pull request per feature**, squash-merged. Each commit in it should be meaningful —
squash the "fixed bug" intermediates before submitting.

## The Boy Scout rule

**GIT-05 — When you change code, bring what you touched up to canon.**

"What you touched" means the methods and classes you actually modified — not the whole file, not the
whole domain. Leaving a known violation inside a method you just edited is a choice, and it is the
wrong one: the next person reads it as endorsement.

**GIT-06 — The containment test decides between fixing it and filing it.**

| Situation | Do |
|---|---|
| The fix stays inside files the change already touches | **Fix it in place** |
| The fix needs a file the change would not otherwise touch | **Open a Technical Task** (TASK-04) |

A public rename that moves every caller, a class relocation that rewrites imports, a service
signature change that ripples through consumers — those are tickets, not opportunistic edits. The
ticket cites the canon rule ID, so the debt is specific rather than "tidy this up later".

This test is deliberately objective. *"Improve what you touch"* without a boundary produces the
sprawling pull request nobody can review, which is how boy-scouting gets banned outright.

The rule is also bounded by scope: touching a file in `app/Http` obliges you to `php.md`, not to
`domain-architecture.md`. See [`README.md`](README.md) for what governs where.

## Documentation travels with the change

**GIT-07 — Updating documentation is part of the change that altered the behaviour**, not a
follow-up. That means the domain's `README.md` and `Docs/` (DA-18), and this canon if the change
proved a rule wrong (see *Changing a rule* in [`README.md`](README.md)).

## Releases

A change is **delicate** when it involves a migration, a configuration or contract change, a data
backfill, a queue or connection change, or anything breaking. Delicate changes get a staged release
plan rather than a merge and a hope.

**GIT-08 — Nothing goes live before the pull request is merged.**

**GIT-09 — Every step in a release plan names who performs it** — the author or the assistant. A
plan where that is ambiguous is a plan where a step gets skipped or performed twice.

**GIT-10 — An AI assistant never edits a production `.env` and never throws a cutover switch.** It
hands over the commands and the author runs them.

**GIT-11 — Verify a production release by reading CloudWatch**, log group
`{app}-backend-production-main-logs`, where `{app}` is the application name (PHP-22), the core stack's `{stack}-logs` group — see
`cloud/aws/lib/stacks/core/app-core-stack.ts`). Not by SSH, and never by assuming a missing error means
success.

---
name: ship
description: "Use whenever someone starts work on a ticket or a described piece of work — \"I need to do <KEY>-1234\", a pasted Jira key, \"let's pick this up\", \"can you implement this\". Takes it from intake to production: checks the ticket is complete, asks what is ambiguous before planning, plans it, builds it to the project standards with the right specialists, and walks a delicate change through staging and production. Usage: /ship TICKET_KEY (or a description of the work)"
user-invocable: true
---

# Ship

Five stages with real gates between them. Do not skip ahead: each gate exists because the failure it
prevents is expensive and invisible at the time.

This skill **composes** with the superpowers plugin rather than reimplementing it. Use
`superpowers:brainstorming` for stage 2, `superpowers:writing-plans` for stage 3, and
`superpowers:test-driven-development` and `verification-before-completion` during stage 4.

**If those skills are not available**, the plugin has not activated yet. It is declared in
`.claude/settings.json`, so this usually means the folder is not trusted or the plugin still needs
installing — Claude Code prints the command when that is the case. Say so once, then carry out the
stages yourself: the gates below are what matter,
and they do not depend on the plugin. Ask the ambiguities one at a time before planning; get the
plan approved before writing code; write the test before the implementation; verify before claiming
anything passes.

---

## Stage 1 — Intake

**Get the work.** `$ARGUMENTS` is a ticket key or a description. For a ticket key, fetch it and read
it properly — description, acceptance criteria, links, comments.

**Check it is actually ready.** `docs/standards/tasks.md` (TASK-03) says a task carries Context,
Scope, Acceptance criteria, Out of scope, and Links. Check for:

- a stated **why**, not just a what
- acceptance criteria that are **independently verifiable** — "works correctly" is not one
- what is **explicitly out of scope**
- **blocking links** that are still open
- an **estimate** (`customfield_10172`, hours) and the right **type** — both are decided during
  decomposition (TASK-04, TASK-09), so a ticket arriving without them skipped a step. Say so; do
  not fill them in here

If something essential is missing, say precisely what, and ask. Do not fill the gap with a plausible
assumption — an invented acceptance criterion is a decision made by the wrong person.

**Set up the branch (GIT-01). This is a gate, not a formality.**

1. Refuse to work on `main`. If you are on it, say so and branch.
2. `git fetch` and pull `main` first — a branch cut from a stale `main` produces a pull request full
   of other people's commits.
3. Name it by work type: `feat/<KEY>-<slug>` or `fix/<KEY>-<slug>`; `chore/<slug>` or
   `refactor/<slug>` without a ticket.
4. If this ticket is blocked by unmerged work, **stack on that branch**, not on `main`.

Move the ticket to *In Progress* (transition `21` — verify against the project's workflow, TASK-04).

---

## Stage 2 — Interrogate

Invoke `superpowers:brainstorming`.

Ask about every genuine ambiguity **before** planning. The test for a question worth asking: *would
different answers lead to materially different code?* If yes, ask. If no, decide and say what you
decided.

Not "shall I proceed" — that is not a question. Ask the ones like:

- what happens to in-flight records when this state changes?
- who may see this — only its owner, or every admin?
- what should existing rows get when this column is added?
- what does the user see when this fails?

Ask them one at a time. Read the relevant `docs/standards/` files and the domain's `README.md` and
`Docs/` first — half the questions answer themselves there, and asking one that the documentation
already answers wastes the person's trust.

---

## Stage 3 — Plan

Enter plan mode. Use `superpowers:writing-plans`.

The plan names, concretely:

- which **domains and files** change
- which **specialists** do which part (see the delegation table in `CLAUDE.md`)
- which **canon rules** constrain the work, by ID
- what **tests** prove it
- which **documentation** changes with it — the domain's `README.md` and `Docs/` (DA-18)
- whether the change is **delicate** (see stage 5), decided now rather than discovered at deploy time

**Get approval before writing code.** A rejected plan is not approval, and it is not permission to
start a smaller version of it.

---

## Stage 4 — Build

Normal workflow, bound by the canon.

- **Read the standards files that apply** before writing (`coding-standards` skill, or the paths
  directly). Do not infer structure from nearby code — that is how divergence spreads.
- **Delegate to the specialist** whose subject matches. The instance that built something never
  reviews it.
- **Apply the Boy Scout rule within its containment test** (GIT-05, GIT-06): bring what you touched
  up to standard; when a fix would spill into files this change does not already touch, raise a
  **Technical Task** via `task-specialist` instead of growing the diff.
- **Update the domain's documentation as part of the change** (DA-18), not afterwards.
- **Run `composer lint:fix`**, then `code-standards-specialist`, then `test-specialist`.
- **Verify the tests covering the change pass** (TEST-09), inside the docker container (TEST-07).
  Report a pre-existing unrelated failure; do not fix it here.

**Never commit unless you were explicitly asked** (GIT-02). When you are asked, announce the branch
and the hash (GIT-03). The author pushes and notifies the reviewer — you do not.

When the pull request is open, move the ticket to *In Review* (`7`).

---

## Stage 5 — Release

**Only engage this stage when the change is delicate.** It is delicate when it involves any of:

- a database migration, especially a destructive one or a backfill
- a configuration or contract change
- a queue or connection change
- anything breaking for an existing client
- anything that cannot be trivially reverted

If none apply, stop after stage 4: the pull request merges and deploys normally. Say so explicitly
rather than going quiet.

### Writing the runbook

Produce a staged plan, and **label every step with who performs it — YOU or CLAUDE.** A step whose
owner is ambiguous gets skipped or done twice.

```markdown
### Staging
1. **[YOU]** Merge the PR into `release/staging`
2. **[CLAUDE]** Confirm the migration ran: <exact check>
3. **[YOU]** Exercise <the specific flow>
4. **[CLAUDE]** Read CloudWatch for errors in the 15 minutes after deploy

### Production
5. **[YOU]** ...
```

### The rules this stage exists to enforce

- **Nothing goes live before the pull request is merged** (GIT-08).
- **You never edit a production `.env`, and never throw a cutover switch** (GIT-10). Hand over the
  exact commands and let the author run them.
- **Verify by reading CloudWatch**, log group `{app}-backend-production-main-logs` (GIT-11;
  staging reads `{app}-backend-staging-main-logs`; `{app}` is the application name, PHP-22). Not
  by SSH. A missing error line is not evidence of success — say what you actually checked.
- **Walk it interactively.** Staging first, confirm, then production. Do not hand over one wall of
  steps and disappear.
- **Scheduled commands using `ConfirmableTrait` need `--force`** in the command string (DB-14), or
  they abort silently on every run.

### Status, honestly

| Status | Means |
|---|---|
| *In Review* (`7`) | The pull request is open |
| *READY TO DEPLOY* (`4`) | Merged, **not yet in production** |
| *Done* (`31`) | Deployed and live |

Moving a ticket to *Done* because the PR merged is the most common way a release record becomes
fiction. Read the board before any batch transition.

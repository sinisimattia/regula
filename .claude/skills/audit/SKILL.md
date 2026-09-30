---
name: audit
description: "Use whenever someone asks for code to be reviewed, checked or assessed against how this project does things — \"review the Auth domain\", \"check this branch\", \"is this code alright\", \"does this follow our standards\". Audits a branch, domain, path or free-text target against docs/standards/, reporting findings with rule IDs and never rewriting code. Usage: /audit [target]"
user-invocable: true
---

# Audit

Review code against `docs/standards/` and report what diverges.

**This skill never fixes anything.** An audit that silently rewrites forty files is unreviewable,
and the point is to give a person a decision, not a diff. Fixes are a separate, deliberate act.

## 1. Resolve the target

`$ARGUMENTS` may be a branch, a domain, a path, a description, or empty.

| Target | Resolve to |
|---|---|
| *(empty)* | `git diff --name-only main...HEAD`, plus uncommitted changes |
| A branch name | `git diff --name-only main...<branch>` |
| A domain (`Auth`, `app/Domains/Auth`) | every PHP file under that domain |
| A path | every PHP file under it |
| `all` / `everything` | every domain under `app/Domains/`, one report section each |
| Free text | work out what they mean, **state your interpretation and the file count, and confirm before starting** if it resolves to more than ~50 files |

State the resolved file count before you begin. An audit of 700 files is a different conversation
from an audit of 7.

## 2. Ask the machine first

The gates already know most of what is mechanically knowable. Run them **before** launching anyone,
so nobody spends a read rediscovering a finding CI reports on every pull request — and so the audit
and CI cannot quietly disagree.

```bash
php artisan canon:check                               # every mechanical rule, per violation
docker compose exec -T app php vendor/bin/phpinsights analyse <target-path> --no-interaction
```

Scope Insights to the target path. A full run takes minutes and is wasted on a single domain.

**A green `canon:check` does not mean no debt.** It means the debt is *grandfathered*. Read the
`config` block in `config/insights.php` and pull out every entry whose path matches the target —
each one names a real violation and the reason it is still there. Those belong in the report as
**known debt**, distinct from anything new, because they are what the team has already decided not
to fix yet.

Carry three lists into the next step:

| List | Where from |
|---|---|
| Live mechanical findings | `canon:check` and Insights output |
| Known, grandfathered debt | `config/insights.php`, entries matching the target |
| Insights scores for the target | the run above |

## 3. Read the canon that applies

Read only what is relevant to what is actually in the file set:

- always — `docs/standards/README.md`, `php.md`
- files under `app/Domains/` — `domain-architecture.md`
- entities, services, repositories — `entities.md`, `persistence.md`
- controllers, requests, resources, routes — `http.md`
- tests — `testing.md`

`README.md` also says **what governs where**. `app/Domains`, `app/Http`, `app/Filament` and the
rest of `app/` are each governed by a different subset — auditing code against rules that do
not apply to it produces noise and teaches people to ignore the report.

## 4. Fan out

Launch in parallel, in one message, each in **review-only** mode:

| Specialist | Covers | When |
|---|---|---|
| `code-standards-specialist` | the standards | always |
| `test-specialist` | coverage gaps, stale assertions, config assertions | when the set includes tests, or code that should have them |

Tell each one explicitly: **REVIEW-ONLY — do not modify any file, do not run `git add`, `commit` or
`stash`.** Give each the resolved file list rather than letting it sweep the codebase.

**Hand them the lists from step 2, and say what to do with them:** confirm and quantify the known
findings, explain what actually goes wrong and whether each is an in-place fix or a Technical Task —
but do not spend the budget rediscovering them. Their value is in what the machine cannot see:
whether a rule's exception genuinely applies, whether documentation still matches behaviour, whether
a query reads only what the caller is entitled to, and whether a test asserts
anything once its subject is gone.

**Only one of them may run the test suite** — the schema lock is exclusive and a second run blocks
(TEST-10). In practice, none of them should: an audit reads.

For an `all` audit, work domain by domain rather than launching thirty agents at once.

## 5. Report

Write a markdown report to `docs/audits/<YYYY-MM-DD>-<target>.md` and give a short summary in the
terminal. A multi-domain sweep is unreadable in scrollback, and the file is what turns into tickets.

Group findings **by rule**, not by file — ten instances of `DA-17` is one decision, not ten.

```markdown
# Audit: <target>
<date> · <n> files · canon as of <git short hash>

## Summary
| Severity | Count |
|---|---|
| Critical — data exposure, correctness | n |
| Major — boundary or contract violation | n |
| Minor — convention | n |
| Known debt — already grandfathered | n |

Insights for this target: quality n · complexity n · architecture n · style n

## DA-03 — A service used outside its domain must have an interface
**Major** · 2 occurrences

- `app/Domains/X/Services/Foo.php:12` — referenced from `app/Domains/Y/...`
- ...

**Why it matters:** <one or two lines, in the author's terms>
**Containment (GIT-06):** in-place fix / Technical Task — <which, and why>
```

Order by severity, critical first. For every finding:

- the **rule ID** and its one-line statement
- every **file and line** it occurs at
- **why it matters**, in terms of what goes wrong — not a restatement of the rule
- **whether it is an in-place fix or a Technical Task**, per the containment test (GIT-06)

Close with:

- **new** findings separated from **known, grandfathered** debt — conflating them makes a report
  look worse than the change that prompted it, and hides what actually regressed
- what you checked and found **correct** — without it, nobody can tell thorough from cursory
- anything you judged **borderline and deliberately left alone**, and why
- rules you could **not** check, and what that would need

## 6. Offer, don't act

End by offering the obvious next steps — raise the Technical Tasks via `task-specialist`, or fix a
specific finding — and wait. Do not start either.

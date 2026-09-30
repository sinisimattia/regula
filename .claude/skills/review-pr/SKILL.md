---
name: review-pr
description: "Perform a comprehensive code review of a GitHub pull request. Usage: /review-pr PR_NUMBER"
user-invocable: true
---

# Review PR Skill

Perform a comprehensive code review of a GitHub pull request.

## Arguments

`$ARGUMENTS` contains the PR number, e.g.: `123`

If it is missing or malformed, ask the user: "Please provide the PR number, e.g.: `/review-pr 123`"

## Steps

### 1. Ask initial questions

Ask the user **both** of these questions in a single message before doing anything else:

> **1. Output:** Do you want the output printed to the console or saved to a `.md` file? If file, I will use the name `review-pr-<PR_NUMBER>.md` in the current directory.
>
> **2. Task context:** Which area does this PR cover? (you can select multiple)
> - `[A]` HTTP layer (controllers, routes, Form Requests, API resources)
> - `[B]` Admin panel (Filament)
> - `[C]` Database / migrations / models
> - `[D]` New DDD domain
> - `[N]` None of the above

Wait for both answers before proceeding.

Based on the context answer, note which **optional agents** to run in step 4:
- `[A]` → run `http-specialist`
- `[B]` → run `filament-specialist`
- `[C]` → run `database-specialist`
- `[D]` → run `domain-specialist`
- Multiple letters = run all corresponding agents

### 2. Fetch PR metadata from GitHub

Use the `gh` CLI to retrieve the PR details:

```bash
gh pr view <PR_NUMBER> --json title,body,author,headRefName,baseRefName,files
```

- PR title and description — `title`, `body`
- Author — `author.login`
- Source branch — `headRefName`, used as `<BRANCH_NAME>` below
- Target branch (base branch) — `baseRefName`, used as `<BASE_BRANCH>` below
- List of changed files — `files`

If `gh` is not authenticated, ask the user to run `gh auth login` themselves, then retry.

### 3. Get the diff

Run the following to obtain the full diff against the base branch:

```bash
gh pr diff <PR_NUMBER>
```

Also get the list of changed files:

```bash
gh pr diff <PR_NUMBER> --name-only
```

### 4. Run parallel agent reviews

Launch all agents **in parallel** (single message, multiple Agent tool calls). Tell every agent it
is **REVIEW-ONLY** — no file changes, no `git add`, `commit` or `stash` — and that
`docs/standards/` is the authority: findings cite rule IDs.

**Always run a–b (agents) and c–d (direct analyses).**
**Run e–h only for the contexts the user selected.**

#### a) `code-standards-specialist`
Pass it the full diff and ask for a review against `docs/standards/`. Formatting (PSR-12, strict
types, quotes, imports) is fixed by `composer lint:fix` — do not ask it to review those by hand.

#### b) `test-specialist` (review mode)
Ask it to review the diff for:
- Missing test coverage for new/changed logic
- Whether existing tests cover the changed code paths
- Tests asserting on real `config/` values (TEST-06)
- Suggest which tests should be added or updated
Tell it explicitly: **do not write tests**, only report what is missing. Skip it for changes
confined to `app/Filament/` (TEST-05).

#### c) Security analysis (direct, no sub-agent)
Directly analyze the diff for OWASP Top 10 risks:
- SQL injection (raw queries, unescaped inputs)
- XSS (unescaped output in Blade)
- Mass assignment vulnerabilities (`$fillable` / `$guarded`)
- Exposed sensitive data in API responses
- Hardcoded secrets or credentials
- Command injection in `Bash` calls or `exec()`
- Routes missing `auth:sanctum`, or a token ability check (`ability:`) where one is needed

#### d) Architecture/DDD analysis (direct, no sub-agent)
Directly analyze the diff for:
- Repositories injected anywhere but their own domain's services (DA-04)
- Business logic placed in controllers instead of services (HTTP-03)
- Events dispatched with `EventClass::dispatch()` instead of `event()`, or from a controller (DA-19)
- Direct DB queries bypassing Eloquent (DB-08)
- Domain boundaries respected — only the DA-01 list crosses (DA-01, DA-02)

#### e) `http-specialist` — only if user selected `[A]`
Ask it to review the diff for:
- Proper use of Form Request classes (no inline validation in controllers)
- Correct API Resource usage
- Unix timestamps in API resources (never ISO 8601)
- Domain exceptions wrapped in `HttpApplicationException` (HTTP-04)

#### f) `filament-specialist` — only if user selected `[B]`
Ask it to review the diff for:
- Correct Filament 5 patterns (Schemas, v5 namespaces, the `->columns(1)` root layout)
- Proper use of `relationship()` on form components
- State changes through service interfaces, not direct model manipulation (DA-22)

#### g) `database-specialist` — only if user selected `[C]`
Ask it to review the diff for:
- Migration correctness and rollback safety
- Proper indexes and foreign keys
- Eloquent relationship definitions and N+1 risks
- Factory and seeder correctness

#### h) `domain-specialist` — only if user selected `[D]`
Ask it to review the diff for:
- Correct DDD directory structure under `app/Domains/`, with `README.md` and `Docs/` (DA-18)
- Service provider registration
- Interface/implementation bindings (DA-17)
- Domain boundary violations

### 5. Consolidate and format the report

Collect all results from the agents and your direct analyses. Format the final report as:

---

# Code Review — PR #<PR_NUMBER>: <PR_TITLE>

**Branch:** `<BRANCH_NAME>` → `<BASE_BRANCH>`
**Author:** <AUTHOR>
**Files changed:** <N>

---

## Summary

<2-3 sentences with an overall assessment: ready to merge / needs changes / blocking issues>

---

## Blocking issues

> Issues that **must** be resolved before merging.

- ...

## Important issues

> Significant issues that should be addressed.

- ...

## Suggestions

> Non-blocking improvements and best practices.

- ...

## Missing tests

> Absent or insufficient test coverage.

- ...

## Final checklist

**Always:**
- [ ] `composer lint:check`, `composer canon:check` green
- [ ] No security issues
- [ ] Repositories injected only in service constructors
- [ ] Events dispatched via `event()` helper
- [ ] Tests cover new/modified code paths

**If HTTP layer `[A]`:**
- [ ] No inline validation in controllers
- [ ] Unix timestamps in API resources

**If Admin panel `[B]`:**
- [ ] Filament 5 patterns followed
- [ ] Service injection (no direct model manipulation)

**If Database `[C]`:**
- [ ] Migrations are rollback-safe
- [ ] No N+1 queries

**If New domain `[D]`:**
- [ ] Correct DDD structure under `app/Domains/`
- [ ] Service provider registered

---

### 6. Deliver output

- If the user chose **console**: print the report directly.
- If the user chose **file**: write it to `review-pr-<PR_NUMBER>.md` in the current working directory and confirm the path.

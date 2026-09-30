---
name: create-task
description: "Publish a markdown task breakdown to Jira — creating the epic too if the document declares one. Types, effort, components, status and blocking links all come from the document. Usage: /create-task DOCUMENT_PATH [EPIC_KEY]"
user-invocable: true
---

# Create task

Publish a task breakdown document to the tracker. The document is the source of truth: types,
estimates, components and blocking relationships were all decided during decomposition, and this
step copies them across rather than deciding anything new.

## Arguments

`$ARGUMENTS` is a document path and, optionally, an epic key:

```
/create-task docs/tasks/feature-x.md <KEY>-1234  # publish under an existing epic
/create-task docs/tasks/feature-x.md             # epic comes from the document
```

- **First token** — path to the markdown breakdown document (required)
- **Second token** — an existing epic key like `<KEY>-2422` (optional)

If the path is missing, ask for it and stop.

## 1. Work out where the epic comes from

Three cases, in order:

| The document / arguments say | Do |
|---|---|
| An epic key was passed, **or** the header has `> **Epic:** <KEY>-1234` | Use it. If both are present and differ, stop and ask which |
| The document has an **Epic to create** block | Create it first (TASK-08), then use the new key |
| Neither | **Stop and ask.** Does this work warrant an epic, or is it one Story with subtasks? That is a decomposition decision, not a publishing one — do not invent an epic to have somewhere to hang things |

Check the document exists before any of this, and that a passed key matches `[A-Z]+-[0-9]+`.

`<KEY>` is a placeholder until the Jira project exists. If `docs/standards/tasks.md` still
says `<KEY>`, stop and ask for the project key before publishing anything.

## 2. Check the document is publishable

Refuse to publish a document that is not ready, and say exactly what is missing:

- every task has a **Type** and an **Effort** (TASK-03, TASK-09) — a missing estimate is not for
  this step to invent
- every task has the five body sections
- blocking references name tasks that exist in the document
- efforts are in hours, and no leaf is wildly oversized (TASK-09 — over two days is a split signal,
  so raise it rather than publishing it)

## 3. Delegate to `task-specialist`

Launch it with:

> Publish the task breakdown at `<DOCUMENT_PATH>`.
>
> Epic: `<EPIC_KEY>` — or "create it from the document's Epic to create block, and report the key
> before creating anything else".
>
> Follow your own instructions in full. In particular:
> - **Types come from the document** (TASK-04). Do not default everything to Story. If there is no
>   `Bug` type in the project, a defect belongs on the support board
> - **Effort comes from the document** into `customfield_10172` in hours; never story points
> - Component into `customfield_10152`
> - Transition each issue to Sprint Ready (`2`, verify against the project) immediately after creating it
> - Create the blocking links programmatically with `createIssueLink`, **verifying the direction of
>   the first one before creating the rest** — there is no delete-link tool
> - The subtask type name differs by board (`Sottotask` or `Sub-task`) — use the one the project has

## 4. Report

- the epic — created or reused, with its key
- a table of created issues: key, title, type, effort, status
- every link as `{BLOCKER} blocks → {BLOCKED}`, so the direction can be spot-checked
- the total effort published, against the document's stated total — if they disagree, say so; it
  means something was missed or double-counted

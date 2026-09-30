# Tasks

How work is written down, broken up, and mapped onto the tracker.

A task is read twice: once by a person deciding what to do, and once by whoever implements it —
increasingly an AI assistant. It has to work for both. That means plain language with a predictable
shape, not ticket-speak and not a specification dump.

## Structure

**TASK-01 — Structured data goes in structured fields, never in the body.**

Effort in hours, components, priority, epic link, issue type, status, assignee, labels — all of it
belongs in the field built for it. A field restated in prose is a field that goes stale, and prose
cannot be filtered, summed or reported on.

The existing convention *"don't prefix a title with Backend/Frontend"* is one instance of this
general law: the component field already carries that, so the prefix is duplication that makes every
title longer and every board harder to scan.

**TASK-02 — Relationships are real tracker links** — `blocks`, `is blocked by` — never prose like
"do this after <KEY>-1234". A link is visible on the board, drives ordering, and survives the ticket
being reworded.

**TASK-03 — Every task follows the same body skeleton, in the breakdown document *and* in the
tracker.** They are one shape, not two, so publishing is a copy rather than a translation.

```markdown
## {Descriptive Task Title}

**Type:** Story · **Effort:** 6h · **Component:** Backend · **Blocked by:** {title, or nothing}

**Context** — why this exists; what is wrong or missing today
**Scope** — what changes; domains and files where they are known
**Acceptance criteria**
- [ ] each item independently verifiable
**Out of scope** — what this deliberately does not cover
**Links** — design docs, related tickets, prior art

### {Descriptive Subtask Title}
**Effort:** 3h

{the same sections, at subtask granularity}
```

The **metadata line becomes fields**; the **five sections become the body**. That is TASK-01 made
mechanical: if it is on the metadata line it belongs in a field, and if it is in a section it
belongs in the body. Nothing has to be decided twice.

Predictable headings are what let a task be picked up and worked on without a conversation first.
Plain language is what lets a person read it. Both, not either.

## Typing and splitting

**TASK-04 — Issue type is a decision, not a default.** The project lives on the company's Jira
site, so these are the site's issue types. **The ids and the set of types
are to be verified once the `<KEY>` project exists** — a project only offers the types its scheme
grants it.

| Type | id | When |
|---|---|---|
| **Epic** | `10185` | One outcome needing several independently-shippable tasks. See TASK-08 |
| **Story** | `10182` | User-visible value, framed as an outcome for a user or customer |
| **Technical Task** | `10198` | Engineering work with no direct user-visible change: refactors, library upgrades, devops, canon compliance, debt paydown. Boy-scout findings (GIT-06) become these |
| **Spike** | `10203` | A time-boxed question whose output is an answer, not shipped code. Anything built is throwaway and labelled so |
| **Design** | `10210` | Design work, paired with the `Design` component |
| **Sottotask** | `10186` | A slice of a parent that cannot stand alone. See TASK-05 |

**Check whether the project has a `Bug` type before filing one.** If defects live on a separate
support board instead, raise the engineering work in `<KEY>` as a **Technical Task** and link the two
(TASK-02) — do not copy the bug's content across.

**The subtask type can be named differently on each board** — `Sottotask` on one, `Sub-task` on
another. Creating a subtask with the other board's name fails, and it is the kind of failure that
looks like a permissions problem rather than a typo. Check which board you are writing to before you
create one.

**TASK-05 — The subtask test is independent mergeability.** If a part could be merged on its own and
leave the system working, it is a subtask. If it could not, it is an implementation step and belongs
in the parent's acceptance criteria — not in the tracker.

Splitting work that cannot ship separately creates tickets that can never be closed independently,
which is worse than not splitting at all: the board shows progress that isn't real, and every one of
them blocks on the same pull request.

**TASK-08 — An Epic exists when one outcome needs more than one independently-shippable task to
reach, and someone outside the team would name that outcome.**

Three consequences, each of which is the usual way epics go wrong:

- **An epic with one child is not an epic.** If a single Story with subtasks covers the work, that
  Story *is* the unit. An epic wrapping one Story adds a click and tells nobody anything.
- **An epic must be able to close.** "Tech debt", "Performance", "Auth improvements" are buckets,
  not epics: nothing makes them done, so they accumulate forever and eventually mean nothing. If you
  cannot say what is true when it closes, it is not an epic.
- **The epic carries the outcome; its children carry the work.** The epic body says why, and what
  done looks like. Implementation detail belongs in the children, where somebody will actually read
  it.

**TASK-09 — Estimates are decided during decomposition, not invented at publish time.**

Every leaf carries its estimate in the breakdown document **before** anything reaches the tracker,
where it is still cheap to argue with. Publishing copies that number into `customfield_10172`; it
never makes one up.

- **Estimate the leaf.** Whatever becomes an issue with no children carries the hours. A parent's
  estimate is the sum of its children's, stated so the total is visible.
- **A leaf over two days is a split signal, not a large leaf.** Either it hides more than one
  shippable thing (TASK-05), or it is not understood well enough to estimate — and both mean
  decompose further before putting a number on it.
- **Hours, never story points.** `customfield_10016` is unused here.

**TASK-10 — A task is a domain concept, not an artifact.**

If a concept needs a migration, a model, an entity, a factory, a repository, a service, an exception
and provider bindings, that is **one task** — not eight. Subtasks group related artifacts: the data
layer in one, the logic layer in another.

Never a task per file, never a subtask per class. Over-fragmentation produces a board that looks
busy and tells you nothing, and tickets that cannot close on their own (TASK-05).

**TASK-11 — When a plan exists, it is the input.**

If brainstorming and `writing-plans` produced a spec or an implementation plan, read them and lift
what they already decided: the **sequencing**, the **files and domains** each piece touches, and the
**verification** each step defines. Re-deriving any of that from prose is how a dependency graph
gets inverted — and there is no delete-link tool to undo it (TASK-02).

The verification a plan defines per step is usually the acceptance criteria a task needs (TASK-03),
already written once. Lift it rather than inventing a second version that will drift from the first.

**A plan step is not a task.** Plan steps are ordered work — "add the column", "wire the binding".
Tasks are domain concepts (TASK-10), and several plan steps normally collapse into one. Mapping
steps to tickets one-for-one is the fastest way to produce the fragmentation TASK-05 forbids.

**An estimate with no plan behind it is a guess, and should be labelled one.** TASK-09 asks for
hours on every leaf; where there is no plan to derive them from, say so when you hand the document
over, so the number is read as the estimate it is rather than the measurement it is not.

## Project specifics (`<KEY>`)

Field ids are site-wide and carry over as they are. Issue-type and transition ids depend on the
project's schemes and workflow — **verify them once the `<KEY>` project exists**, and replace the
placeholder key everywhere it appears (`docs/standards/`, `.claude/`).

| Thing | Value |
|---|---|
| Project key | `<KEY>` — placeholder until the project exists |
| Components field | `customfield_10152` — *Componenti*: Backend, DataScience, Frontend, Design |
| Estimated effort | `customfield_10172` — *Estimated Effort (Hours)*, a number of hours |
| Story points | **Not used.** Never set `customfield_10016` — this project estimates in hours |
| Issue types | Epic `10185` · Story `10182` · Technical Task `10198` · Spike `10203` · Design `10210` · Sottotask `10186` — verify |
| Epic link | the `parent` field on every non-Epic issue |
| Transition to Sprint Ready | `2` — applied immediately after creating an issue — verify |
| Transition to In Progress | `21` — verify |

**TASK-06 — Status reflects reality, and merged is not deployed.**

| Status | Means |
|---|---|
| **In Review** (`7`, verify) | The pull request is open |
| **READY TO DEPLOY** (`4`, verify) | Merged, not yet in production |
| **Done** (`31`, verify) | Deployed and live |

Read the board before a batch transition. Moving a ticket to Done because the PR merged is the most
common way a release record becomes fiction.

## Writing tasks, not code

**TASK-07 — A task says what outcome is needed and why, not how to implement it.** Name the domains
and files where they are genuinely known — that saves the implementer a search — but stop short of
prescribing the design. If the implementation is already decided, the decision belongs in the
Context section as a constraint, with its reason.

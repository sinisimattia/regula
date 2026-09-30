---
name: handoff
description: Write or update a handoff document (HANDOFF.md) so the next agent with fresh context can continue this work. Use when the user asks to hand off, wrap up a session, save progress for later, or prepare to continue in a new conversation.
---

Write or update a handoff document so the next agent — starting with zero context — can continue this work without repeating mistakes or re-discovering what is already known.

## Steps

1. **Check for an existing HANDOFF.md** in the project root. If it exists, read it fully before touching it: the update must preserve still-valid information and remove only what is now stale or resolved.

2. **Gather real state before writing.** Do not write from memory alone:
   - `git status` and `git log --oneline -10` for the actual state of the working tree and recent commits
   - Current branch and whether there are uncommitted or unpushed changes
   - If tests or builds were part of the work, note their *last verified* status (and when it was verified) — never claim "tests pass" without having seen it in this session

3. **Write or update HANDOFF.md** with these sections:

   - **Goal** — What we're trying to accomplish, in one or two sentences. Include the "why" if it constrains the solution.
   - **Current Progress** — What's been done so far. Be concrete: name files, commits, and decisions, not vague summaries.
   - **What Worked** — Approaches that succeeded, with enough detail to reproduce them (exact commands, key insights).
   - **What Didn't Work** — Approaches that failed and *why they failed*, so they're not repeated. This is the most valuable section for the next agent — don't skip it even if it feels embarrassing.
   - **Key Files** — Files central to the task, as `path/to/file.py:123` references with a one-line note on why each matters.
   - **Environment & Commands** — How to run/test/build the relevant parts (only commands actually verified to work in this session), plus any setup gotchas (env vars, services that must be running, credentials location — never the credentials themselves).
   - **Next Steps** — Ordered, actionable items for continuing. The first item should be startable immediately without further investigation. Flag any open decisions that need the user's input.

## Writing rules

- Write for a reader with **zero context**: no pronouns referring to this conversation ("the bug we found" → name the bug), no shorthand invented during this session.
- Prefer specifics over prose: exact error messages, exact file paths, exact commands.
- Keep it as short as completeness allows — the next agent will read this in full, so every stale or redundant line has a cost.
- Never include secrets, tokens, or credentials; point to where they live instead.

## Finish

Save as `HANDOFF.md` in the project root. Tell the user the absolute file path and that they can start a fresh conversation by just providing that path (e.g. "Read HANDOFF.md and continue").

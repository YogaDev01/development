---
name: refinement
description: Use for small, focused TESYS bug fixes, improvements and incremental changes taken directly from an instruction, without a separate planning phase. Not for HIGH/CRITICAL changes without approval.
tools: Read, Grep, Glob, Edit, Write, Bash
effort: medium
---

You are the TESYS **refinement** role. Your complete, canonical instructions are in `.claude/roles/refinement.md`.

First action: Read `.claude/roles/refinement.md` and `CLAUDE.md` (project root) in full with the Read tool, without offset or limit and not only §21 (even if CLAUDE.md is already in your context, that copy may be stale), then follow them.
This file only sets the tool permissions and entry point; do not duplicate or override the
role file. If this file and the role file ever disagree, the role file and CLAUDE.md win.

Hard limits that always apply: minimum necessary change; HIGH/CRITICAL (CLAUDE.md §20) needs approval first; no artisan test/migrate/seed unless the user asked and CLAUDE.md §4.3 is met; no git commit/push unless asked; never leave a long-running command unattended or piped through `tail` (CLAUDE.md §21.5).
Perform only the work of this role and report only what you actually did. Do not claim that any
other role (planner, executor, reviewer, evaluator, refinement) ran unless it actually did.

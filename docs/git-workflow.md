# Git workflow

## Target workflow

```
feature/<name>  ──PR──▶  develop  ──(review / testing / UAT)──▶  main  ──▶  production
```

- No development directly on `main`.
- One feature/fix per branch; small PRs.
- `main` = what is (or is about to be) in production. Tag releases (`v2.5.3`).
- Hotfix: `hotfix/<name>` from `main`, merged back into both `main` and `develop`.

## Current state (observed 2026-10-02)

| Item | State |
|------|-------|
| Current branch | `main` (tracks `origin/main`, at `70dfa9e` "Merge pull request #2 from YogaDev01/newdata") |
| Uncommitted work | **16 modified files on `main`** (Budget Plan, CR, Daily Transaction, Finance Summary, Project, Report controllers; BudgetPlan/BudgetRequest/Project/RequestDetail models; todo-list & budget-plan views; `.claude/launch.json`). ~247+/89−. Business-logic changes, not committed. |
| Local branches | `main`, `master` (= `development/develop` "Third Update"), `release/v2.3.1`, `feat/settlement-bulk-close` (0 unique commits — already merged; 25 behind `origin/main`) |
| Remotes | `origin` and `tesys` → both `github.com/YogaDev01/tesys.git` (duplicate remote); `development` → `github.com/YogaDev01/development.git` |
| Remote branches | `origin/main`, `origin/develop` (stale: "First Running Github Team"; 2 unique commits, 29 behind `origin/main`), `origin/master`, `origin/feature`, `origin/new-update`, `origin/newdata` |
| Practice so far | Work committed on `newdata`/`main`, merged to `main` via PR #2; release commits named `feat: rilis vX.Y.Z – …` |
| Deployment | Manual upload to Hostinger shared hosting (files listed per change and uploaded by hand). No CI/CD config in repo. |

### Update 2026-10-02 (baseline established, local only — not pushed)

- Uncommitted "Nilai Efektif" work moved to `feature/effective-amount`
  (unreleased, HIGH-risk calculation change; needs tests + Finance review).
- `docs/baseline`: CLAUDE.md + docs.
- Local `develop` created from `main` (`origin/develop` left untouched).
- Preventive fixes: `fix/h1-treasury-batch-lock`,
  `fix/h2-settlement-verify-lock`, `fix/h3-cash-receipt-allocate-lock`,
  each branched from `develop`.
- `.claude/launch.json` local change (other project) intentionally not committed.

## Gaps vs target

1. Active, uncommitted business-logic work sits on `main`.
2. `origin/develop` is stale and not used as integration branch.
3. `master`, `feature`, `new-update`, `newdata`, `release/v2.3.1` are
   leftovers; `feat/settlement-bulk-close` is fully merged.
4. Two remotes point to the same repo.
5. Line endings: Git warns CRLF→LF on the modified files (`.gitattributes`
   exists). Mixed endings make diffs noisy.

## Suggested migration (needs human decision — nothing has been changed)

1. Move the uncommitted work off `main`: `git switch -c feature/<name>` (keeps
   working-tree changes), commit there.
2. Reset `develop` to `origin/main` (`git push origin origin/main:develop
   --force-with-lease` — destructive to the old develop, confirm first).
3. Delete `feat/settlement-bulk-close` (already merged) after confirmation; review the 2 unique commits on `origin/develop` before resetting it.
4. Archive/delete stale remote branches after confirmation.
5. Enable branch protection on `main` (PR + review required) in GitHub.
6. Remove the duplicate `tesys` remote (optional).

## Rules for Claude

- Never switch branches, commit, push, merge, rebase, or delete branches
  unless explicitly asked.
- Before starting work, report the current branch and whether the tree is dirty.
- If asked to commit while on `main`, propose creating a feature branch first.

# Git workflow

## Target workflow

```
feature/<name> | fix/<name> | docs/<name>
        │  (merge --no-ff, lokal)
        ▼
develop  ──push──▶  origin/develop-v2  ──PR + review + UAT──▶  main  ──▶  production
```

- No development directly on `main`.
- One feature/fix per branch, branched from `develop`; small changes.
- Merge into `develop` with `--no-ff` so each fix stays one revertable unit.
- `main` = what is (or is about to be) in production. Tag releases (`v2.5.3`).
- Hotfix: `hotfix/<name>` from `main`, merged back into both `main` and `develop`.
- Local `develop` tracks **`origin/develop-v2`** (not `origin/develop`, which
  no longer exists — see below). Push with `git push origin develop:develop-v2`.

## Current state (updated 2026-10-02)

| Item | State |
|------|-------|
| `origin/main` | `70dfa9e` — production, default branch. Untouched. |
| `origin/develop-v2` | Integration branch: `main` + preventive concurrency fixes (H1–H3, M1, M3, Second Payment re-batch, Invoice/Surat Jalan locks), Invoice UI text, docs baseline. Suite 205/205. **Not yet released** — needs PR to `main` + UAT. |
| Remote branches removed (2026-10-02) | `origin/feature`, `origin/new-update`, `origin/newdata` (0 unique commits, already in `main`); `origin/develop` and `origin/master` (old initial snapshots, history unrelated to `main`). |
| Archive tags | `archive/old-develop` (`75974df`) and `archive/old-master` (`9e06274`) keep the old `develop`/`master` commits. Restore with `git switch -c <name> archive/old-develop`. |
| Local branches | `develop` (→ `origin/develop-v2`), `main`, `feature/effective-amount` (unreleased "Nilai Efektif" calculation change — needs tests + Finance review), `master` (old, tracks `development/develop`), `release/v2.3.1` (release marker, merged). |
| Remotes | `origin` = `github.com/YogaDev01/tesys.git`; `development` = separate repo `github.com/YogaDev01/development.git` (used only by local `master`, not managed here). The duplicate remote `tesys` (same URL as `origin`) was removed from local config on 2026-10-02. |
| Local working tree | `.claude/launch.json` has a local change for another project — intentionally never committed. |
| Deployment | Manual upload to Hostinger shared hosting (files listed per change and uploaded by hand). No CI/CD config in repo. |

## Remaining gaps

1. `develop-v2` is not yet merged to `main` (PR, review, UAT pending).
2. No branch protection on `main` in GitHub (PR + review required) — set in
   GitHub settings.
3. Local `master` and the `development` remote are leftovers from the old
   repository setup; decide whether to keep them.
4. The integration branch name `develop-v2` is temporary; optionally rename it
   to `develop` once the team agrees (old `origin/develop` is gone and archived).

## Before running tests

See `CLAUDE.md` §4: `bootstrap/cache/config.php` must not exist, and
`APP_ENV=testing DB_DATABASE=tesys_test php artisan config:show
database.connections.mysql.database` must print `tesys_test`.

## Rules for Claude

- Never switch branches, commit, push, merge, rebase, or delete branches
  unless explicitly asked.
- Before starting work, report the current branch and whether the tree is dirty.
- If asked to commit while on `main`, propose creating a feature branch first.
- Never delete a remote branch with unique commits without archiving it first
  (tag) and getting explicit approval.

# Backend-only CI/CD Pipeline for Anjem

## Context

Anjem is moving to a two-environment Forge setup: `api.anjem.me` (prod, auto-deploys from `main`) and `staging-api.anjem.me` (staging, auto-deploys from `staging`). Going forward, the staging site is the dev environment — local backend testing will be rare.

The existing GitHub Actions workflows (`laravel-ci.yml`, `flutter-ci.yml`, `pr-checks.yml`, `claude.yml`, `claude-code-review.yml`) are stale and unaligned with the actual prod stack (wrong PHP/Postgres versions, references mobile flows that no longer apply, baked-in `.env.testing` with real Firebase creds). They will all be deleted and replaced by a single new backend workflow.

Mobile builds are explicitly **out of scope** for CI — Alan sideloads APKs via USB using existing `Makefile` targets (`build-rider`, `build-driver`, `build-staging-rider`, `build-staging-driver`).

The desired git flow:
1. Branch `xxx/yyy` off `main`, work + commit locally
2. `git checkout staging && git merge xxx/yyy && git push` → Forge deploys staging
3. Test on phone against `staging-api.anjem.me`
4. Open PR `xxx/yyy → main`, CI runs, merge when green → Forge deploys prod
5. `git checkout staging && git reset --hard main && git push --force` to reset staging

## Goals

- One backend CI workflow that runs on (a) PRs to `main` as a merge gate and (b) pushes to `staging` as informational signal
- Branch protection on `main` requiring the workflow to pass before merge
- CI environment must mirror prod: PHP 8.4, Postgres 17 + PostGIS, Redis 7
- Zero mobile CI surface
- Sanitize `backend/.env.testing` so CI doesn't depend on a file that contains real secrets

## Workflow Design

### File: `.github/workflows/backend-ci.yml`

**Triggers:**
```yaml
on:
  pull_request:
    branches: [main]
    paths: ['backend/**', '.github/workflows/backend-ci.yml']
  push:
    branches: [staging]
    paths: ['backend/**', '.github/workflows/backend-ci.yml']
```

**Single job: `test`** (runs on `ubuntu-latest`, working-directory `backend/`)

Steps:
1. `actions/checkout@v4`
2. `shivammathur/setup-php@v2` — PHP **8.4**, extensions: `mbstring, dom, fileinfo, pgsql, redis, bcmath, intl, gd, exif, pcntl, posix, pdo_pgsql`, coverage: none
3. **Postgres 17 + PostGIS service** — image `postgis/postgis:17-3.4`, env `POSTGRES_USER/PASSWORD/DB=anjem_test`, port 5432, health check
4. **Redis 7 service** — image `redis:7-alpine`, port 6379, health check
5. **Composer cache** — `actions/cache@v4` keyed on `composer.lock`
6. `composer install --no-interaction --prefer-dist --optimize-autoloader`
7. **Bootstrap test env**:
   - `cp .env.example .env`
   - `php artisan key:generate`
   - Inline-export the DB/Redis/queue/cache env vars in the workflow `env:` block (don't rely on `.env.testing` file)
8. `php artisan migrate --force` (against `anjem_test`)
9. **Lint:** `./vendor/bin/pint --test`
10. **Static analysis:** `./vendor/bin/phpstan analyse --memory-limit=1G`
11. **Tests:** `php artisan test --parallel` (or non-parallel if flakes)
12. **Security audit:** `composer audit` (continue-on-error allowed since false positives are common)

**Inline env block** (avoids leaking real `.env.testing` secrets):
```yaml
env:
  APP_ENV: testing
  DB_CONNECTION: pgsql
  DB_HOST: 127.0.0.1
  DB_PORT: 5432
  DB_DATABASE: anjem_test
  DB_USERNAME: anjem_test
  DB_PASSWORD: anjem_test
  REDIS_HOST: 127.0.0.1
  REDIS_PORT: 6379
  CACHE_DRIVER: redis
  SESSION_DRIVER: redis
  QUEUE_CONNECTION: sync          # tests run jobs synchronously
  MAIL_MAILER: array
  BROADCAST_DRIVER: log
  FILESYSTEM_DISK: local
```

Anything Firebase/Mapbox/Reverb-related should be set to dummy values via the same `env:` block so service-instantiating tests don't crash. Tests that genuinely need a real Mapbox/Firebase round-trip should be skipped in CI via `markTestSkipped()` based on env.

## Cleanup: Files to Delete

- `.github/workflows/laravel-ci.yml`
- `.github/workflows/flutter-ci.yml`
- `.github/workflows/pr-checks.yml`
- `.github/workflows/claude.yml`
- `.github/workflows/claude-code-review.yml`

**Keep** `.github/dependabot.yml` and `.github/labeler.yml` (independent, low maintenance, useful).

## Backend repo cleanup (sanitize test env)

`backend/.env.testing` currently contains real Firebase service-account credentials per the exploration. Two options, pick the simpler:

- **Recommended:** delete `backend/.env.testing` from the repo, add it to `.gitignore`, and rely entirely on the workflow's inline `env:` block + `.env.example` for CI. Locally, anyone who runs `php artisan test` can copy `.env.example` to `.env.testing` and set their own DB.
- Alternative: rewrite `.env.testing` with placeholder values (no real keys) and check it back in.

Either way, rotate the leaked Firebase credentials before pushing the cleanup commit.

## Branch & Repo Setup (one-time, manual)

These steps need to happen on GitHub / Forge after the workflow file lands:

1. **Rename `staging-sprint` → `staging`** (preserves Alan's current feature work):
   - Locally: `git branch -m staging-sprint staging && git push origin -u staging && git push origin --delete staging-sprint`
   - Update any open PRs / Forge site config to point at `staging`
2. **Forge:** point `staging-api.anjem.me` site at the new `staging` branch with auto-deploy enabled
3. **GitHub branch protection on `main`:**
   - Settings → Branches → Add rule for `main`
   - Require status checks to pass before merging → select `test` (the job from `backend-ci.yml`)
   - Require branches to be up to date before merging
   - (Optional) Disallow force pushes
   - **Do not** require PR reviews — Alan is solo
4. **GitHub branch protection on `staging`:** none. Force pushes must remain allowed because the git flow includes `git push --force` to reset staging after each cycle.

## Forge Integration Notes

- Forge auto-deploys on push regardless of GitHub Actions status. For `main`, this is fine because branch protection ensures only green code reaches `main`. For `staging`, broken commits *can* deploy — that's acceptable since staging is the dev environment and the red ❌ on the commit serves as a heads-up while testing on the phone.
- If a stricter staging gate is ever wanted later, the path is: disable Forge auto-deploy, have `backend-ci.yml` POST to a Forge deploy webhook in a final step. Out of scope for now.

## Critical Files

- **Create:** `.github/workflows/backend-ci.yml`
- **Delete:** `.github/workflows/laravel-ci.yml`, `flutter-ci.yml`, `pr-checks.yml`, `claude.yml`, `claude-code-review.yml`
- **Modify or delete:** `backend/.env.testing` (sanitize or remove)
- **Modify:** `backend/.gitignore` (if `.env.testing` becomes ignored)
- **Reference (no edits):** `backend/composer.json`, `backend/phpunit.xml`, `backend/phpstan.neon`, `backend/.env.example`

## Verification

After the workflow file is in place:

1. **Smoke-test the workflow on a throwaway branch:**
   - Branch off main, push a trivial backend change, open a draft PR to `main`
   - Confirm `backend-ci.yml` runs end-to-end and goes green (Pint, PHPStan, migrations, tests, audit)
   - Intentionally break Pint formatting → confirm CI goes red and the merge button is blocked once branch protection is on
2. **Test the `staging` trigger:**
   - Merge the throwaway branch into `staging` and push
   - Confirm CI runs against the `staging` branch and Forge deploys in parallel
   - Hit `https://staging-api.anjem.me/api/v1/health` (or equivalent) to confirm the deploy worked
3. **Confirm branch protection blocks merges:**
   - With CI red, attempt to merge a PR to `main` → GitHub should show "Required statuses must pass"
4. **Validate the cleanup:**
   - `gh workflow list` shows only `backend-ci.yml` (plus dependabot if it surfaces)
   - `git grep -r FIREBASE_CREDENTIALS backend/.env.testing` returns nothing (or the file is gone)
5. **End-to-end git flow dry run:** execute steps 1–7 of the proposed flow on a no-op feature branch and confirm each handoff works (branch → staging push → phone test → PR → merge → Forge prod deploy → staging reset).

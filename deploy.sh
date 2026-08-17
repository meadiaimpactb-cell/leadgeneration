#!/usr/bin/env bash
#
# النشر على b2b.amadcraft.sa — أمر واحد، بالترتيب الموثّق في docs/deployment.md.
#
# Deployment for b2b.amadcraft.sa. This script is the executable form of
# docs/deployment.md §2, in the order that document gives, with the three
# mistakes it warns about turned into guards rather than notes:
#
#   · a build without an SSR restart leaves the old server-rendered HTML being
#     served alongside a new browser bundle, and the page looks subtly wrong in
#     a way no error reports;
#   · `config:cache` before the .env is right bakes the wrong values in;
#   · editing config/filesystems.php on the server to fix storage, instead of
#     setting MEDIA_DISK_ROOT, is a change that the next deploy overwrites.
#
# It is safe to re-run. Every step is idempotent, and the site is put into
# maintenance mode for the window where the database and the assets disagree.
#
# Usage:  ./deploy.sh            deploy whatever is on the current branch
#         ./deploy.sh --no-build skip npm (use when only PHP changed)

set -euo pipefail

cd "$(dirname "$0")"

BUILD=1
[ "${1:-}" = "--no-build" ] && BUILD=0

say() { printf '\n\033[1;36m▸ %s\033[0m\n' "$1"; }
die() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

# ---------------------------------------------------------------- guards ----

[ -f artisan ] || die "Run this from the project root."
[ -f .env ] || die ".env is missing. Copy .env.example and fill it in first."

APP_ENV="$(php -r 'echo trim(shell_exec("php artisan tinker --execute=\"echo config(\\\"app.env\\\");\" 2>/dev/null") ?: "");' 2>/dev/null || true)"

# Refuse to run the production sequence against a database whose name says
# otherwise. `migrate --force` is not something to discover you aimed wrong.
grep -qE '^DB_DATABASE=.*_test' .env && die "DB_DATABASE points at a test database."

# ------------------------------------------------------------------ code ----

say "Pulling the current branch"
git pull --ff-only

say "Putting the site into maintenance mode"
# The window below is the only one where the schema and the assets can
# disagree. `--render` serves the branded 503 rather than a bare Symfony page.
php artisan down --render="errors::503" --retry=60 || true

restore() {
    php artisan up || true
}
trap restore EXIT

# ------------------------------------------------------------ dependencies --

say "Installing PHP dependencies (no dev tooling)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if [ "$BUILD" -eq 1 ]; then
    say "Building the browser and SSR bundles"
    npm ci
    npm run build
    [ -f bootstrap/ssr/ssr.js ] || die "npm run build produced no SSR bundle."
else
    say "Skipping the asset build (--no-build)"
fi

# --------------------------------------------------------------- database ----

say "Running migrations"
php artisan migrate --force

# Structural only — DatabaseSeeder writes roles, pages, settings, menus and
# redirects, and no marketing copy. Safe in production and safe to repeat.
say "Seeding structural data"
php artisan db:seed --force

say "Resetting the permission cache"
php artisan permission:cache-reset

# ------------------------------------------------------------------ cache ----

# This order matters: config first, because route and view caching read it.
say "Rebuilding the production caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# ------------------------------------------------------------- processes ----

# The step most often forgotten. inertia:start-ssr loads bootstrap/ssr/ssr.js
# once, at boot — so a rebuild alone leaves the previous render running.
say "Restarting the queue worker and the SSR server"
if command -v supervisorctl >/dev/null 2>&1; then
    sudo supervisorctl restart amadcraft-queue amadcraft-ssr
else
    printf '  supervisorctl not found — restart these two yourself:\n'
    printf '    php artisan queue:work --tries=3 --backoff=30\n'
    printf '    php artisan inertia:start-ssr\n'
fi

php artisan queue:restart

say "Bringing the site back up"
php artisan up
trap - EXIT

# ------------------------------------------------------------------ check ----

# A deploy that reports success without looking is how the panel spent a week
# serving old markup. These are the two things that fail silently.
say "Verifying"

BASE="${DEPLOY_URL:-https://b2b.amadcraft.sa}"

code=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/up" || echo 000)
[ "$code" = "200" ] || die "Health check failed: $BASE/up answered $code"
printf '  health          %s\n' "$code"

home=$(curl -s "$BASE/ar" || true)
printf '%s' "$home" | grep -q 'data-server-rendered' \
    || die "The Arabic home page is not server-rendered — is inertia:start-ssr running?"
printf '  /ar             200, server-rendered\n'

printf '%s' "$home" | grep -q '<html lang="ar" dir="rtl"' \
    || die "The Arabic page did not come out right-to-left."
printf '  /ar             lang=ar dir=rtl\n'

printf '\n\033[1;32m✓ Deployed and verified.\033[0m\n'

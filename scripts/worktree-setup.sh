#!/usr/bin/env bash
# Prepare a git worktree an agent can code, build and test in without touching
# the main checkout or production.
#
#   scripts/worktree-setup.sh <name>     → .claude/worktrees/<name>, branch <name> off master
#
# - .env is the main one but its database is a local throwaway (pm_app_<name> on
#   the docker pgsql), so artisan commands in the worktree never reach the cloud DB.
# - Tests need nothing: tests/bootstrap.php gives every process its own database.
# - vendor, node_modules and Filament's published assets are APFS clones (instant,
#   copy-on-write); the gitignored root AGENTS.md / CLAUDE.md are copied in.
set -euo pipefail

name="${1:?worktree name}"
root="$(cd "$(git rev-parse --git-common-dir)/.." && pwd)"
path="$root/.claude/worktrees/$name"
app_db="pm_app_${name//-/_}"
pg=(docker exec project-manager-pgsql-1 psql -U pm -d postgres -tAc)

[ -d "$path" ] || git -C "$root" worktree add -q "$path" -b "$name" master

[ "$("${pg[@]}" "SELECT 1 FROM pg_database WHERE datname='$app_db'")" = 1 ] || "${pg[@]}" "CREATE DATABASE $app_db;" >/dev/null

for dir in vendor node_modules public/css/filament public/js/filament public/fonts/filament; do
  [ -e "$path/$dir" ] || { mkdir -p "$(dirname "$path/$dir")" && cp -Rc "$root/$dir" "$path/$dir"; }
done
cp "$root/AGENTS.md" "$root/CLAUDE.md" "$path/"

grep -vE '^DB_(URL|HOST|PORT|DATABASE|USERNAME|PASSWORD|SSLMODE)=' "$root/.env" >"$path/.env"
cat >>"$path/.env" <<ENV
DB_HOST=127.0.0.1
DB_PORT=54329
DB_DATABASE=$app_db
DB_USERNAME=pm
DB_PASSWORD=pm
DB_SSLMODE=disable
ENV

cd "$path"
php artisan migrate --force --no-interaction --quiet
npm run build --silent >/dev/null 2>&1

cat <<MSG
worktree ready: $path (branch $name, local db $app_db)
  test:  cd $path && php artisan test --compact <file>
  done:  scripts/worktree-teardown.sh $name
MSG

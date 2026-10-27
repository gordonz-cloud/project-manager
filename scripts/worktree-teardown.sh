#!/usr/bin/env bash
# Undo scripts/worktree-setup.sh once the branch is merged: remove the worktree,
# delete the branch and drop its local database.
#
#   scripts/worktree-teardown.sh <name> [--force]
#
# Refuses while the worktree has uncommitted changes or the branch is not in
# master; --force discards that work.
set -euo pipefail

name="${1:?worktree name}"
force="${2:-}"
root="$(cd "$(git rev-parse --git-common-dir)/.." && pwd)"
path="$root/.claude/worktrees/$name"
app_db="pm_app_${name//-/_}"
pg=(docker exec project-manager-pgsql-1 psql -U pm -d postgres -tAc)
cd "$root"

if [ "$force" != "--force" ]; then
  if [ -d "$path" ] && [ -n "$(git -C "$path" status --porcelain)" ]; then
    echo "refusing: $path has uncommitted changes (--force discards them)" >&2
    exit 1
  fi
  if git rev-parse -q --verify "refs/heads/$name" >/dev/null && ! git merge-base --is-ancestor "$name" master; then
    echo "refusing: branch $name is not merged into master (--force discards it)" >&2
    exit 1
  fi
fi

[ -d "$path" ] && git worktree remove --force "$path"
git branch -D "$name" 2>/dev/null || true
"${pg[@]}" "DROP DATABASE IF EXISTS $app_db WITH (FORCE);" >/dev/null

echo "worktree $name gone: checkout, branch, database $app_db"

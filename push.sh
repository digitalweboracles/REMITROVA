#!/usr/bin/env bash
#
# push.sh — pushes this folder to your GitHub repo.
#
# Usage:
#   bash push.sh "commit message here"
#
# If you don't pass a message, it uses a default one with today's date.
# Safe to re-run: won't force-push over existing history by accident —
# it checks first and tells you what to do if there's a conflict.

set -e

REPO_URL="https://github.com/digitalweboracles/REMITROVA.git"
BRANCH="main"
COMMIT_MSG="${1:-Update from Claude — $(date +%Y-%m-%d)}"

echo "==> Checking for a .gitignore"
if [ ! -f .gitignore ]; then
  cat > .gitignore << 'EOF'
/vendor
/node_modules
.env
/storage/logs/*.log
/.phpunit.cache
.DS_Store
EOF
  echo "    created .gitignore"
else
  echo "    already exists, leaving it alone"
fi

echo "==> Initializing git (safe if already initialized)"
if [ ! -d .git ]; then
  git init -q
  git branch -M "$BRANCH"
fi

echo "==> Setting remote 'origin' to $REPO_URL"
if git remote get-url origin >/dev/null 2>&1; then
  git remote set-url origin "$REPO_URL"
else
  git remote add origin "$REPO_URL"
fi

echo "==> Staging and committing"
git add -A
if git diff --cached --quiet; then
  echo "    nothing to commit — working tree matches last commit"
else
  git commit -q -m "$COMMIT_MSG"
  echo "    committed: $COMMIT_MSG"
fi

echo "==> Fetching remote state to check for conflicts"
git fetch origin "$BRANCH" -q 2>/dev/null || true

REMOTE_EXISTS=$(git ls-remote --heads origin "$BRANCH" 2>/dev/null | wc -l | tr -d ' ')

if [ "$REMOTE_EXISTS" = "0" ]; then
  echo "==> Remote branch doesn't exist yet — pushing fresh"
  git push -u origin "$BRANCH"
else
  # There's already history on the remote branch. Try a normal push
  # first (safe — never overwrites). If it's rejected because local
  # and remote have diverged, stop and say so instead of guessing.
  echo "==> Remote branch exists — attempting a normal (safe) push"
  if git push -u origin "$BRANCH" 2>/tmp/push_err.log; then
    echo "    pushed cleanly"
  else
    echo ""
    echo "!! Push was rejected — local and remote history have diverged."
    echo "!! This usually means the repo already has commits this folder doesn't know about."
    echo ""
    echo "   To pull in the remote history and merge (safest, keeps everything):"
    echo "     git pull origin $BRANCH --allow-unrelated-histories"
    echo "     git push -u origin $BRANCH"
    echo ""
    echo "   To wipe the remote and replace it entirely with THIS folder"
    echo "   (only do this if you're sure the remote has nothing you need):"
    echo "     git push -u origin $BRANCH --force"
    echo ""
    exit 1
  fi
fi

echo ""
echo "==> Done. Latest commit on $BRANCH:"
git log -1 --oneline

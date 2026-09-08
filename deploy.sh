#!/usr/bin/env bash
#
# deploy.sh — the ONE script to run after unzipping a new delivery.
#
# Clones a FRESH, clean, up-to-date copy of the actual GitHub repo,
# copies the new files on top of it, and pushes. Since the clone
# starts already in sync with GitHub, there's no divergence possible.
#
# Usage: bash deploy.sh "your commit message"

set -e

REPO_URL="https://github.com/digitalweboracles/REMITROVA.git"
BRANCH="main"
COMMIT_MSG="${1:-Update from Claude — $(date +%Y-%m-%d)}"
SOURCE_DIR="$(pwd)"
CLONE_DIR="$HOME/Downloads/remitrova-deploy-$(date +%s)"

if [ ! -f "$SOURCE_DIR/composer.json" ]; then
  echo "!! No composer.json found in the current folder ($SOURCE_DIR)."
  echo "!! cd into the folder you just unzipped (the one with composer.json, app/, routes/ directly inside it) and run this again."
  exit 1
fi

echo "==> Cloning a fresh copy of your repo"
git clone -q "$REPO_URL" "$CLONE_DIR"
cd "$CLONE_DIR"
git checkout -q "$BRANCH" 2>/dev/null || git checkout -q -b "$BRANCH"

echo "==> Copying new files into the fresh clone"
if command -v rsync >/dev/null 2>&1; then
  rsync -a --exclude='.git' "$SOURCE_DIR"/ "$CLONE_DIR"/
else
  cp -a "$SOURCE_DIR"/. "$CLONE_DIR"/
fi

chmod +x artisan deploy.sh 2>/dev/null || true

if [ ! -f .gitignore ]; then
  cat > .gitignore << 'EOF'
/vendor
/node_modules
.env
/storage/logs/*.log
/.phpunit.cache
.DS_Store
EOF
fi

echo "==> Staging and committing"
git add -A
if git diff --cached --quiet; then
  echo "    nothing to commit — this clone already matches what you have locally"
else
  git commit -q -m "$COMMIT_MSG"
  echo "    committed: $COMMIT_MSG"
fi

echo "==> Pushing"
git push -u origin "$BRANCH"

echo ""
echo "==> Done. Latest commit on $BRANCH:"
git log -1 --oneline
echo ""
echo "This fresh clone lives at: $CLONE_DIR"

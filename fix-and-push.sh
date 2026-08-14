#!/usr/bin/env bash
set -e

REPO_URL="https://github.com/digitalweboracles/REMITROVA.git"
BRANCH="main"
COMMIT_MSG="${1:-Fix + push - $(date +%Y-%m-%d)}"
CHANGED=0

echo "=========================================="
echo " Pre-flight checks"
echo "=========================================="

if [ -f composer.json ]; then
  if grep -q '"laravel/framework": "\^11\.0"' composer.json; then
    echo "--> Fixing composer.json: widening laravel/framework constraint to allow v12"
    if sed --version >/dev/null 2>&1; then
      sed -i 's/"laravel\/framework": "\^11\.0"/"laravel\/framework": "^11.44|^12.0"/' composer.json
    else
      sed -i '' 's/"laravel\/framework": "\^11\.0"/"laravel\/framework": "^11.44|^12.0"/' composer.json
    fi
    CHANGED=1
    echo "    done"
  else
    echo "--> composer.json already fixed (or uses a different constraint) - skipping"
  fi
else
  echo "!! composer.json not found - are you in the remitrova-backend folder? Aborting."
  exit 1
fi

if [ -f composer.lock ] && [ "$CHANGED" = "1" ]; then
  echo "--> composer.lock exists and composer.json just changed - removing stale lock file"
  rm composer.lock
fi

if git ls-files --error-unmatch .env >/dev/null 2>&1; then
  echo "--> .env is currently tracked by git - untracking it"
  git rm --cached .env -q
  CHANGED=1
fi

if git ls-files --error-unmatch vendor >/dev/null 2>&1; then
  echo "--> vendor/ is currently tracked by git - untracking it"
  git rm -r --cached vendor -q
  CHANGED=1
fi

if [ ! -f .gitignore ]; then
  echo "--> No .gitignore found - creating one"
  cat > .gitignore << 'EOF'
/vendor
/node_modules
.env
/storage/logs/*.log
/.phpunit.cache
.DS_Store
EOF
  CHANGED=1
fi

echo ""
echo "=========================================="
echo " Committing and pushing"
echo "=========================================="

if [ ! -d .git ]; then
  git init -q
  git branch -M "$BRANCH"
fi

if git remote get-url origin >/dev/null 2>&1; then
  git remote set-url origin "$REPO_URL"
else
  git remote add origin "$REPO_URL"
fi

git add -A
if git diff --cached --quiet; then
  echo "    nothing to commit - working tree matches last commit"
else
  git commit -q -m "$COMMIT_MSG"
  echo "    committed: $COMMIT_MSG"
fi

git fetch origin "$BRANCH" -q 2>/dev/null || true
REMOTE_EXISTS=$(git ls-remote --heads origin "$BRANCH" 2>/dev/null | wc -l | tr -d ' ')

if [ "$REMOTE_EXISTS" = "0" ]; then
  echo "==> Remote branch doesn't exist yet - pushing fresh"
  git push -u origin "$BRANCH"
else
  echo "==> Remote branch exists - attempting a normal (safe) push"
  if git push -u origin "$BRANCH" 2>/tmp/push_err.log; then
    echo "    pushed cleanly"
  else
    echo ""
    echo "!! Push was rejected - local and remote history have diverged."
    echo "   Safe fix:   git pull origin $BRANCH --allow-unrelated-histories && git push -u origin $BRANCH"
    echo "   Force fix:  git push -u origin $BRANCH --force   (only if remote has nothing you need)"
    echo ""
    exit 1
  fi
fi

echo ""
echo "==> Done. Latest commit on $BRANCH:"
git log -1 --oneline
echo ""
echo "Now check the Railway dashboard for the new build."

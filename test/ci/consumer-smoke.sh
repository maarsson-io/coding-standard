#!/usr/bin/env sh
set -eu

info() {
  printf "[\033[34mINFO\033[0m] %s\n" "$1"
}
ok() {
  printf "[\033[32m OK \033[0m] %s\n" "$1"
}
fail() {
  printf "[\033[31mFAIL\033[0m] %s\n" "$1" >&2;exit 1;
}

WORKDIR="${CODING_STANDARD_SMOKE_WORKDIR:-/tmp/coding-standard-consumer-smoke}"
CONSUMER="$WORKDIR/consumer"
REPO="$WORKDIR/repo"

info "Preparing consumer project workspace…"
rm -rf "$CONSUMER"
mkdir -p "$CONSUMER"
cd "$CONSUMER"

info "Writing consumer composer.json (path repository)…"
cat > composer.json <<'JSON'
{
  "name": "maarsson/coding-standard-consumer-smoke",
  "description": "CI consumer project for testing maarsson/coding-standard package functions",
  "type": "project",
  "require": {
    "php": "^8.4"
  },
  "require-dev": {
    "maarsson/coding-standard": "*"
  },
  "repositories": [
    {
      "type": "path",
      "url": "../repo",
      "options": {
        "symlink": false
      }
    }
  ],
  "minimum-stability": "dev",
  "prefer-stable": true
}
JSON

info "Installing dependencies…"
composer config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer config --no-plugins allow-plugins.phpro/grumphp false
composer install --no-interaction --prefer-dist

info "Asserting the tool binaries are installed…"
for binary in phpcs phpcbf php-cs-fixer phpmd phpstan parallel-lint grumphp; do
  test -x "./vendor/bin/$binary" || fail "$binary binary missing"
done

info "Running sync script…"
./vendor/bin/sync-coding-standards.php

info "Asserting files exist in project root…"
test -f phpmd.yml || fail "phpmd.yml was not copied to project root"
test -f .phpcs.xml || fail ".phpcs.xml was not copied to project root"
test ! -f phpcs.xml || fail "Legacy phpcs.xml should not be created"
test -f .php-cs-fixer.php || fail ".php-cs-fixer.php was not copied to project root"
test -f phpstan.neon || fail "phpstan.neon was not copied to project root"
test -f eslint.config.mjs || fail "eslint.config.mjs was not copied to project root"
test -f stylelint.config.mjs || fail "stylelint.config.mjs was not copied to project root"
test -f grumphp.yml || fail "grumphp.yml was not copied to project root"
test -f .github/workflows/ci.yml || fail "CI workflow was not copied to .github/workflows"
test -f docs/CODE_QUALITY.md || fail "CODE_QUALITY.md was not copied to docs"
for hook in pre-commit commit-msg pre-push; do
  test -x ".githooks/$hook" || fail "$hook was not installed as executable"
  cmp -s ".githooks/$hook" "./vendor/maarsson/coding-standard/resources/githooks/$hook" || fail "$hook mismatch error"
done

info "Asserting the frontend tools are added to package.json…"
test -f package.json || fail "package.json was not created"
grep -Fq '"eslint": "eslint ."' package.json || fail "ESLint script was not added to package.json"
grep -Fq '"stylelint": "^' package.json || fail "Stylelint dependency was not added to package.json"

info "Asserting files match package dist versions…"
cmp -s phpmd.yml ./vendor/maarsson/coding-standard/resources/phpmd.yml.dist || fail "phpmd.yml mismatch error"
cmp -s .phpcs.xml ./vendor/maarsson/coding-standard/resources/phpcs.xml.dist || fail ".phpcs.xml mismatch error"
cmp -s .php-cs-fixer.php ./vendor/maarsson/coding-standard/resources/php-cs-fixer.php.dist || fail "php-cs-fixer.php mismatch error"
cmp -s phpstan.neon ./vendor/maarsson/coding-standard/resources/phpstan.neon.dist || fail "phpstan.neon mismatch error"

cmp -s grumphp.yml ./vendor/maarsson/coding-standard/resources/grumphp.yml.dist || fail "grumphp.yml mismatch error"

cmp -s eslint.config.mjs ./vendor/maarsson/coding-standard/resources/eslint.config.mjs.dist || fail "eslint.config.mjs mismatch error"

cmp -s stylelint.config.mjs ./vendor/maarsson/coding-standard/resources/stylelint.config.mjs.dist || fail "stylelint.config.mjs mismatch error"

cmp -s .github/workflows/ci.yml ./vendor/maarsson/coding-standard/resources/ci.yml.dist || fail "CI workflow mismatch error"
cmp -s docs/CODE_QUALITY.md ./vendor/maarsson/coding-standard/resources/docs/CODE_QUALITY.md || fail "CODE_QUALITY.md mismatch error"

MANAGED_FILES="phpmd.yml .phpcs.xml .php-cs-fixer.php phpstan.neon grumphp.yml eslint.config.mjs stylelint.config.mjs .github/workflows/ci.yml docs/CODE_QUALITY.md .githooks/pre-commit .githooks/commit-msg .githooks/pre-push"
for file in $MANAGED_FILES; do
  test ! -e "$file.backup" || fail "$file backup should not exist after first sync"
done

info "Asserting Git hook activation…"
git init -q
git config core.hooksPath .old-hooks
./vendor/bin/sync-coding-standards.php
test "$(git config --local core.hooksPath)" = .githooks || fail "Git hooks were not activated"
for file in $MANAGED_FILES; do
  test ! -e "$file.backup" || fail "$file was backed up although it did not change"
done

info "Asserting hook permissions are restored without a backup…"
chmod -x .githooks/pre-commit
./vendor/bin/sync-coding-standards.php
test -x .githooks/pre-commit || fail "pre-commit permissions were not restored"
test ! -e .githooks/pre-commit.backup || fail "pre-commit was backed up although its content did not change"

info "Asserting the debug statement check runs with the installed GrumPHP…"
test -x vendor/bin/check-debug-statements.php || fail "check-debug-statements.php was not installed as executable"
printf "export const value = 1;\n" > clean.js
git add clean.js
./vendor/bin/check-debug-statements.php || fail "Debug statement check failed for clean files"
printf "console.log('debug');\n" > debug.js
git add debug.js
if ./vendor/bin/check-debug-statements.php > "$WORKDIR/debug-check.log" 2>&1; then
  fail "Debug statement check should fail for a tracked debug statement"
fi
grep -Fq 'debug.js' "$WORKDIR/debug-check.log" || fail "Debug statement was not reported"
git rm -q --cached clean.js debug.js
rm clean.js debug.js

info "Asserting always-overwrite behavior…"
echo "local change" >> phpmd.yml
echo "local change" >> .phpcs.xml
echo "local change" >> .php-cs-fixer.php
echo "local change" >> phpstan.neon
echo "local change" >> grumphp.yml
echo "local change" >> eslint.config.mjs
echo "local change" >> stylelint.config.mjs
echo "# local workflow change" >> .github/workflows/ci.yml
echo "local change" >> docs/CODE_QUALITY.md
for hook in pre-commit commit-msg pre-push; do
  echo "local change" >> ".githooks/$hook"
  chmod -x ".githooks/$hook"
done
mkdir "$CONSUMER/expected-backups"
for file in $MANAGED_FILES; do
  cp "$file" "$CONSUMER/expected-backups/$(basename "$file")"
done
./vendor/bin/sync-coding-standards.php
for file in $MANAGED_FILES; do
  cmp -s "$file.backup" "$CONSUMER/expected-backups/$(basename "$file")" || fail "$file local changes were not backed up"
done
cmp -s phpmd.yml ./vendor/maarsson/coding-standard/resources/phpmd.yml.dist || fail "phpmd.yml overwrite error"

cmp -s .phpcs.xml ./vendor/maarsson/coding-standard/resources/phpcs.xml.dist || fail ".phpcs.xml overwrite error"

cmp -s .php-cs-fixer.php ./vendor/maarsson/coding-standard/resources/php-cs-fixer.php.dist || fail ".php-cs-fixer.php overwrite error"

cmp -s phpstan.neon ./vendor/maarsson/coding-standard/resources/phpstan.neon.dist || fail "phpstan.neon overwrite error"

cmp -s grumphp.yml ./vendor/maarsson/coding-standard/resources/grumphp.yml.dist || fail "grumphp.yml overwrite error"
for hook in pre-commit commit-msg pre-push; do
  test -x ".githooks/$hook" || fail "$hook permissions were not restored"
  cmp -s ".githooks/$hook" "./vendor/maarsson/coding-standard/resources/githooks/$hook" || fail "$hook overwrite error"
done

cmp -s eslint.config.mjs ./vendor/maarsson/coding-standard/resources/eslint.config.mjs.dist || fail "eslint.config.mjs overwrite error"

cmp -s stylelint.config.mjs ./vendor/maarsson/coding-standard/resources/stylelint.config.mjs.dist || fail "stylelint.config.mjs overwrite error"
cmp -s .github/workflows/ci.yml ./vendor/maarsson/coding-standard/resources/ci.yml.dist || fail "CI workflow overwrite error"
cmp -s docs/CODE_QUALITY.md ./vendor/maarsson/coding-standard/resources/docs/CODE_QUALITY.md || fail "CODE_QUALITY.md overwrite error"

info "Asserting an unchanged sync creates no backup…"
./vendor/bin/sync-coding-standards.php
for file in $MANAGED_FILES; do
  test ! -e "$file.backup.1" || fail "$file was backed up although it did not change"
done

info "Asserting a later change gets a numbered backup…"
mkdir "$CONSUMER/expected-second-backups"
for file in $MANAGED_FILES; do
  echo "# second local change" >> "$file"
  cp "$file" "$CONSUMER/expected-second-backups/$(basename "$file")"
done
./vendor/bin/sync-coding-standards.php
for file in $MANAGED_FILES; do
  cmp -s "$file.backup.1" "$CONSUMER/expected-second-backups/$(basename "$file")" || fail "$file numbered backup was not created"
  cmp -s "$file.backup" "$CONSUMER/expected-backups/$(basename "$file")" || fail "$file first backup was overwritten"
done
cmp -s grumphp.yml ./vendor/maarsson/coding-standard/resources/grumphp.yml.dist || fail "grumphp.yml second overwrite error"
rm -rf "$CONSUMER/expected-backups" "$CONSUMER/expected-second-backups"

info "Asserting the documentation path option…"
./vendor/bin/sync-coding-standards.php --docs-path=docs/development
cmp -s docs/development/CODE_QUALITY.md ./vendor/maarsson/coding-standard/resources/docs/CODE_QUALITY.md || fail "CODE_QUALITY.md was not copied to the configured path"
./vendor/bin/sync-coding-standards.php --docs_path=handbook/
cmp -s handbook/CODE_QUALITY.md ./vendor/maarsson/coding-standard/resources/docs/CODE_QUALITY.md || fail "CODE_QUALITY.md was not copied to the underscore option path"
for invalid in ../outside /tmp/outside docs/../outside ""; do
  if ./vendor/bin/sync-coding-standards.php "--docs-path=$invalid" > "$CONSUMER/docs-path-failure.log" 2>&1; then
    fail "Sync should reject the documentation path '$invalid'"
  fi
  grep -Fq 'Invalid --docs-path value' "$CONSUMER/docs-path-failure.log" || fail "Invalid documentation path was not reported"
done
test ! -e ../outside/CODE_QUALITY.md || fail "CODE_QUALITY.md was written outside the project"

info "Asserting backup failure prevents overwrite…"
rm phpmd.yml
mkdir phpmd.yml
echo "preserve me" > phpmd.yml/local-change
if ./vendor/bin/sync-coding-standards.php > "$CONSUMER/sync-failure.log" 2>&1; then
  fail "Sync should fail when a backup cannot be created"
fi
test -f phpmd.yml/local-change || fail "Backup failure did not preserve the existing target"
grep -Fq 'Failed to back up phpmd.yml; file was not overwritten.' "$CONSUMER/sync-failure.log" || fail "Backup failure was not reported"

ok "Consumer smoke test passed."

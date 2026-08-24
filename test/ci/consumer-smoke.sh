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

WORKDIR="/tmp/coding-standard-consumer-smoke"
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
composer install --no-interaction --prefer-dist

info "Running sync script…"
./vendor/bin/sync-coding-standards.php

info "Asserting files exist in project root…"
test -f phpmd.xml || fail "phpmd.xml was not copied to project root"
test -f phpcs.xml || fail "phpcs.xml was not copied to project root"
test -f .php-cs-fixer.php || fail ".php-cs-fixer.php was not copied to project root"
test -f phpstan.neon || fail "phpstan.neon was not copied to project root"

info "Asserting files match package dist versions…"
cmp -s phpmd.xml ./vendor/maarsson/coding-standard/resources/phpmd.xml.dist || fail "phpmd.xml mismatch error"
cmp -s phpcs.xml ./vendor/maarsson/coding-standard/resources/phpcs.xml.dist || fail "phpcs.xml mismatch error"
cmp -s .php-cs-fixer.php ./vendor/maarsson/coding-standard/resources/php-cs-fixer.php.dist || fail "php-cs-fixer.php mismatch error"
cmp -s phpstan.neon ./vendor/maarsson/coding-standard/resources/phpstan.neon.dist || fail "phpstan.neon mismatch error"

info "Asserting always-overwrite behavior…"
echo "local change" >> phpmd.xml
./vendor/bin/sync-coding-standards.php
cmp -s phpmd.xml ./vendor/maarsson/coding-standard/resources/phpmd.xml.dist || fail "phpcs.xml overwrite error"

ok "Consumer smoke test passed."

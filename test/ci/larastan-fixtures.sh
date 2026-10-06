#!/usr/bin/env sh
set -eu

case "${1:-}" in
  GoodCode|BadCode) fixture="$1" ;;
  *) echo "Usage: $0 GoodCode|BadCode" >&2; exit 2 ;;
esac

repo="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
workdir="$(mktemp -d "${TMPDIR:-/tmp}/coding-standard-larastan.XXXXXX")"
trap 'rm -rf "$workdir"' EXIT HUP INT TERM

# Evaluate the shared config from a project root, as consumers do.
ln -s "$repo/vendor" "$workdir/vendor"
cp "$repo/resources/phpstan.neon.dist" "$workdir/phpstan.neon"
cd "$workdir"

"$repo/vendor/bin/phpstan" analyse \
  --configuration=phpstan.neon \
  --memory-limit=1G \
  --no-progress \
  --debug \
  "$repo/test/fixtures/Larastan/$fixture"

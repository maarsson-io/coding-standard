# Code Quality

This project uses the shared rules of [maarsson/coding-standard](https://github.com/maarsson-io/coding-standard). Git hooks run the checks locally on every commit and push, and the CI workflow repeats them on the server.

**Goal:** maintain high code quality based on common rules, without slowing down development.

This file is managed by that package and is overwritten on every sync. Do not edit it here.

## What runs when

| Check | Tool | Commit | Push | CI |
| --- | --- | :-: | :-: | :-: |
| PHP syntax | [PHP Parallel Lint](https://github.com/php-parallel-lint/PHP-Parallel-Lint) | ✓ | | ✓ |
| Supported PHP version | [GrumPHP](https://github.com/phpro/grumphp) | ✓ | | |
| Composer configuration | [Composer](https://getcomposer.org/doc/03-cli.md#validate) | ✓ | | ✓ |
| Composer autoloading | [Composer](https://getcomposer.org/doc/03-cli.md#dump-autoload-dumpautoload) | | ✓ | ✓ |
| JSON, XML, and YAML syntax | [GrumPHP](https://github.com/phpro/grumphp) | ✓ | | ✓ |
| Coding standard | [PHP CodeSniffer (PHPCS)](https://github.com/PHPCSStandards/PHP_CodeSniffer/) with the [Slevomat](https://github.com/slevomat/coding-standard/) sniffs | ✓ | | ✓ |
| Code style | [PHP CS Fixer](https://cs.symfony.com/), check only | ✓ | | ✓ |
| JavaScript, TypeScript, and Vue | [ESLint](https://eslint.org/) | ✓ | | ✓ |
| CSS, SCSS, and Vue styles | [Stylelint](https://stylelint.io/) | ✓ | | ✓ |
| Debug statements and development markers | [GrumPHP](https://github.com/phpro/grumphp) | ✓ | | ✓ |
| Protected branch names | [GrumPHP](https://github.com/phpro/grumphp) | ✓ | | |
| Commit message format | [GrumPHP](https://github.com/phpro/grumphp) | ✓ | | |
| Static analysis and dead code | [PHPStan](https://phpstan.org/) with [Larastan](https://github.com/larastan/larastan) and the [dead code detector](https://github.com/shipmonk-rnd/dead-code-detector) | | ✓ | ✓ |
| Design and complexity | [PHP Mess Detector (PHPMD)](https://phpmd.org/) | | ✓ | ✓ |
| Tests | [PHPUnit](https://phpunit.de/) | | ✓ | ✓ |

Commit checks look at the staged files. Push and CI checks cover the whole codebase.

## When a check fails

A failed check blocks the commit or push. Nothing is lost: staged changes stay staged, and local commits stay in place.

1. Read the output. It names the failed task, the file, and the line.
2. Fix the problem. Some tools can fix their own findings; review the result before staging it.
3. Stage the fix and repeat the commit or push.

| Tool | Fix command |
| --- | --- |
| PHPCS | `./vendor/bin/phpcbf <path>` |
| PHP CS Fixer | `./vendor/bin/php-cs-fixer fix <path>` |
| ESLint | `npx eslint <path> --fix` |
| Stylelint | `npx stylelint <path> --fix` |

Do not bypass the hooks with `--no-verify`. CI runs the same checks and rejects the change later.

## Rules

### Debug statements and development markers

The following are rejected in PHP, Blade, JavaScript, TypeScript, and Vue files:

- `dd()`, `dump()`, `var_dump()`, `print_r()`, `var_export()`, `die()`, `exit()`
- `console.log()`, `console.error()`, `console.warn()`, `console.debug()`, `debugger;`, `alert()`
- the markers `FIXME:`, `HACK:`, and `XXX:`
- merge conflict markers

A keyword called as a method, such as `Dialog.alert()` or `$this->dump()`, is not reported. Files under `public/vendor` are skipped.

### Branches

Direct commits to `develop`, `staging`, `staging/*`, and `master` are rejected. Work on a separate branch and merge through a pull request.

### Commit messages

```
<type>(<optional scope>): <subject>

<optional body>
```

| Type | Use for |
| --- | --- |
| `feat` | New functionality |
| `fix` | Bug fixes |
| `docs` | Documentation only |
| `style` | Formatting without logic changes |
| `refactor` | Restructuring without behavior changes |
| `test` | Adding or fixing tests |
| `chore` | Tooling and maintenance |
| `build` | Build system and dependencies |
| `ci` | CI/CD configuration |
| `perf` | Performance improvements |
| `revert` | Reverting a commit |

- The scope uses lowercase letters, digits, and hyphens.
- Mark a breaking change with `!` before the colon, for example `feat(api)!: remove legacy endpoints`.
- The subject is a single line of at most 72 characters without a trailing period.
- Body lines are at most 72 characters.

## Running the checks manually

Run the commands from the project root.

| Check | Command |
| --- | --- |
| All commit checks | `./vendor/bin/grumphp run --testsuite=git_pre_commit` |
| All push checks | `./vendor/bin/grumphp run --testsuite=git_pre_push` |
| PHP syntax | `./vendor/bin/grumphp run --tasks=phplint` |
| Composer configuration | `composer validate --strict` |
| Composer autoloading | `./vendor/bin/grumphp run --tasks=composer_validate_autoload` |
| JSON, XML, and YAML syntax | `./vendor/bin/grumphp run --tasks=jsonlint,xmllint,yamllint` |
| Debug statements, all tracked files | `./vendor/bin/check-debug-statements.php` |
| PHPCS | `./vendor/bin/phpcs --parallel=4 .` |
| PHP CS Fixer | `./vendor/bin/php-cs-fixer check --diff` |
| PHPMD | `./vendor/bin/phpmd analyze . --format ansi --ruleset phpmd.yml` |
| PHPStan | `./vendor/bin/phpstan analyse --no-progress --memory-limit=-1` |
| Tests | `php artisan test` |
| ESLint | `npx eslint . --cache --cache-location .eslint.cache` |
| Stylelint | `npx stylelint "**/*.{css,scss,vue}" --cache --cache-location .stylelint.cache` |

`grumphp run` skips the debug statement check, because GrumPHP runs it only during an actual commit. Use the separate command for it.

Run PHPStan on the complete codebase, so that dead code detection sees references across files and tests.

PHPCS, PHP CS Fixer, PHPMD, and PHPStan cache their results on their own. ESLint and Stylelint cache only with the options shown above. The cache files match `*.cache`, which the project should ignore in Git.

## Changing a rule

The rule files, the hooks, and the CI workflow come from the shared package and are overwritten on every sync. A change made in this project does not last. Propose rule changes in the [maarsson/coding-standard](https://github.com/maarsson-io/coding-standard) repository.

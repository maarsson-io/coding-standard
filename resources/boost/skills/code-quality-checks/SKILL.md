---
name: code-quality-checks
description: Run and interpret the code quality checks configured by maarsson/coding-standard when changing or verifying code, including legacy projects with existing failures.
---

# Code Quality Checks

## Select the checks

Work from the consumer project's root. Read its `composer.json`, `package.json`, `grumphp.yml`, and the relevant root-level tool configurations. Prefer existing project scripts when they provide the required checks and scope. The commands below are defaults for this package, not commands for testing the coding-standard repository itself.

Report missing executables or configurations as errors; do not automatically install tools, synchronize configurations, or use `npx` to download missing executables.

For code changes, select checks according to the affected file types and the nature and risk of the change. The table identifies relevant checks; it does not require running the entire toolset after every edit. Tests are the exception: every code change requires running the project's tests relevant to it, whatever the changed file type. Explain any relevant checks omitted from verification. Do not consider the change complete while it introduces ruleset violations or compliance remains insufficiently verified.

For a code review, run all configured code-quality checks and tests over their configured scope. Only report a green review when verification establishes that the reviewed changes introduce no new failures. Where failures occur, compare findings against the review's base revision under the same configuration and scope to distinguish new failures from pre-existing ones. Report pre-existing failures separately without fixing unrelated code; they do not prevent a green review. Missing prerequisites, checks that could not run, or failures that cannot be reliably classified leave the review unverified.

| Changed code | Checks |
| --- | --- |
| Any code | Tests relevant to the change |
| PHP | Syntax lint, PHPCS, PHP-CS-Fixer check, PHPMD, PHPStan/Larastan |
| JavaScript / TypeScript | ESLint |
| Vue | ESLint and Stylelint |
| CSS / SCSS | Stylelint |
| Composer or JSON / XML / YAML files | Applicable validation tasks from `grumphp.yml` |

Respect each configuration's file selection and exclusions, including Blade exclusions in PHP style checks. Do not assume that frontend source files live under `resources/`; legacy projects may use other directories.

## Commands and scope

Use installed local executables. These commands check the configured codebase without applying fixes:

```sh
./vendor/bin/phpcs --parallel=4 .
./vendor/bin/php-cs-fixer check --diff
./vendor/bin/phpmd analyze . --format ansi --ruleset phpmd.yml
./vendor/bin/phpstan analyse --no-progress --memory-limit=-1
./node_modules/.bin/eslint . --cache --cache-location .eslint.cache
./node_modules/.bin/stylelint "**/*.{css,scss,vue}" --cache --cache-location .stylelint.cache
```

The tools find their root-level configuration files on their own. PHPCS and PHPMD take their cache settings from the rulesets, and PHP-CS-Fixer and PHPStan cache automatically; only ESLint and Stylelint need the cache options shown. Choose output formats, diff display, and progress options as needed to interpret and compare diagnostics.

When the project's workflow permits checks on affected files, replace `.` in PHPCS, PHPMD, or ESLint with the relevant paths; pass individual paths to Stylelint. PHPMD accepts comma-separated paths. For PHP-CS-Fixer, append the relevant paths with `--path-mode=intersection` so explicit paths retain the configured Finder exclusions. Quote paths containing spaces.

Always run PHPStan over the complete scope in `phpstan.neon`, including tests. Passing only changed files can produce incorrect dead-code findings or miss references. The shared GrumPHP PHPStan task uses `use_grumphp_paths: false` for this reason.

For PHP syntax, use the configured GrumPHP `phplint` task or `php -l` on affected PHP files. Run the tests relevant to the change using the project's configured test command. When no test covers the changed code, report that instead of treating the change as verified by tests.

Read the installed GrumPHP test-suite definitions to identify other checks applicable to the changed files, such as `jsonlint`, `xmllint`, and `yamllint`.

## Fix and verify

When implementing code changes, validate only the changes made for the task. Limit checks to affected files wherever the tool supports reliable validation at that scope. When a selected analysis requires the full configured codebase, as with PHPStan dead-code detection, run that analysis but assess the findings for failures introduced by the changes. For code reviews, use each check's full configured scope.

Where legacy failures are known, capture the relevant findings before editing and compare them after the change under the same configuration and scope. Compare individual diagnostics, accounting for shifted line numbers; equal error totals do not establish that no new failures were introduced. If the original findings are unavailable or cannot be compared reliably, report that verification limit.

Do not run automatic fixes without an explicit user instruction to do so. When appropriate, suggest the relevant commands for the user to run.

Fix failures introduced by the change within the requested scope. For explicitly requested automatic fixes, restrict the command to the affected files: use PHPCBF, the PHP-CS-Fixer `fix` command, or ESLint / Stylelint with `--fix`. PHP-CS-Fixer enables risky rules, so review the resulting diff for behavior changes. Rerun the applicable checks after fixing.

Do not weaken rules, add suppressions or baseline entries, or bypass hooks to make checks pass. Do not expand the task into unrelated legacy cleanup. Report the commands and scope used, new failures, pre-existing failures, and checks that could not run separately; do not describe an incomplete verification as passing.

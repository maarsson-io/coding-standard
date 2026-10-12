# maarsson/coding-standard

Centralized custom coding standards that can be applied across my different projects.

[![Latest Stable Version](https://img.shields.io/github/v/release/maarsson-io/coding-standard?label=Latest)](https://github.com/maarsson-io/coding-standard/releases)
[![Minimum PHP Version](https://img.shields.io/packagist/dependency-v/maarsson/coding-standard/php.svg)](https://packagist.org/packages/maarsson/coding-standard)
[![Tests](https://github.com/maarsson-io/coding-standard/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/maarsson-io/coding-standard/actions/workflows/ci.yml)
[![License](https://img.shields.io/github/license/maarsson-io/coding-standard)](https://github.com/maarsson-io/coding-standard/blob/master/LICENSE)

## About

This package holds centrally maintained, opinionated code quality rulesets and applies them to individual projects automatically. The rules are maintained in one place, and every project receives the same version of them whenever its Composer dependencies are installed or updated.

The rulesets and the CI workflow target Laravel applications.

### How it works

- **It installs the tools.** The package requires every PHP tool its rulesets need, and adds the frontend tools to the project's `package.json`.
- **It syncs the rulesets.** A sync script copies the shared configuration files into the project after `composer install` and `composer update`.
- **It activates local checks.** The synced Git hooks run the checks through [GrumPHP](https://github.com/phpro/grumphp) on every commit and push.
- **It activates CI checks.** The synced GitHub Actions workflow repeats the checks on the server, because local hooks can be bypassed and CI cannot.
- **It documents the workflow.** A synced [code quality guide](resources/docs/CODE_QUALITY.md) tells the project's developers what runs when, what to do when a check fails, and which conventions apply.

### Included checks

| Check | Tool | Commit | Push | CI |
| --- | --- | :-: | :-: | :-: |
| PHP syntax | [PHP Parallel Lint](https://github.com/php-parallel-lint/PHP-Parallel-Lint) | ✓ | | ✓ |
| Supported PHP version | GrumPHP | ✓ | | |
| Composer configuration | Composer | ✓ | | ✓ |
| Composer autoloading | Composer | | ✓ | ✓ |
| JSON, XML, and YAML syntax | GrumPHP | ✓ | | ✓ |
| Coding standard | [PHP CodeSniffer (PHPCS)](https://github.com/PHPCSStandards/PHP_CodeSniffer/) with the [Slevomat](https://github.com/slevomat/coding-standard/) sniffs | ✓ | | ✓ |
| Code style | [PHP CS Fixer](https://cs.symfony.com/), check only | ✓ | | ✓ |
| JavaScript, TypeScript, and Vue | [ESLint](https://eslint.org/) | ✓ | | ✓ |
| CSS, SCSS, and Vue styles | [Stylelint](https://stylelint.io/) | ✓ | | ✓ |
| Debug statements and development markers | GrumPHP | ✓ | | ✓ |
| Protected branch names | GrumPHP | ✓ | | |
| Commit message format | GrumPHP | ✓ | | |
| Static analysis and dead code | [PHPStan](https://phpstan.org/) with [Larastan](https://github.com/larastan/larastan) and the [dead code detector](https://github.com/shipmonk-rnd/dead-code-detector) | | ✓ | ✓ |
| Design and complexity | [PHP Mess Detector (PHPMD)](https://phpmd.org/) | | ✓ | ✓ |
| Tests | PHPUnit | | ✓ | ✓ |

The hooks block the operation when a check fails. No check applies automatic fixes.

---

## Installation

### Requirements

- PHP `>=8.4 <8.6`
- Composer
- Git
- For the frontend tools: npm and Node.js `^22.13.0 || >=24`

### Install the package

```sh
composer config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer config --no-plugins allow-plugins.phpro/grumphp false
composer require --dev maarsson/coding-standard
```

The package pulls in the PHP tools itself. The two plugin permissions must be set in the project's `composer.json`, because a dependency cannot grant them: the PHPCS installer registers the Slevomat sniffs, and the GrumPHP plugin stays disabled because the sync activates the hooks itself.

The project must provide PHPUnit and a `phpunit.xml`.

### Configure the Composer scripts

Add the sync script to the project's `composer.json` and run it after every install and update:

```json
{
  "scripts": {
    "coding-standard:sync": [
      "@php -r \"if (getenv('COMPOSER_DEV_MODE') !== '0') { require 'vendor/bin/sync-coding-standards.php'; }\""
    ],
    "post-install-cmd": [
      "@coding-standard:sync"
    ],
    "post-update-cmd": [
      "@coding-standard:sync"
    ]
  }
}
```

Composer sets `COMPOSER_DEV_MODE=0` when `install` or `update` runs with `--no-dev`. The condition skips the sync in that mode, because this development dependency and its executable are not installed. This prevents production deployments from failing with `vendor/bin/sync-coding-standards.php: not found`. Development installs still run the sync and propagate any failure.

See [Composer script environment variables](https://getcomposer.org/doc/articles/scripts.md#defining-scripts) for details.

#### Documentation path

The sync copies the code quality guide to `docs/CODE_QUALITY.md`. To place it elsewhere, append the `--docs-path` option to the script, after a `--` separator:

```json
"coding-standard:sync": [
  "@php -r \"if (getenv('COMPOSER_DEV_MODE') !== '0') { require 'vendor/bin/sync-coding-standards.php'; }\" -- --docs-path=docs/development"
]
```

The guide is then written to `docs/development/CODE_QUALITY.md`. Set the option in the script definition, not on the command line, so that automatic and manual syncs use the same location. The path must be relative to the project root. Changing it later leaves the previous copy in place; delete that file yourself.

### Run the first sync

```sh
composer coding-standard:sync
```

Then:

1. Run `npm install` to install the frontend tools the sync added to `package.json`.
2. Add `*.cache` and `*.backup*` to `.gitignore`.
3. Commit the synced files together with `package.json` and `package-lock.json`. The CI workflow checks the committed files and does not sync.

### Updating

```sh
composer update maarsson/coding-standard --with-dependencies
```

The option also moves the tools to the newest versions the package allows. Run `npm install` when the sync reports a `package.json` change, and review the resulting `git diff` before committing; see [Overwrite behavior](#overwrite-behavior).

### Upgrading from 2.x

Version 3 replaces the `maarsson/dev-tools` metapackage: this package now requires the tools itself. In a project that installed the metapackage, swap the two:

```sh
composer remove --dev maarsson/dev-tools --no-update
composer require --dev maarsson/coding-standard:^3.0 --with-all-dependencies
```

The plugin permissions, the Composer scripts, and the frontend tools in `package.json` stay as they are.

---

## What the sync command does

The sync runs after `composer install` and `composer update` when development dependencies are installed and Composer scripts are enabled. Run it manually at any time with `composer coding-standard:sync`, or directly with `./vendor/bin/sync-coding-standards.php` from the project root.

### It copies the shared files

| File | Purpose |
| --- | --- |
| `phpmd.yml` | PHPMD ruleset |
| `.phpcs.xml` | PHPCS ruleset |
| `.php-cs-fixer.php` | PHP CS Fixer configuration |
| `phpstan.neon` | PHPStan configuration |
| `grumphp.yml` | GrumPHP tasks and test suites |
| `eslint.config.mjs` | ESLint configuration |
| `stylelint.config.mjs` | Stylelint configuration |
| `.github/workflows/ci.yml` | GitHub Actions workflow |
| `docs/CODE_QUALITY.md` | Code quality guide for the project's developers; see [Documentation path](#documentation-path) |
| `.githooks/pre-commit`, `.githooks/commit-msg`, `.githooks/pre-push` | Git hooks |

### It updates package.json

The frontend tool list comes from this package's `extra.frontend-tools` setting.

- The tools are added to `devDependencies`.
- The `eslint`, `eslint:fix`, `stylelint`, and `stylelint:fix` scripts are created.
- The managed entries are recorded under the `maarsson-coding-standard` key, so a later sync can update or remove them.

The sync does not install npm packages. It prints a reminder when `package.json` changed; run `npm install` and commit `package.json` together with `package-lock.json`.

The sync stops with an error, before copying any file, in two cases: a managed tool is listed under `dependencies`, or a managed entry already exists in `package.json` with a different value that an earlier sync did not write.

### It activates the Git hooks

When run from the root of a Git repository, the sync makes the hooks executable and sets the local `core.hooksPath` to `.githooks`. Linked worktrees are supported. Outside a repository root the files are still copied and the activation is skipped.

### Overwrite behavior

The sync always overwrites the copied files. This is intentional: it guarantees that every project uses the same rules.

- Before a file is replaced, its current contents are saved next to it as `<filename>.backup`, then `<filename>.backup.1`, `<filename>.backup.2`, and so on. A backup is created on every sync, even when the file did not change.
- If a backup cannot be created, that file is not overwritten and the sync exits with an error.

Project-specific adjustments are not supported by the package. When a project needs one, such as an extra CI job or the Vue 2 ESLint ruleset, keep it in version control: after a sync, review the `git diff`, commit what the package changed, and restore the project-specific parts.

### The CI workflow

The synced workflow assumes a Laravel application tested with `php artisan test` on an in-memory SQLite database.

- It reports the checks `CI Tests`, `CI Test PHP 8.4`, and `CI Test PHP 8.5`. Use these names for required status checks.
- It installs Composer dependencies without scripts, so it verifies the committed rulesets and never syncs.
- Its actions are pinned to commit SHAs. A repository that restricts allowed actions must permit them.

---

## Laravel Boost integration

This package provides a guideline and the `code-quality-checks` skill for Laravel Boost. The skill explains which checks to run, their scope, and how to compare failures in legacy projects.

Run `php artisan boost:install`, or `php artisan boost:update` for an existing Boost setup, and select `maarsson/coding-standard` when it is offered. Boost offers new packages only when run from the terminal, not as a Composer script such as `post-update-cmd`. Once selected, the package stays in `boost.json` and later updates refresh its guideline and skill automatically.

---

## Developer guide

The checks, the conventions, the manual commands, and the steps to take when a check fails are described in the [code quality guide](resources/docs/CODE_QUALITY.md). The sync copies it into every project, so the developers of a project read the version that matches their installed rules.

### The debug statement script

GrumPHP runs its `git_blacklist` task only in the pre-commit context, on the staged files. `./vendor/bin/check-debug-statements.php` applies the same check to every tracked file, and the CI workflow uses it. It reads the `git_blacklist` options from `grumphp.yml` and selects files with GrumPHP's own filters, so it follows the hook's keywords, extensions, and whitelist and ignore patterns.

| Situation | Result |
| --- | --- |
| A keyword is found | Fails and lists the matches |
| No keywords are configured | Passes with a notice |
| No tracked file matches the filters | Passes with a notice |
| `grumphp.yml` is missing | Fails, because the check could not run |

### ESLint and Vue 2

The shared configuration uses `vue.configs['flat/recommended']`, which targets Vue 3. For a Vue 2 project, replace it in `eslint.config.mjs` with:

```js
vue.configs['flat/vue2-recommended']
```

Select the ruleset that matches the installed Vue major version before running `--fix`. Vue 3 rules can apply incompatible fixes to Vue 2 code, such as renaming `beforeDestroy` to `beforeUnmount`; the renamed hook will not run in Vue 2. The sync overwrites `eslint.config.mjs`, so restore this adjustment after each sync.

---

## Troubleshooting

***My local changes were overwritten***

This is the intended [overwrite behavior](#overwrite-behavior). Restore the project-specific parts from version control or from the `.backup` file created next to the overwritten file.

***Conflicting package.json entry / Frontend tool already exists in dependencies***

The sync manages the frontend tools in `devDependencies` and four npm scripts. Move the named tool from `dependencies` to `devDependencies`, or remove the project's own version of the named entry, then sync again.

***Source file not found***

The package is not installed correctly, or Composer install/update did not complete. Try:

```sh
composer install
composer coding-standard:sync -vvv
```

***Sync script not executable***

Ensure your environment supports running scripts from `vendor/bin` and that file permissions are preserved.

---

## License

[MIT](LICENSE)

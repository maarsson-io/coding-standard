# maarsson/coding-standard

Centralized custom coding standards that can be applied across my different projects.

[![Latest Stable Version](https://img.shields.io/github/v/release/maarsson-io/coding-standard?label=Latest)](https://github.com/maarsson-io/coding-standard/releases)
[![Minimum PHP Version](https://img.shields.io/packagist/dependency-v/maarsson/coding-standard/php.svg)](https://packagist.org/packages/maarsson/coding-standard)
[![Tests](https://github.com/maarsson-io/coding-standard/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/maarsson-io/coding-standard/actions/workflows/ci.yml)
[![License](https://img.shields.io/github/license/maarsson-io/coding-standard)](https://github.com/maarsson-io/coding-standard/blob/master/LICENSE)

> [!NOTE]
> See also [maarsson/dev-tools](https://github.com/maarsson-io/dev-tools).

## About

This package holds centrally maintained, opinionated code quality rulesets and applies them to individual projects automatically. The rules are maintained in one place, and every project receives the same version of them whenever its Composer dependencies are installed or updated.

The rulesets and the CI workflow target Laravel applications.

### How it works

- **It syncs the rulesets.** A sync script copies the shared configuration files into the project after `composer install` and `composer update`.
- **It activates local checks.** The synced Git hooks run the checks through [GrumPHP](https://github.com/phpro/grumphp) on every commit and push.
- **It activates CI checks.** The synced GitHub Actions workflow repeats the checks on the server, because local hooks can be bypassed and CI cannot.

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

### Recommended: through the metapackage

The [maarsson/dev-tools](https://github.com/maarsson-io/dev-tools) metapackage installs this package together with every tool the rulesets need. Follow its [installation steps](https://github.com/maarsson-io/dev-tools#installation), then [configure the Composer scripts](#configure-the-composer-scripts) below.

### Manual

Install the package as a development dependency:

```sh
composer require --dev maarsson/coding-standard
```

The package ships the rulesets only. Install the tools yourself:

```sh
composer config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer config --no-plugins allow-plugins.phpro/grumphp false
composer require --dev phpmd/phpmd
composer require --dev squizlabs/php_codesniffer
composer require --dev slevomat/coding-standard
composer require --dev friendsofphp/php-cs-fixer
composer require --dev larastan/larastan
composer require --dev shipmonk/dead-code-detector:^1.4
composer require --dev php-parallel-lint/php-parallel-lint:^1.4
composer require --dev phpro/grumphp:^2.25
npm install --save-dev eslint@^10 @eslint/js@^10 @stylistic/eslint-plugin@^5 eslint-plugin-vue@^10 globals@^17 typescript@^6 typescript-eslint@^8
npm install --save-dev stylelint@^17 @stylistic/stylelint-plugin@^5 postcss-html@^2 stylelint-config-standard@^40 stylelint-config-standard-scss@^17 stylelint-config-recommended-vue@^2 stylelint-config-standard-vue@^2 @dreamsicle.io/stylelint-config-tailwindcss@^1.2.2
```

The GrumPHP Composer plugin is disabled because the sync script installs and activates the hooks itself. The project must provide PHPUnit and a `phpunit.xml`.

The synced CI workflow calls `npm run eslint` and `npm run stylelint`. With a manual installation, add these scripts to `package.json` yourself; see [Running the checks manually](#running-the-checks-manually) for the commands.

<a id="2-project-configuration-required"></a>

### Configure the Composer scripts

Required for both installation methods. Add the sync script to the project's `composer.json` and run it after every install and update:

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

### Run the first sync

```sh
composer coding-standard:sync
```

Then:

1. Run `npm install` if the sync reported a `package.json` change.
2. Add `*.cache` and `*.backup*` to `.gitignore`.
3. Commit the synced files. The CI workflow checks the committed files and does not sync.

### Updating

```sh
composer update maarsson/coding-standard
```

When the package is installed through `maarsson/dev-tools`, update both:

```sh
composer update maarsson/dev-tools --with-dependencies
```

Review the resulting `git diff` before committing; see [Overwrite behavior](#overwrite-behavior).

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
| `.githooks/pre-commit`, `.githooks/commit-msg`, `.githooks/pre-push` | Git hooks |

### It updates package.json

This step runs only when `maarsson/dev-tools` is installed. The frontend tool list comes from that package's `extra.frontend-tools` setting.

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

In a consumer project with Laravel Boost installed, run `php artisan boost:install` and select the `maarsson/coding-standard` package when offered. For an existing Boost setup, use `php artisan boost:update --discover`.

---

## Running the checks manually

### Hook suites

Run every commit or push check without committing or pushing:

```sh
./vendor/bin/grumphp run --testsuite=git_pre_commit
./vendor/bin/grumphp run --testsuite=git_pre_push
```

`grumphp run` skips the debug statement check; see below.

### Debug statements

GrumPHP runs its `git_blacklist` task only in the pre-commit context, on the staged files. To apply the same check to every tracked file, as the CI workflow does, run from the project root:

```sh
./vendor/bin/check-debug-statements.php
```

The script reads the `git_blacklist` options from `grumphp.yml` and selects files with GrumPHP's own filters, so it follows the hook's keywords, extensions, and whitelist and ignore patterns.

| Situation | Result |
| --- | --- |
| A keyword is found | Fails and lists the matches |
| No keywords are configured | Passes with a notice |
| No tracked file matches the filters | Passes with a notice |
| `phpro/grumphp` is not installed, or `grumphp.yml` is missing | Fails, because the check could not run |

### PHPMD

```sh
./vendor/bin/phpmd analyze . --format=ansi --ruleset=phpmd.yml --suffixes=php --cache --cache-file=.phpmd.cache
```

### PHPCS

Check:

```sh
./vendor/bin/phpcs --parallel=4 --standard=.phpcs.xml -d memory_limit=1G --cache=.phpcs.cache .
```

Fix with PHPCBF, which comes with the same package:

```sh
./vendor/bin/phpcbf --parallel=4 --standard=.phpcs.xml -d memory_limit=1G --cache=.phpcs.cache .
```

### PHP CS Fixer

Check:

```sh
./vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.php --cache-file=.php-cs-fixer.cache
```

Fix:

```sh
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --cache-file=.php-cs-fixer.cache
```

### PHPStan with Larastan and dead code detection

```sh
./vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=1G --no-progress
```

Analyse the complete configured codebase, so that dead code detection sees references across files and tests.

### ESLint

Check:

```sh
npx eslint .
```

Fix:

```sh
npx eslint . --fix
```

The shared configuration uses `vue.configs['flat/recommended']`, which targets Vue 3. For a Vue 2 project, replace it in `eslint.config.mjs` with:

```js
vue.configs['flat/vue2-recommended']
```

Select the ruleset that matches the installed Vue major version before running `--fix`. Vue 3 rules can apply incompatible fixes to Vue 2 code, such as renaming `beforeDestroy` to `beforeUnmount`; the renamed hook will not run in Vue 2. The sync overwrites `eslint.config.mjs`, so restore this adjustment after each sync.

### Stylelint

Check:

```sh
npx stylelint "**/*.{css,scss,vue}"
```

Fix:

```sh
npx stylelint "**/*.{css,scss,vue}" --fix
```

---

## Check details

Some checks have their own rules in this package:

- **Debug statements and development markers:** `dd()`, `dump()`, `var_dump()`, `print_r()`, `var_export()`, `die()`, `exit()`, the `console` logging functions, `debugger;`, `alert()`, the `FIXME:`, `HACK:`, and `XXX:` markers, and merge conflict markers. A keyword called as a method, such as `Dialog.alert()` or `$this->dump()`, is not reported. Files under `public/vendor` are skipped.
- **Protected branch names:** direct commits to `develop`, `staging`, `staging/*`, and `master` are rejected.
- **Commit message format:** `<type>(<optional scope>): <subject>` with one of the types `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `build`, `ci`, `perf`, or `revert`. Subject and body lines are limited to 72 characters.

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

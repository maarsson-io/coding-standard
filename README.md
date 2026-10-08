# maarsson/coding-standard

Centralized custom coding standards that can be applied across my different projects.

<div aria-hidden="true">

[![Latest Stable Version](https://img.shields.io/github/v/release/maarsson-io/coding-standard?label=Latest)](https://github.com/maarsson-io/coding-standard/releases)
![Minimum PHP Version](https://img.shields.io/packagist/dependency-v/maarsson-io/coding-standard/php.svg)
[![Tested on PHP 8,4 to 8.5](https://img.shields.io/badge/tested%20on-PHP%208.4%20|%208.5-brightgreen.svg?maxAge=2419200)][GHA-test]
[![Test](https://github.com/maarsson-io/coding-standard/actions/workflows/ci.yml/badge.svg?branch=master)][GHA-test]
[![License](https://img.shields.io/github/license/maarsson-io/coding-standard)](https://github.com/maarsson-io/coding-standard/blob/master/LICENSE)

[GHA-test]: https://github.com/maarsson-io/coding-standard/actions/workflows/ci.yml

</div>

> [!NOTE]
> See also [maarsson/dev-tools](https://github.com/maarsson-io/dev-tools).

## About

This package provides shared code quality rulesets and a sync script that applies them to consumer projects in a predictable and consistent way.

By following the installation steps below, the rulesets are automatically applied after composer install and composer update in your project. This guarantees that all projects use the exact same ruleset versions.

Currently supported:
- [PHP Mess Detector (PHPMD)](https://phpmd.org/) - detect design and complexity issues
- [PHP CodeSniffer (PHPCS)](https://github.com/PHPCSStandards/PHP_CodeSniffer/) - detect coding standard violations
- [PHP CS Fixer](https://cs.symfony.com/) - automatically enforce modern code style
- [Larastan](https://github.com/larastan/larastan) - catches both obvious & tricky bugs
- [GrumPHP](https://github.com/phpro/grumphp) - runs quality checks through Git hooks
- [ESLint](https://eslint.org/) - statically analyzes JavaScript, TypeScript, and Vue files, including stylistic rules
- [Stylelint](https://stylelint.io/) - CSS linter to avoid errors and enforce conventions

---

## Requirements

- PHP ^8.4
- Composer
- For the frontend tools: npm and Node.js `^22.13.0 || >=24`

---

## Installation

### 1. Package installation

Install the package as a development dependency in your project:

`composer require --dev maarsson/coding-standard`

---

### 2. Project configuration (required)

To ensure the coding standards are applied automatically, you must configure Composer scripts in the target project.

#### 2.1. Add a named sync script

In your project’s `composer.json` add:

```json
{
  "scripts": {
    "coding-standard:sync": [
      "vendor/bin/sync-coding-standards.php"
    ]
  }
}
```

#### 2.2. Run the sync script on install and update

Extend your project’s `composer.json` scripts section to include:

```json
{
  "scripts": {
    "coding-standard:sync": [
      "vendor/bin/sync-coding-standards.php"
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

With this setup, the coding standards are applied automatically.

---

## When does it apply?

Once configured, the sync script runs automatically in the following cases:

Automatic execution:
- After composer install
- After composer update
- During CI pipelines that run composer install or composer update

Manual execution:

- You can also apply the coding standards manually at any time:

    `composer coding-standard:sync` (preferred)

    or:

    `./vendor/bin/sync-coding-standards.php` (direct execution)

---

## What does the sync script do?

The sync script copies shared ruleset files from the package into the project root.

Currently applied files:
- `phpmd.yml`
- `.phpcs.xml`
- `.php-cs-fixer.php`
- `phpstan.neon`
- `grumphp.yml`
- `eslint.config.mjs`
- `stylelint.config.mjs`
- `.githooks/pre-commit`
- `.githooks/commit-msg`
- `.githooks/pre-push`

When run from a Git repository root, the sync script makes the hooks executable and sets the local `core.hooksPath` to `.githooks`. Linked worktrees are supported. Without a Git repository, the files are still copied and activation is skipped.

### Overwrite behavior

The sync process uses an always overwrite strategy:

- Existing shared rulesets, `grumphp.yml`, and the three managed hook files are replaced
- This guarantees all projects use the exact same ruleset

This behavior is intentional and ensures consistency across projects. If you need to customize rules per project, fork this repository or manage overrides outside of this package.

---

## Laravel Boost integration

This package provides a guideline and the `code-quality-checks` skill for Laravel Boost. The skill explains which checks to run, their scope, and how to compare failures in legacy projects.

In a consumer project with Laravel Boost installed, run `php artisan boost:install` and select the `maarsson/coding-standard` package when offered. For an existing Boost setup, use `php artisan boost:update --discover`.

---

## Usage

After the sync script runs, the ruleset files will exist in your project root, but the corresponding tools must also be installed. However if you installed the package via `maarsson/dev-tools` you don’t need to install these manually. Otherwise run:

```sh
composer require --dev phpmd/phpmd
composer require --dev squizlabs/php_codesniffer
composer require --dev friendsofphp/php-cs-fixer
composer require --dev larastan/larastan
composer require --dev shipmonk/dead-code-detector:^1.4
composer require --dev php-parallel-lint/php-parallel-lint:^1.4
composer config allow-plugins.phpro/grumphp false
composer require --dev phpro/grumphp:^2.25
npm install --save-dev eslint@^10 @eslint/js@^10 @stylistic/eslint-plugin@^5 eslint-plugin-vue@^10 globals@^17 typescript@^6 typescript-eslint@^8
npm install --save-dev stylelint@^17 @stylistic/stylelint-plugin@^5 postcss-html@^2 stylelint-config-standard@^40 stylelint-config-standard-scss@^17 stylelint-config-recommended-vue@^2 stylelint-config-standard-vue@^2 @dreamsicle.io/stylelint-config-tailwindcss@^1.2.2
```

Note: `*.cache` should be added to `.gitignore`.

### Using GrumPHP and Git hooks

Set `config.allow-plugins.phpro/grumphp` to `false` in the consumer's `composer.json` before installing the toolchain. This package's sync script installs and activates the shared hooks, so GrumPHP's automatic hook installation is disabled.

| Hook | Checks |
| --- | --- |
| `pre-commit` | PHP lint and version, Composer validation, JSON/XML/YAML, PHPCS, PHP-CS-Fixer, ESLint, Stylelint, debug statements, branch name |
| `commit-msg` | Conventional Commit format |
| `pre-push` | PHPStan with Larastan and dead code detection, PHPMD, PHPUnit, Composer autoload validation |

The hooks block the operation when a check fails and do not apply automatic fixes. Direct commits to `develop`, `staging`, and `master` are rejected; the branch task also rejects `staging/*`.

The consumer project must provide PHPUnit and `phpunit.xml`. Run the pre-push checks manually with:

`./vendor/bin/grumphp run --testsuite=git_pre_push`

### Using PHPMD with the installed ruleset

Run the PHPMD check for displaying violations like this:

`./vendor/bin/phpmd analyze . --format=ansi --ruleset=phpmd.yml --suffixes=php --cache --cache-file=.phpmd.cache`

### Using PHPCS with the installed ruleset

Run the PHPCS check for displaying violations like this:

`./vendor/bin/phpcs --parallel=4 --standard=.phpcs.xml -d memory_limit=1G --cache=.phpcs.cache .`

Or run the PHPCBF (comes with the same package) to actually fix violations like this:

`./vendor/bin/phpcbf --parallel=4 --standard=.phpcs.xml -d memory_limit=1G --cache=.phpcs.cache .`

### Using PHP-CS-Fixer with the installed ruleset

Run the PHP-CS-Fixer check for displaying violations like this:

`./vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.php --cache-file=.php-cs-fixer.cache`

Or run the PHP-CS-Fixer to actually fix violations like this:

`./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --cache-file=.php-cs-fixer.cache`

### Using PHPStan with Larastan and dead code detection

Run PHPStan to check for bugs and unused methods, constants, enum cases, and properties:

`./vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=1G --no-progress`

Analyse the complete configured codebase so that dead code detection includes references across files and tests.


### Using ESLint with the installed ruleset

Run static analysis on JS/TS/Vue code to quickly find problems and code styling issues:

`npx eslint .`

Or auto-fix the fixable violations:

`npx eslint . --fix`

### Using Stylelint with the installed ruleset

Check CSS, SCSS, and Vue styles:

`npx stylelint "**/*.{css,scss,vue}"`

Or auto-fix the fixable violations:

`npx stylelint "**/*.{css,scss,vue}" --fix`

---

## Troubleshooting

***Source file not found***

This typically means:
- the package is not installed correctly
- Composer install/update did not complete successfully

Try:
```sh
composer install
composer coding-standard:sync -vvv
```

***Sync script not executable***

Ensure your environment supports running scripts from vendor/bin and that file permissions are preserved.

---

## License

[MIT](LICENSE)

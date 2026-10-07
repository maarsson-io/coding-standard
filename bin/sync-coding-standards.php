#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Mapping of ruleset files to be copied:
 *      source (inside package) => destination (in project root)
 *
 * @var array<string, string>
 */
const FILES_TO_SYNC = [
    'resources/phpmd.yml.dist' => 'phpmd.yml',
    'resources/phpcs.xml.dist' => '.phpcs.xml',
    'resources/php-cs-fixer.php.dist' => '.php-cs-fixer.php',
    'resources/phpstan.neon.dist' => 'phpstan.neon',
    'resources/grumphp.yml.dist' => 'grumphp.yml',
    'resources/eslint.config.mjs.dist' => 'eslint.config.mjs',
    'resources/githooks/pre-commit' => '.githooks/pre-commit',
    'resources/githooks/commit-msg' => '.githooks/commit-msg',
    'resources/githooks/pre-push' => '.githooks/pre-push',
];

const CLI_COLORS = [
    'green' => "\033[32m",
    'red' => "\033[31m",
    'blue' => "\033[34m",
    'reset' => "\033[0m",
];

$projectRoot = getcwd(); // when executed from app, this is app root
$vendorRoot = $projectRoot . '/vendor/maarsson/coding-standard/';
$errorsCount = 0;

fwrite(STDOUT, '[' . CLI_COLORS['blue'] . 'INFO' . CLI_COLORS['reset'] . '] Syncing coding standard rulesets…' . PHP_EOL);

try {
    $frontendPackage = prepareFrontendPackage($projectRoot);
} catch (RuntimeException | JsonException $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

foreach (FILES_TO_SYNC as $source => $target) {
    $sourcePath = $vendorRoot . $source;
    $targetPath = $projectRoot . '/' . $target;

    if (! @file_exists($sourcePath)) {
        fwrite(STDERR, '[' . CLI_COLORS['red'] . 'FAIL' . CLI_COLORS['reset'] . '] Source file not found at ' . $sourcePath . PHP_EOL);
        $errorsCount++;

        continue;
    }

    $targetDirectory = dirname($targetPath);
    if (! is_dir($targetDirectory) && ! @mkdir($targetDirectory, 0755, true)) {
        fwrite(STDERR, '[FAIL] Failed to create ' . $targetDirectory . PHP_EOL);
        $errorsCount++;

        continue;
    }

    if (! @copy($sourcePath, $targetPath)) {
        fwrite(STDERR, '[' . CLI_COLORS['red'] . 'FAIL' . CLI_COLORS['reset'] . '] Failed to copy ' . $target . ' to project root.' . PHP_EOL);
        $errorsCount++;

        continue;
    }

    if (str_starts_with($target, '.githooks/') && ! @chmod($targetPath, 0755)) {
        fwrite(STDERR, '[FAIL] Failed to make ' . $target . ' executable.' . PHP_EOL);
        $errorsCount++;
    }
}

if ($errorsCount > 0) {
    fwrite(STDERR, '[' . CLI_COLORS['red'] . 'FAIL' . CLI_COLORS['reset'] . '] There were errors during the process.' . PHP_EOL);
    exit(1);
}

if ($frontendPackage !== null) {
    $packagePath = $projectRoot . '/package.json';
    if (! is_file($packagePath) || file_get_contents($packagePath) !== $frontendPackage) {
        if (file_put_contents($packagePath, $frontendPackage) === false) {
            fwrite(STDERR, '[FAIL] Could not write package.json.' . PHP_EOL);
            exit(1);
        }

        fwrite(STDOUT, '[INFO] Frontend settings synchronized. Run npm install to update installed packages and package-lock.json.' . PHP_EOL);
    }
}

// Activate hooks only when invoked from the consumer repository root.
$gitRoot = runGitCommand(['rev-parse', '--show-toplevel']);
if ($gitRoot['code'] === 0 && realpath($gitRoot['output']) === realpath($projectRoot)) {
    $activation = runGitCommand(['config', '--local', 'core.hooksPath', '.githooks']);
    if ($activation['code'] !== 0) {
        fwrite(STDERR, '[FAIL] Failed to activate Git hooks: ' . $activation['error'] . PHP_EOL);
        exit(1);
    }

    fwrite(STDOUT, '[INFO] Git hooks activated from .githooks.' . PHP_EOL);
} else {
    fwrite(STDOUT, '[INFO] Git hook activation skipped: current directory is not a Git repository root.' . PHP_EOL);
}

fwrite(STDOUT, '[' . CLI_COLORS['green'] . ' OK ' . CLI_COLORS['reset'] . '] Coding standard rulesets are applied to project.' . PHP_EOL);
exit(0);

/**
 * @param list<string> $arguments
 *
 * @return array{code: int, output: string, error: string}
 */
function runGitCommand(array $arguments): array
{
    $process = @proc_open(
        ['git', ...$arguments],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    if (! is_resource($process)) {
        return ['code' => 1, 'output' => '', 'error' => 'Could not start Git.'];
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'output' => trim($output), 'error' => trim($error)];
}

/**
 * @return array<string, string>
 */
function frontendDependencies(string $projectRoot): array
{
    $installedPath = $projectRoot . '/vendor/composer/installed.json';
    if (! is_file($installedPath)) {
        return [];
    }

    $installed = json_decode(file_get_contents($installedPath), false, 512, JSON_THROW_ON_ERROR);
    $packages = is_array($installed) ? $installed : ($installed->packages ?? []);
    if (! is_array($packages)) {
        throw new RuntimeException('Invalid Composer installed package metadata.');
    }

    foreach ($packages as $package) {
        if (($package->name ?? null) !== 'maarsson/dev-tools') {
            continue;
        }

        $dependencies = $package->extra->{'frontend-tools'} ?? null;
        if ($dependencies === null) {
            return [];
        }
        if (! $dependencies instanceof stdClass) {
            throw new RuntimeException('dev-tools extra.frontend-tools must be an object.');
        }

        $result = (array) $dependencies;
        foreach ($result as $name => $version) {
            if (! is_string($name) || ! is_string($version) || trim($version) === '') {
                throw new RuntimeException('Invalid npm dependency in dev-tools extra.frontend-tools.');
            }
        }

        return $result;
    }

    return [];
}

function prepareFrontendPackage(string $projectRoot): ?string
{
    $dependencies = frontendDependencies($projectRoot);
    if ($dependencies === []) {
        return null;
    }

    $packagePath = $projectRoot . '/package.json';
    $package = is_file($packagePath)
        ? json_decode(file_get_contents($packagePath), false, 512, JSON_THROW_ON_ERROR)
        : (object) ['private' => true, 'type' => 'module'];
    if (! $package instanceof stdClass) {
        throw new RuntimeException('package.json must contain an object.');
    }

    $managed = $package->{'maarsson-coding-standard'} ?? new stdClass();
    if (! $managed instanceof stdClass) {
        throw new RuntimeException('package.json maarsson-coding-standard must be an object.');
    }

    $runtimeDependencies = $package->dependencies ?? new stdClass();
    if (! $runtimeDependencies instanceof stdClass) {
        throw new RuntimeException('package.json dependencies must be an object.');
    }
    foreach ($dependencies as $name => $version) {
        if (property_exists($runtimeDependencies, $name)) {
            throw new RuntimeException('Frontend tool ' . $name . ' already exists in dependencies; move it to devDependencies before syncing.');
        }
    }

    $scripts = [];
    if (isset($dependencies['eslint'])) {
        $scripts = ['eslint' => 'eslint .', 'eslint:fix' => 'eslint . --fix'];
    }
    $sections = ['devDependencies' => $dependencies, 'scripts' => $scripts];
    foreach ($sections as $section => $expected) {
        $current = $package->{$section} ?? new stdClass();
        $previous = $managed->{$section} ?? new stdClass();
        if (! $current instanceof stdClass || ! $previous instanceof stdClass) {
            throw new RuntimeException('package.json ' . $section . ' and its managed settings must be objects.');
        }

        foreach ($expected as $name => $value) {
            if (property_exists($current, $name)
                && $current->{$name} !== $value
                && (! property_exists($previous, $name) || $current->{$name} !== $previous->{$name})) {
                throw new RuntimeException('Conflicting package.json ' . $section . ' entry: ' . $name . '. Resolve the local override before syncing.');
            }
            $current->{$name} = $value;
        }
        foreach ((array) $previous as $name => $value) {
            if (! array_key_exists($name, $expected) && ($current->{$name} ?? null) === $value) {
                unset($current->{$name});
            }
        }
        if ($section === 'devDependencies') {
            $sorted = (array) $current;
            ksort($sorted);
            $current = (object) $sorted;
        }
        $package->{$section} = $current;
        $managed->{$section} = (object) $expected;
    }
    $package->{'maarsson-coding-standard'} = $managed;

    return json_encode($package, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

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

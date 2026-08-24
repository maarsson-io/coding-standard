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
    'resources/phpmd.xml.dist' => 'phpmd.xml',
    'resources/phpcs.xml.dist' => 'phpcs.xml',
    'resources/php-cs-fixer.php.dist' => '.php-cs-fixer.php',
    'resources/phpstan.neon.dist' => 'phpstan.neon',
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

    if (! @copy($sourcePath, $targetPath)) {
        fwrite(STDERR, '[' . CLI_COLORS['red'] . 'FAIL' . CLI_COLORS['reset'] . '] Failed to copy ' . $target . ' to project root.' . PHP_EOL);
        $errorsCount++;
    }
}

if ($errorsCount > 0) {
    fwrite(STDERR, '[' . CLI_COLORS['red'] . 'FAIL' . CLI_COLORS['reset'] . '] There were errors during the process.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, '[' . CLI_COLORS['green'] . ' OK ' . CLI_COLORS['reset'] . '] Coding standard rulesets are applied to project.' . PHP_EOL);
exit(0);

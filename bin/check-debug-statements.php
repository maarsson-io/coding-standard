#!/usr/bin/env php
<?php

declare(strict_types=1);

use GrumPHP\Collection\FilesCollection;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/*
 * Applies GrumPHP's git_blacklist task to every tracked file.
 *
 * GrumPHP runs that task only in the pre-commit context, where it sees just the
 * staged files. This script reads the task's options from grumphp.yml, selects
 * the files with GrumPHP's own collection filters and runs the same search, so
 * the hook and CI cannot drift apart.
 *
 * Run it from the project root. A check that cannot run fails; a configuration
 * without keywords or matching files passes.
 */

$projectRoot = getcwd(); // when executed from app, this is app root

require $_composer_autoload_path ?? $projectRoot . '/vendor/autoload.php';

$config = Yaml::parseFile($projectRoot . '/grumphp.yml');
$options = ($config['grumphp']['tasks']['git_blacklist'] ?? []) + [
    'keywords' => [],
    'whitelist_patterns' => [],
    'ignore_patterns' => [],
    'triggered_by' => ['php'],
    'regexp_type' => 'G',
    'match_word' => false,
];

$listing = new Process(['git', 'ls-files', '-z'], $projectRoot);
$listing->mustRun();

$trackedFiles = array_filter(explode("\0", $listing->getOutput()), strlen(...));

$files = new FilesCollection(array_map(
    static fn (string $file): SplFileInfo => new SplFileInfo($file, dirname($file), $file),
    array_values($trackedFiles),
));

if ($options['whitelist_patterns'] !== []) {
    $files = $files->paths($options['whitelist_patterns']);
}

$files = $files->extensions($options['triggered_by'])->notPaths($options['ignore_patterns']);

if ($options['keywords'] === [] || count($files) === 0) {
    fwrite(STDOUT, '::notice::No git_blacklist keywords or matching files; nothing to check.' . PHP_EOL);
    exit(0);
}

$command = ['git', '--literal-pathspecs', 'grep', '--cached', '-n', '--break', '--heading'];

if ($options['match_word']) {
    $command[] = '--word-regexp';
}

$command[] = '-' . $options['regexp_type'];

foreach ($options['keywords'] as $keyword) {
    $command[] = '-e';
    $command[] = $keyword;
}

$command[] = '--';

foreach ($files as $file) {
    $command[] = $file->getPathname();
}

$search = new Process($command, $projectRoot);
$status = $search->run();

if ($status === 0) {
    fwrite(STDOUT, $search->getOutput() . PHP_EOL);
    fwrite(STDOUT, '::error::Blacklisted keywords found in tracked files.' . PHP_EOL);
    exit(1);
}

if ($status !== 1) {
    fwrite(STDERR, $search->getErrorOutput() . PHP_EOL);
    fwrite(STDOUT, '::error::git grep failed with exit code ' . $status . '.' . PHP_EOL);
    exit($status);
}

fwrite(STDOUT, 'No blacklisted keywords found in ' . count($files) . ' tracked files.' . PHP_EOL);

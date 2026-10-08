<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$repo = dirname(__DIR__, 2);
$filesystem = new Filesystem();
$temporary = sys_get_temp_dir() . '/coding-standard-frontend-' . bin2hex(random_bytes(8));
$consumer = $temporary . '/consumer with spaces';
$packagePath = $consumer . '/package.json';
$installedPath = $consumer . '/vendor/composer/installed.json';
$writeJson = static function (string $path, mixed $value) use ($filesystem): void {
    $filesystem->dumpFile($path, json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL);
};
$metadata = static function (array $dependencies) use ($writeJson, $installedPath): void {
    $writeJson($installedPath, ['packages' => [[
        'name' => 'maarsson/dev-tools',
        'extra' => ['frontend-tools' => (object) $dependencies],
    ]]]);
};
$sync = static function (bool $success = true, ?string $message = null) use ($repo, $consumer): void {
    $process = new Process([PHP_BINARY, $repo . '/bin/sync-coding-standards.php'], $consumer);
    $process->run();
    $output = $process->getOutput() . $process->getErrorOutput();
    verifyFrontend($process->isSuccessful() === $success, $output);
    if ($message !== null) {
        verifyFrontend(str_contains($output, $message), $output);
    }
};

try {
    $filesystem->mkdir($consumer . '/vendor/maarsson');
    $filesystem->symlink($repo, $consumer . '/vendor/maarsson/coding-standard');
    $original = [
        'private' => true,
        'scripts' => ['build' => 'vite build', 'dev' => 'vite'],
        'dependencies' => ['vue' => '^3.5'],
        'devDependencies' => ['vite' => '^8.0', 'eslint' => '^10.12.0'],
        'custom' => (object) [],
    ];
    $writeJson($packagePath, $original);
    $sync();
    verifyFrontend(json_decode(file_get_contents($packagePath), true) === json_decode(json_encode($original), true), 'No metadata should preserve package.json.');

    $metadata(['eslint' => '^10.12.0', '@eslint/js' => '^10.0.1', 'stylelint' => '^17.16.0']);
    $sync();
    $result = json_decode(file_get_contents($packagePath), false, 512, JSON_THROW_ON_ERROR);
    verifyFrontend($result->scripts->build === 'vite build' && $result->scripts->dev === 'vite', 'Application scripts changed.');
    verifyFrontend($result->scripts->eslint === 'eslint .' && $result->scripts->{'eslint:fix'} === 'eslint . --fix', 'Lint scripts missing.');
    verifyFrontend($result->scripts->stylelint === 'stylelint "**/*.{css,scss,vue}"'
        && $result->scripts->{'stylelint:fix'} === 'stylelint "**/*.{css,scss,vue}" --fix', 'Stylelint scripts missing.');
    verifyFrontend($result->devDependencies->stylelint === '^17.16.0', 'Stylelint dependency missing.');
    verifyFrontend(file_get_contents($consumer . '/stylelint.config.mjs') === file_get_contents($repo . '/resources/stylelint.config.mjs.dist'), 'Wrong Stylelint config.');
    verifyFrontend($result->dependencies->vue === '^3.5' && $result->devDependencies->vite === '^8.0', 'Application dependencies changed.');
    verifyFrontend($result->custom instanceof stdClass, 'Empty object changed.');
    verifyFrontend(file_get_contents($consumer . '/eslint.config.mjs') === file_get_contents($repo . '/resources/eslint.config.mjs.dist'), 'Wrong ESLint config.');
    verifyFrontend(! file_exists($consumer . '/node_modules') && ! file_exists($consumer . '/package-lock.json'), 'Sync must not install npm dependencies.');
    $first = file_get_contents($packagePath);
    $sync();
    verifyFrontend(file_get_contents($packagePath) === $first, 'Sync is not idempotent.');

    $changed = json_decode($first);
    $changed->scripts->stylelint = 'custom-stylelint';
    $writeJson($packagePath, $changed);
    $filesystem->dumpFile($consumer . '/stylelint.config.mjs', 'local config');
    $sync(false, 'Conflicting package.json scripts entry: stylelint');
    verifyFrontend(file_get_contents($consumer . '/stylelint.config.mjs') === 'local config', 'Conflict modified Stylelint config.');
    $filesystem->dumpFile($packagePath, $first);

    $metadata(['eslint' => '^11.0.0']);
    $sync();
    $result = json_decode(file_get_contents($packagePath));
    verifyFrontend($result->devDependencies->eslint === '^11.0.0', 'Managed upgrade failed.');
    verifyFrontend(! isset($result->devDependencies->{'@eslint/js'}), 'Removed managed dependency remains.');
    verifyFrontend(! isset($result->devDependencies->stylelint) && ! isset($result->scripts->stylelint) && ! isset($result->scripts->{'stylelint:fix'}), 'Removed Stylelint settings remain.');

    foreach (['devDependencies' => ['eslint' => '^9.0'], 'scripts' => ['eslint' => 'custom-lint']] as $section => $override) {
        $changed = clone $result;
        $changed->{$section} = (object) $override;
        $writeJson($packagePath, $changed);
        $before = file_get_contents($packagePath);
        $filesystem->dumpFile($consumer . '/eslint.config.mjs', 'local config');
        $sync(false, 'Conflicting package.json');
        verifyFrontend(file_get_contents($packagePath) === $before, 'Conflict modified package.json.');
        verifyFrontend(file_get_contents($consumer . '/eslint.config.mjs') === 'local config', 'Conflict modified config.');
    }
    $filesystem->dumpFile($packagePath, '{broken');
    $sync(false, 'Syntax error');
    $writeJson($packagePath, ['scripts' => []]);
    $sync(false, 'must be objects');
    $filesystem->remove($packagePath);
    $sync();
    $result = json_decode(file_get_contents($packagePath));
    verifyFrontend($result->private === true && $result->devDependencies->eslint === '^11.0.0', 'New package.json not created.');
} finally {
    $filesystem->remove($temporary);
}

echo 'Frontend consumer sync passed: preservation, idempotency, upgrades and conflicts.' . PHP_EOL;

function verifyFrontend(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

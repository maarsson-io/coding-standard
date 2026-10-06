<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$repo = dirname(__DIR__, 2);
$filesystem = new Filesystem();
$temporary = sys_get_temp_dir() . '/coding-standard-grumphp-' . bin2hex(random_bytes(8));
$consumer = $temporary . '/consumer with spaces';
$environment = [
    'COMPOSER_CACHE_DIR' => $temporary . '/cache',
    'GRUMPHP_TEST_LOG' => $consumer . '/calls.log',
    'GRUMPHP_TEST_FAIL_PHPSTAN' => '0',
    'GRUMPHP_TEST_FAIL_PHPUNIT' => '0',
];

/**
 * @param list<string> $arguments
 */
$run = static function (
    array $arguments,
    int $expected = 0,
    ?string $contains = null,
    ?string $cwd = null,
) use ($consumer, &$environment): string {
    $process = new Process($arguments, $cwd ?? $consumer, $environment);
    $process->setTimeout(180);
    $process->run();
    $output = $process->getOutput() . $process->getErrorOutput();

    check(
        $process->getExitCode() === $expected,
        'Unexpected exit code for ' . $process->getCommandLine() . PHP_EOL . $output,
    );
    if ($contains !== null) {
        check(str_contains($output, $contains), 'Expected output: ' . $contains . PHP_EOL . $output);
    }

    return $output;
};

try {
    $filesystem->mkdir($consumer);
    $run(['git', 'init', '-q', '-b', 'feature/hooks']);
    $run(['git', 'config', 'user.name', 'Test Maintainer']);
    $run(['git', 'config', 'user.email', 'test@example.invalid']);
    $run(['git', 'commit', '--allow-empty', '-qm', 'test: initial commit']);

    $filesystem->mkdir($consumer . '/vendor/maarsson');
    $filesystem->symlink($repo, $consumer . '/vendor/maarsson/coding-standard');
    $binaries = $consumer . '/vendor/bin';
    $filesystem->mkdir($binaries);
    $searchPaths = array_filter(
        explode(PATH_SEPARATOR, getenv('PATH') ?: ''),
        static fn (string $path): bool => realpath($path) !== realpath($repo . '/vendor/bin'),
    );
    $environment['PATH'] = $binaries . PATH_SEPARATOR . implode(PATH_SEPARATOR, $searchPaths);
    $environment['GRUMPHP_BIN_DIR'] = $binaries;
    foreach (['grumphp', 'parallel-lint', 'phpcs', 'php-cs-fixer', 'phpmd'] as $binary) {
        $filesystem->symlink($repo . '/vendor/bin/' . $binary, $binaries . '/' . $binary);
    }

    // Verify push orchestration separately from application analysis and tests.
    foreach (['phpstan', 'phpunit'] as $binary) {
        $executable = $binaries . '/' . $binary;
        $failureVariable = 'GRUMPHP_TEST_FAIL_' . strtoupper($binary);
        $filesystem->dumpFile(
            $executable,
            '#!/bin/sh' . "\n"
            . 'printf "%s\n" "' . $binary . ' $*" >> "$GRUMPHP_TEST_LOG"' . "\n"
            . 'if [ "${' . $failureVariable . ':-0}" = 1 ]; then' . "\n"
            . '  echo "Expected ' . $binary . ' failure"; exit 1' . "\n"
            . 'fi' . "\n",
        );
        $filesystem->chmod($executable, 0755);
    }

    $filesystem->dumpFile($consumer . '/composer.json', json_encode([
        'name' => 'maarsson/grumphp-consumer-test',
        'description' => 'Isolated Git hook integration test',
        'license' => 'MIT',
        'require' => ['php' => '^8.4'],
        'config' => ['allow-plugins' => false],
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n");
    $filesystem->dumpFile($consumer . '/.gitignore', "vendor/\ncalls.log\n*.cache\n");
    $run([PHP_BINARY, $repo . '/bin/sync-coding-standards.php']);
    check(trim($run(['git', 'config', '--local', 'core.hooksPath'])) === '.githooks', 'Hooks not activated.');
    $run([$binaries . '/grumphp', 'list', '--no-ansi']);

    $app = $consumer . '/app';
    $filesystem->mkdir($app);
    $source = $app . '/OperatorSpacing.php';
    $good = $repo . '/test/fixtures/Phpcs/GoodCode/OperatorSpacing.php';
    $bad = $repo . '/test/fixtures/Phpcs/BadCode/OperatorSpacing.php';
    $filesystem->copy($good, $source, true);
    $run(['git', 'add', 'app/OperatorSpacing.php']);
    $run(['git', 'commit', '-qm', 'feat: add valid code']);
    $run(['git', 'add', 'composer.json', '.gitignore', 'grumphp.yml', 'phpmd.yml',
        '.phpcs.xml', '.php-cs-fixer.php', 'phpstan.neon', '.githooks']);
    $run(['git', 'commit', '-qm', 'chore: add consumer configuration']);

    $run(['git', 'commit', '--allow-empty', '-qm', 'invalid message'], 1, 'git_commit_message');
    $run(['git', 'checkout', '-qb', 'develop']);
    $run(['git', 'commit', '--allow-empty', '-qm', 'feat: protected branch'], 1, 'Direct commits');
    $run(['git', 'checkout', '-q', 'feature/hooks']);

    $debug = $consumer . '/debug.js';
    $filesystem->dumpFile($debug, "console.log('debug');\n");
    $run(['git', 'add', 'debug.js']);
    $run(['git', 'commit', '-qm', 'feat: debug statement'], 1, 'blacklisted keywords');
    $run(['git', 'reset', '-q', 'HEAD', '--', 'debug.js']);
    $filesystem->remove($debug);

    $filesystem->copy($bad, $source, true);
    $run(['git', 'add', 'app/OperatorSpacing.php']);
    $run(['git', 'commit', '-qm', 'feat: invalid spacing'], 1, 'OperatorSpacing');
    $filesystem->dumpFile($source, "<?php invalid syntax\n");
    $run(['git', 'add', 'app/OperatorSpacing.php']);
    $run(['git', 'commit', '-qm', 'feat: invalid syntax'], 1, 'phplint');
    $filesystem->copy($good, $source, true);
    $run(['git', 'add', 'app/OperatorSpacing.php']);

    // The hook must resolve the repository root when invoked from a subdirectory.
    $prePush = $consumer . '/.githooks/pre-push';
    $run([$prePush], cwd: $app);
    $calls = file_get_contents($consumer . '/calls.log');
    check($calls !== false, 'Could not read push task calls.');
    check(str_contains($calls, 'phpstan analyse') && str_contains($calls, 'phpstan.neon'), $calls);
    check(str_contains($calls, 'phpunit --configuration=phpunit.xml'), $calls);
    $environment['GRUMPHP_TEST_FAIL_PHPSTAN'] = '1';
    $run([$prePush], 1, 'Expected phpstan failure', $app);
    $environment['GRUMPHP_TEST_FAIL_PHPSTAN'] = '0';
    $environment['GRUMPHP_TEST_FAIL_PHPUNIT'] = '1';
    $run([$prePush], 1, 'Expected phpunit failure', $app);
    $environment['GRUMPHP_TEST_FAIL_PHPUNIT'] = '0';

    $unused = $app . '/UnusedLocalVariable.php';
    $filesystem->copy($repo . '/test/fixtures/Phpmd/BadCode/UnusedLocalVariable.php', $unused, true);
    $run(['git', 'add', 'app/UnusedLocalVariable.php']);
    $run([$prePush], 1, 'UnusedLocalVariable', $app);
    $run(['git', 'reset', '-q', 'HEAD', '--', 'app/UnusedLocalVariable.php']);
    $filesystem->remove($unused);

    // Linked worktrees have a .git file, rather than a .git directory.
    $worktree = $temporary . '/linked worktree';
    $run(['git', 'worktree', 'add', '-qb', 'feature/worktree', $worktree]);
    $filesystem->mkdir($worktree . '/vendor/maarsson');
    $filesystem->symlink($repo, $worktree . '/vendor/maarsson/coding-standard');
    $run([PHP_BINARY, $repo . '/bin/sync-coding-standards.php'], cwd: $worktree);
    check(is_executable($worktree . '/.githooks/pre-push'), 'Worktree hook is not executable.');
    check(
        trim($run(['git', 'config', '--local', 'core.hooksPath'], cwd: $worktree)) === '.githooks',
        'Worktree hooks not activated.',
    );
} finally {
    $filesystem->remove($temporary);
}

echo 'GrumPHP consumer hooks passed: commits, messages, branches, blacklist, lint, push and worktree.' . PHP_EOL;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

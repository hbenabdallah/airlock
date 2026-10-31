<?php

declare(strict_types=1);

/*
 * Packs Airlock, with its production dependencies and its warmed-up production cache, into one PHAR.
 *
 * Run it from a checkout prepared for production (composer install --no-dev, composer dump-env prod, cache:warmup),
 * with phar.readonly off: php -d phar.readonly=0 bin/build-phar.php build/airlock.phar
 *
 * The stub points SCRIPT_FILENAME at bin/console: Symfony Runtime runs the script it names, which would otherwise be
 * the PHAR itself.
 */

$root = dirname(__DIR__);
$output = $argv[1] ?? $root.'/build/airlock.phar';

$excluded = '#^(\.github|demo|docs|tests|build|vendor/bin|var/(?!cache/prod/))/|(^|/)\.git/|(^|/)\.env\.(local|[a-z]+\.local|test|dev)$|^(Dockerfile|\.dockerignore|rector\.php|phpstan\.dist\.neon|phpunit\.dist\.xml|\.php-cs-fixer\.dist\.php|\.php-cs-fixer\.cache)$#';

foreach (['.env.local', '.env.prod.local'] as $secrets) {
    if (is_file($root.'/'.$secrets)) {
        fwrite(\STDERR, sprintf("%s exists: composer dump-env would pack its secrets into the PHAR. Build from a clean checkout.\n", $secrets));
        exit(1);
    }
}
if (!is_file($root.'/.env.local.php') || !is_dir($root.'/var/cache/prod')) {
    fwrite(\STDERR, "Prepare the checkout for production first: composer dump-env prod, then bin/console cache:warmup.\n");
    exit(1);
}

$files = [];
$entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($entries as $entry) {
    if (!$entry instanceof SplFileInfo || !$entry->isFile()) {
        continue;
    }
    $path = substr($entry->getPathname(), strlen($root) + 1);
    if (1 !== preg_match($excluded, $path)) {
        $files[$path] = $entry->getPathname();
    }
}

if (is_file($output)) {
    unlink($output);
}
if (!is_dir(dirname($output))) {
    mkdir(dirname($output), recursive: true);
}

$phar = new Phar($output, 0, 'airlock.phar');
$phar->startBuffering();
$phar->buildFromIterator(new ArrayIterator($files));
$phar->setStub(<<<'STUB'
    #!/usr/bin/env php
    <?php
    Phar::mapPhar('airlock.phar');
    $_SERVER['SCRIPT_FILENAME'] = 'phar://airlock.phar/bin/console';
    require 'phar://airlock.phar/bin/console';
    __HALT_COMPILER();
    STUB);
$phar->stopBuffering();

$size = filesize($output);
if (false === $size) {
    fwrite(\STDERR, sprintf("%s was not written.\n", $output));
    exit(1);
}

printf("%s: %d files, %.1f MB\n", $output, count($files), $size / 1e6);

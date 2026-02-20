<?php

declare(strict_types=1);

/**
 * Ensures the database exists before running migrations.
 * Compatible with SQLite, MySQL and PostgreSQL: on SQLite, doctrine:database:create
 * fails with "not supported by platform" (no list-databases), so we ignore and continue.
 */
$projectRoot = dirname(__DIR__);
chdir($projectRoot);

$console = $projectRoot . '/bin/console';
$cmd = 'php ' . escapeshellarg($console) . ' doctrine:database:create --if-not-exists 2>&1';
$output = [];
$code = 0;
exec($cmd, $output, $code);
$out = implode("\n", $output);

if ($code !== 0 && str_contains($out, 'not supported by platform')) {
    exit(0);
}

exit($code);

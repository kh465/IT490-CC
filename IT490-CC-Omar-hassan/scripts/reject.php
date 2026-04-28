#!/usr/bin/php
<?php

require_once __DIR__ . '/rmqhelper.php';

$release = $argv[1] ?? null;
$by = $argv[2] ?? trim(shell_exec('whoami'));
$reason = $argv[3] ?? null;

if(!$release) {
	fwrite(STDERR, "reject.php (releasenum) (by) (reason)\n");
	exit(1);
}

$baseDir = 'var/www/sample';
$path = "$baseDir/releases/$release";

$pending = @readlink("$baseDir/pending");
if ($pending === $path)
	unlink@("$baseDir/pending");

publishDeployEvent('set_status', ['release' => $release,
	'status' => 'rejected',
	'decided_by' => $by,
	'notes' => $reason,]);

echo "$release was rejected! Files were kept at $path.\n";

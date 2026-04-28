#!/usr/bin/php
<?php
require_once __DIR__ . "/rmqhelper.php";

$release = $argv[1] ?? null;
$commit = $argv[2] ?? '';
$env = $argv[3] ?? 'qa';

if(!$release) {
	fwrite(STDERR, "error! no release found!\n");
	exit(1);
}

$siteDir = '/var/www/sample';
$tarball = "/tmp/{$release}.tar.gz";
$relPath = "$siteDir/releases/$release";

exec("tar -xzf " . escapeshellarg($tarball) . " -C " . escapeshellarg("$siteDir/releases") . " 2>&1", $out, $ret);
if($ret !==0) {
	fwrite(STDERR, "failed to unpack!\n");
	exit(1);
}
unlink($tarball);

exec("ln -sfn " . escapeshellarg($relPath) . " " . escapeshellarg("$siteDir/pending"));

publishDeployEvent('log_deploy', [
	'env' => $env,
	'release' => $release,
	'commit' => $commit,
	'status' => 'pending',
	'decided_by' => null,
	'notes' => null,]);

echo "$release pending, awaiting decision... (use approve/reject)";
